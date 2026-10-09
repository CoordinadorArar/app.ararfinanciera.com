<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

/**
 * Motor de cálculo del deterioro de cartera.
 *
 * Todos los pasos son sentencias sobre conjuntos: PHP orquesta y mide, SQL
 * Server hace el trabajo. El SQL vive en métodos sql*() propios para poder
 * inspeccionarlo y probarlo sin ejecutar el pipeline completo.
 *
 * Convenciones que impone el entorno:
 * - Placeholders posicionales, nunca con nombre: el driver ODBC falla si un
 *   parámetro con nombre se repite en la consulta.
 * - La fecha de corte se declara una sola vez con DECLARE al inicio del lote y
 *   luego se usa como variable, para no repetir el binding decenas de veces.
 * - Fechas en ISO 8601 con T: el login del servidor usa idioma español y
 *   'aaaa-mm-dd hh:mm:ss' se interpretaría como dd/mm/aaaa.
 */
class Deterioro extends Model
{
    use HasFactory;

    protected $table = 'det_corte';
    protected $primaryKey = 'id_corte';
    public $timestamps = false;

    const ESTADO_ABIERTO = 'ABIERTO';
    const ESTADO_CALCULADO = 'CALCULADO';
    const ESTADO_CERRADO = 'CERRADO';

    /** Origen con que el cierre de diciembre firma lo que escribe en det_fiscal_acumulado. */
    const ORIGEN_ACUMULADO_CIERRE = 'CIERRE_DICIEMBRE';

    /**
     * Prefijos de documento de cruce que componen el saldo de cartera en SIESA,
     * definidos por Contabilidad el 17 de septiembre de 2026: FAT, FEX, OP y CC.
     *
     * `OP` se escribe `OPE` porque `OP` no existe en la base: medidos los 17
     * valores que toma `f353_id_tipo_docto_cruce` en la compañía 7, el código
     * de la operación de factoring es `OPE` y no hay ninguno de dos letras que
     * pueda confundirse con él. Es la misma abreviatura que usan las notas de
     * los propios documentos ('FAC. OPE-1-7582', 'OPE: 8391').
     *
     * El campo es el del cruce y no el del documento propio del saldo porque es
     * el que filtra el SQL de referencia que entregó Contabilidad
     * (`documentacion/SQL_CONSULTA_SALDOS_SIESA.sql`, `IN ('FAT','OPE')`). Los
     * dos campos ofrecen los cuatro prefijos, pero dan totales distintos —al 31
     * de julio de 2026, 15.517.122.157,97 por cruce contra 11.575.382.969,19
     * por documento propio— así que la elección no es indiferente y se resuelve
     * por la fuente que la definió.
     */
    const PREFIJOS_SALDO_SIESA = ['FAT', 'FEX', 'OPE', 'CC'];

    /**
     * Cuenta contable del auxiliar que identifica el interés en SIESA,
     * definida por Contabilidad el 18 de septiembre de 2026: 'Ing. por Cobrar
     * Intereses'. Es el dato autoritativo de la separación capital/interés;
     * el texto del documento sólo queda como respaldo (ver `sqlSaldoSiesa`).
     */
    const CUENTA_INTERES_SIESA = '13451001';

    /**
     * Tipos de documento de cruce en los que la cuenta de interes vale. Acotado
     * por Contabilidad el 18 de septiembre de 2026: las `FEX` de `13451001` no
     * se reclasifican (ver `sqlSaldoSiesa`).
     */
    const TIPOS_DOCTO_INTERES_SIESA = ['CC', 'FAT'];

    const CUENTA_CAPITAL_SIESA = '13050501';

    /**
     * Desplazamiento del consecutivo de un `OPE` de refinanciacion: SIESA le da
     * a la operacion refinanciada el consecutivo `10000000 + n`, donde `n` es
     * el numero de la operacion original.
     *
     * Medido sobre la compania 7: los 1.134 registros `OPE` con consecutivo en
     * el rango 10000000..10999999 son **23 operaciones distintas**, todas con
     * nota 'Operacion Refinanciada<n>-- OPE-…', y en las 23 el sufijo del
     * consecutivo aparece literalmente en la nota, sin una sola excepcion.
     *
     * Del corte de agosto de 2026 hay dos operaciones que cruzan por esta via,
     * y las dos **cuadran al peso** contra el capital de factoring, que es la
     * prueba de que el desplazamiento es el correcto y no una coincidencia:
     * la 3517 por 23.312.495,00 —que hoy es una partida SOLO_FACTORING por ese
     * mismo importe— y la 4411 por 1.247.431,00, que hoy aparece con saldo cero
     * porque su `OPE 4411` esta cancelado y todo su capital vive en el
     * `OPE 10004411`.
     *
     * Se implementa como rango y no como `consec LIKE '%' + n`, que fue la
     * primera forma propuesta: medido sobre el mismo corte, el `LIKE` mete
     * saldo ajeno en **46 operaciones** por 19.801.464,00, porque hay
     * consecutivos con forma `<operacion><cuota>` —`5450029` es la cuota 29 de
     * la 5450— que terminan en el numero de otra operacion. El rango no puede
     * confundirlos.
     */
    const CONSEC_OPE_REFINANCIADO = 10000000;

    /**
     * El numero de operacion que hay detras de un consecutivo `OPE`, como
     * expresion de SQL: el propio consecutivo, o el consecutivo menos
     * CONSEC_OPE_REFINANCIADO cuando es una refinanciacion.
     *
     * Vive en un solo sitio porque son cuatro los consumidores que tienen que
     * coincidir —el enlace, las dos partidas de la conciliacion y los dos
     * controles C-SIESA—: si uno normalizara y otro no, la operacion recibiria
     * su saldo y a la vez seguiria figurando como partida sin cruzar.
     */
    public static function operacionDeConsecutivoOpe($columna)
    {
        $tope = self::CONSEC_OPE_REFINANCIADO + 999999;

        return "(CASE WHEN {$columna} BETWEEN ".self::CONSEC_OPE_REFINANCIADO." AND {$tope}
                      THEN {$columna} - ".self::CONSEC_OPE_REFINANCIADO."
                      ELSE {$columna} END)";
    }

    /**
     * El texto que sigue al ancla de operacion en una nota de SIESA, como
     * expresion de SQL. Son cuatro las formas con que las notas escriben la
     * operacion —`OPE: n`, `OPE-1-n`, `OPE n` y `OPEn`— y se prueban en ese
     * orden; el ancla es siempre el literal completo y nunca el numero suelto.
     *
     * Vive en un solo sitio porque son dos los consumidores que tienen que
     * coincidir: `sqlSaldoSiesa()`, que clasifica la fila y le atribuye la
     * operacion, e `interesALaFechaDelEventoEnLote()`, que la congela. Con un
     * parseo por consumidor una operacion podia quedar bien clasificada como
     * interes y a la vez ser incongelable: medido sobre las 31.284 filas de
     * interes de la compania 7, el reconocimiento del congelamiento —que
     * exigia el literal `OPE: `— dejaba fuera 260.
     *
     * La exclusion de los `OPE` **no** esta aqui: es propia de
     * `sqlSaldoSiesa()`, que los cruza por consecutivo, y el congelamiento no
     * la quiere.
     */
    public static function restoDeAnclaOpe($columna)
    {
        return "CASE
                    WHEN CHARINDEX('OPE: ', {$columna}) > 0
                        THEN SUBSTRING({$columna}, CHARINDEX('OPE: ', {$columna}) + 5, 12)
                    WHEN PATINDEX('%OPE-1-[0-9]%', {$columna}) > 0
                        THEN SUBSTRING({$columna}, PATINDEX('%OPE-1-[0-9]%', {$columna}) + 6, 12)
                    WHEN PATINDEX('%OPE [0-9]%', {$columna}) > 0
                        THEN SUBSTRING({$columna}, PATINDEX('%OPE [0-9]%', {$columna}) + 4, 12)
                    WHEN PATINDEX('%OPE[0-9]%', {$columna}) > 0
                        THEN SUBSTRING({$columna}, PATINDEX('%OPE[0-9]%', {$columna}) + 3, 12)
                END";
    }

    /**
     * El numero de operacion que hay en un resto de `restoDeAnclaOpe()`: sus
     * digitos hasta el primer caracter que no lo es. Corta pegado a texto
     * ('OPE: 569ESPE' es la operacion 569), que es justo lo que el corte por el
     * primer espacio no hacia.
     *
     * Devuelve nulo **solo si el resto es nulo**, es decir si ninguna de las
     * cuatro anclas encajo. Un resto que exista y no empiece por digito da
     * **cero, no nulo**: `LEFT()` deja cadena vacia y `TRY_CONVERT(int, '')`
     * vale 0 en SQL Server. Queda escrito porque el nulo es lo que los dos
     * consumidores leen como 'no se pudo atribuir', y un cero silencioso seria
     * una operacion inexistente colandose por esa puerta.
     *
     * Solo lo alcanza la rama `OPE: ` —las otras tres exigen digito en el
     * PATINDEX—, con una nota de forma 'OPE: ABC'. Medido el 23 de septiembre
     * de 2026: cero filas de interes, cero filas del snapshot con
     * `id_operacion_nota = 0` y cero marcas con `id_operacion = 0`. Es
     * comportamiento anterior a la extraccion, que movio la expresion literal.
     */
    public static function operacionDeRestoOpe($resto)
    {
        return "TRY_CONVERT(int, LEFT({$resto}, NULLIF(PATINDEX('%[^0-9]%', {$resto} + 'x'), 0) - 1))";
    }

    /** Los prefijos de PREFIJOS_SALDO_SIESA como lista literal para un IN de SQL. */
    public static function prefijosSaldoSiesa()
    {
        return "'".implode("', '", self::PREFIJOS_SALDO_SIESA)."'";
    }

    public static function sqlOperacionesUnicasPorTercero($idCorte = '@idCorte')
    {
        return "SELECT nit = CONVERT(varchar(25), id_cliente), id_operacion = MIN(id_operacion)
                FROM det_deterioro_operacion
                WHERE id_corte = {$idCorte} AND id_cliente IS NOT NULL
                GROUP BY id_cliente
                HAVING COUNT(*) = 1";
    }

    /** Los tipos de TIPOS_DOCTO_INTERES_SIESA como lista literal para un IN de SQL. */
    public static function tiposDoctoInteresSiesa()
    {
        return "'".implode("', '", self::TIPOS_DOCTO_INTERES_SIESA)."'";
    }

    /** Tabla origen del corte y tabla del corte de comparación, según el ambiente. */
    public static function origenCorte()
    {
        return config('database.faico').'.dbo.ResumenVigentesClientes';
    }

    public static function origenComparacion()
    {
        return config('database.faico').'.dbo.ResumenVigentesClientes1';
    }

    /**
     * Prefijo de las tablas de SIESA. Se lee por nombre de base y no por la
     * conexión 'unoeearar' porque la extracción es un INSERT ... SELECT entre
     * bases del mismo servidor, igual que la de cartera contra FAICO y
     * FactoringManagerDatos. Sobre SIESA el módulo sólo hace SELECT (D-13).
     */
    public static function origenSiesa()
    {
        return config('database.connections.unoeearar.database').'.dbo.';
    }

    /* ---------------------------------------------------------------------
     | Expresiones reutilizables
     |-------------------------------------------------------------------- */

    /**
     * DAYS360 de Excel, método US, como expresión inline.
     *
     * Se replica el comportamiento de Excel, no la norma NASD: Excel ajusta
     * únicamente el día 31 y no aplica el caso especial de fin de febrero.
     * Reproducir la norma desplazaría operaciones de rango en febrero.
     *
     * Va inline y no como función escalar a propósito: en SQL Server 2014 una
     * UDF escalar fuerza ejecución fila a fila y anula el paralelismo.
     */
    public static function sqlDays360($inicio, $fin)
    {
        $d1 = "(CASE WHEN DAY($inicio) = 31 THEN 30 ELSE DAY($inicio) END)";
        $d2 = "(CASE WHEN DAY($fin) = 31 AND $d1 = 30 THEN 30 ELSE DAY($fin) END)";

        return "((YEAR($fin) - YEAR($inicio)) * 360"
             . " + (MONTH($fin) - MONTH($inicio)) * 30"
             . " + $d2 - $d1)";
    }

    /* ---------------------------------------------------------------------
     | Ciclo de vida del corte
     |-------------------------------------------------------------------- */

    /** Periodo (IdAno, IdPeriodo) que tiene cargado hoy la tabla origen. */
    public static function periodoDisponible($tabla = null)
    {
        $tabla = $tabla ?: self::origenCorte();
        $sql = "SELECT TOP 1 IdAno, IdPeriodo, filas = COUNT(*)
                FROM $tabla GROUP BY IdAno, IdPeriodo ORDER BY COUNT(*) DESC";
        return DB::selectOne($sql);
    }

    /**
     * La tabla origen no está parametrizada por fecha: la llena un
     * procedimiento que la vacía y recarga. Antes de extraer hay que confirmar
     * que lo cargado corresponde al corte que se está pidiendo.
     */
    public static function validarPeriodoOrigen($fechaCorte, $tabla = null)
    {
        $tabla = $tabla ?: self::origenCorte();
        $periodo = self::periodoDisponible($tabla);
        if (!$periodo) {
            return ['ok' => false, 'mensaje' => "La tabla $tabla está vacía."];
        }
        $esperadoAno = date('Y', strtotime($fechaCorte));
        $esperadoMes = date('n', strtotime($fechaCorte));
        $cargadoAno = (int) $periodo->IdAno;
        $cargadoMes = (int) $periodo->IdPeriodo;

        if ($cargadoAno != $esperadoAno || $cargadoMes != $esperadoMes) {
            return ['ok' => false, 'mensaje' =>
                "La tabla $tabla tiene cargado el periodo $cargadoAno-"
                . str_pad($cargadoMes, 2, '0', STR_PAD_LEFT)
                . ", no el corte solicitado ($esperadoAno-"
                . str_pad($esperadoMes, 2, '0', STR_PAD_LEFT) . ').'];
        }
        return ['ok' => true, 'periodo' => $periodo];
    }

    public static function crearCorte($fechaCorte, $fechaComparacion, $idUsuario)
    {
        $anterior = DB::table('det_corte')
            ->where('fecha_corte', '<', $fechaCorte)
            ->orderByDesc('fecha_corte')
            ->first();

        DB::table('det_corte')->insert([
            'fecha_corte' => $fechaCorte,
            'fecha_comparacion' => $fechaComparacion,
            'estado' => self::ESTADO_ABIERTO,
            'id_corte_anterior' => $anterior ? $anterior->id_corte : null,
            'id_usuario' => $idUsuario,
            'fecha_creacion' => self::ahora(),
            'modo_compatibilidad_excel' => 0,
        ]);

        return DB::table('det_corte')->where('fecha_corte', $fechaCorte)->value('id_corte');
    }

    /**
     * Guarda de inmutabilidad del snapshot: un corte cerrado no admite ninguna
     * escritura del módulo que lleve su id_corte.
     *
     * ejecutar() ya rechazaba recalcular un corte cerrado, pero era la única
     * puerta cerrada. Explicar una partida, clasificar una baja, cargar las
     * cifras del libro o borrar el corte entero también escriben con su id, y
     * cualquiera de las cuatro cambiaría un corte ya cerrado en silencio.
     *
     * No cubre marcar ni levantar una suspensión, y conviene decir por qué: la
     * marca no lleva id_corte ni escribe en ninguna tabla del corte, su efecto
     * se materializa cuando el corte se recalcula, y recalcular un corte cerrado
     * ya es imposible. Rechazarla además obligaría a falsear la fecha del
     * evento, que es el anclaje del congelamiento de D-06, cada vez que un
     * fallecimiento o una insolvencia se conociera después del cierre del mes.
     */
    public static function corteAbierto($idCorte)
    {
        $corte = DB::table('det_corte')->where('id_corte', $idCorte)->first();
        if (!$corte) {
            return ['ok' => false, 'mensaje' => "El corte $idCorte no existe."];
        }
        if ($corte->estado === self::ESTADO_CERRADO) {
            return ['ok' => false, 'corte' => $corte, 'mensaje' =>
                'El corte del '.$corte->fecha_corte.' está cerrado y no admite cambios. '
                . 'Hay que reabrirlo para corregirlo.'];
        }
        return ['ok' => true, 'corte' => $corte, 'mensaje' => null];
    }

    /** Deja el corte como recién creado, para poder recalcularlo. */
    public static function limpiarCorte($idCorte)
    {
        // det_conciliacion_explicacion no entra: es trabajo del usuario sobre
        // las partidas y tiene que sobrevivir al recálculo del corte.
        foreach (['det_deterioro_operacion', 'det_corte_cuadre', 'det_corte_detalle_cuota',
                  'det_corte_saldo_siesa', 'det_conciliacion_partida',
                  'det_corte_param_rango_mora', 'det_corte_param_producto',
                  'det_corte_param_interes', 'det_corte_param_convencion',
                  'det_corte_param_fiscal', 'det_corte_param_fiscal_rango',
                  'det_corte_param_causal_suspension',
                  'det_corte_param_causal_salida',
                  'det_corte_param_cuenta_contable'] as $tabla) {
            DB::table($tabla)->where('id_corte', $idCorte)->delete();
        }

        // La marca acompaña a la copia congelada de cuentas: mientras el PUC
        // esté vacío, la copia y la ausencia de copia son la misma tabla vacía,
        // y sólo esta bandera distingue "este corte ya congeló sus cuentas, que
        // resultaron ninguna" de "este corte es anterior a la fase y nunca las
        // congeló". De esa distinción depende que un corte cerrado siga
        // exportando lo mismo cuando Contabilidad siembre las cuentas.
        DB::table('det_corte')->where('id_corte', $idCorte)->update(['cuentas_congeladas' => 0]);
    }

    /**
     * Borra el corte entero, con lo que limpiarCorte() conserva a propósito.
     *
     * Eliminar un corte es lo contrario de recalcularlo: la explicación de una
     * partida de conciliación y la clasificación de una baja sobreviven al
     * recálculo porque partidas y bajas se rehacen, pero si el corte deja de
     * existir quedan huérfanas para siempre, sin corte al que volver ni forma
     * de consultarlas.
     */
    public static function eliminarCorte($idCorte)
    {
        $abierto = self::corteAbierto($idCorte);
        if (!$abierto['ok']) {
            throw new \RuntimeException($abierto['mensaje']);
        }

        self::limpiarCorte($idCorte);
        DB::table('det_conciliacion_explicacion')->where('id_corte', $idCorte)->delete();
        DB::table('det_salida_operacion')->where('id_corte', $idCorte)->delete();
        DB::table('det_corte')->where('id_corte', $idCorte)->delete();
    }

    /* ---------------------------------------------------------------------
     | Paso 1 · Congelar paramétricas
     |-------------------------------------------------------------------- */

    /**
     * Copia las paramétricas vigentes a la fecha de corte dentro del corte.
     * El motor lee siempre de esta copia, de modo que recalcular un corte da
     * el mismo resultado aunque después se corrija una vigencia.
     */
    public static function congelarParametros($idCorte, $fechaCorte)
    {
        $vigencia = 'vigente_desde <= ? AND (vigente_hasta IS NULL OR vigente_hasta >= ?)';

        DB::insert(
            "INSERT INTO det_corte_param_rango_mora
                (id_corte, codigo, dias_desde, dias_hasta, etiqueta, pct_deterioro_contable, orden)
             SELECT ?, codigo, dias_desde, dias_hasta, etiqueta, pct_deterioro_contable, orden
             FROM det_param_rango_mora WHERE $vigencia",
            [$idCorte, $fechaCorte, $fechaCorte]
        );

        DB::insert(
            "INSERT INTO det_corte_param_producto (id_corte, nom_operacion, producto)
             SELECT ?, nom_operacion, producto
             FROM det_param_producto WHERE $vigencia",
            [$idCorte, $fechaCorte, $fechaCorte]
        );

        DB::insert(
            "INSERT INTO det_corte_param_interes
                (id_corte, producto, tasa_mora_mensual, aplica_mora, sigue_calculando_suspendido)
             SELECT ?, producto, tasa_mora_mensual, aplica_mora, sigue_calculando_suspendido
             FROM det_param_interes WHERE $vigencia",
            [$idCorte, $fechaCorte, $fechaCorte]
        );

        DB::insert(
            "INSERT INTO det_corte_param_convencion
                (id_corte, base_dias, origen_mora, base_incluye_interes, siesa_manda_sobre_base, tarifa_renta)
             SELECT ?, base_dias, origen_mora, base_incluye_interes, siesa_manda_sobre_base, tarifa_renta
             FROM det_param_convencion WHERE $vigencia",
            [$idCorte, $fechaCorte, $fechaCorte]
        );

        DB::insert(
            "INSERT INTO det_corte_param_fiscal (id_corte, metodo, pct_anual, dias_minimos_mora, activo)
             SELECT ?, metodo, pct_anual, dias_minimos_mora, activo
             FROM det_param_fiscal WHERE $vigencia",
            [$idCorte, $fechaCorte, $fechaCorte]
        );

        DB::insert(
            "INSERT INTO det_corte_param_fiscal_rango (id_corte, metodo, rango_codigo, pct)
             SELECT ?, metodo, rango_codigo, pct
             FROM det_param_fiscal_rango WHERE $vigencia",
            [$idCorte, $fechaCorte, $fechaCorte]
        );

        DB::insert(
            "INSERT INTO det_corte_param_causal_suspension (id_corte, codigo, descripcion, activa)
             SELECT ?, codigo, descripcion, activa
             FROM det_param_causal_suspension WHERE $vigencia",
            [$idCorte, $fechaCorte, $fechaCorte]
        );

        DB::insert(
            "INSERT INTO det_corte_param_causal_salida
                (id_corte, codigo, descripcion, activa, cierra_fiscal, pide_referencia, orden)
             SELECT ?, codigo, descripcion, activa, cierra_fiscal, pide_referencia, orden
             FROM det_param_causal_salida WHERE $vigencia",
            [$idCorte, $fechaCorte, $fechaCorte]
        );

        DB::insert(
            "INSERT INTO det_corte_param_cuenta_contable
                (id_corte, concepto, producto, cuenta_debito, cuenta_credito, descripcion)
             SELECT ?, concepto, producto, cuenta_debito, cuenta_credito, descripcion
             FROM det_param_cuenta_contable WHERE $vigencia",
            [$idCorte, $fechaCorte, $fechaCorte]
        );

        // Marca que este corte ya congeló sus cuentas, aunque no haya ninguna.
        // Sin ella, un corte congelado con el PUC vacío y un corte anterior a la
        // fase son indistinguibles, y el segundo tiene que leer la paramétrica
        // viva mientras el primero no puede.
        DB::table('det_corte')->where('id_corte', $idCorte)->update(['cuentas_congeladas' => 1]);

        return self::hashParametros($idCorte);
    }

    public static function hashParametros($idCorte)
    {
        $partes = [];
        foreach (['det_corte_param_rango_mora' => 'codigo',
                  'det_corte_param_producto' => 'nom_operacion',
                  'det_corte_param_interes' => 'producto',
                  'det_corte_param_convencion' => 'id_corte',
                  'det_corte_param_fiscal' => 'metodo',
                  'det_corte_param_fiscal_rango' => ['metodo', 'rango_codigo'],
                  'det_corte_param_causal_suspension' => 'codigo',
                  'det_corte_param_causal_salida' => 'codigo',
                  'det_corte_param_cuenta_contable' => ['concepto', 'producto']] as $tabla => $orden) {
            $consulta = DB::table($tabla)->where('id_corte', $idCorte);
            foreach ((array) $orden as $columna) {
                $consulta->orderBy($columna);
            }
            $filas = $consulta->get();
            $partes[] = $tabla . ':' . json_encode($filas);
        }
        return hash('sha256', implode('|', $partes));
    }

    /**
     * El método fiscal adoptado (D-04) es política, no código: se resuelve por
     * el activo del snapshot. Sin paramétrica congelada o con más de un método
     * activo el cálculo daría ceros silenciosos en una deducción de renta, así
     * que la corrida se detiene antes de producirlos.
     */
    public static function validarMetodoFiscal($idCorte)
    {
        $conteo = DB::selectOne(
            'SELECT filas = COUNT(*), activos = SUM(CASE WHEN activo = 1 THEN 1 ELSE 0 END),
                    con_tarifa = SUM(CASE WHEN activo = 1 AND pct_anual IS NOT NULL THEN 1 ELSE 0 END)
             FROM det_corte_param_fiscal WHERE id_corte = ?', [$idCorte]);

        if (!$conteo || (int) $conteo->filas === 0) {
            throw new \RuntimeException(
                'No hay paramétrica fiscal congelada para el corte: revise la vigencia de det_param_fiscal.');
        }
        $activos = (int) $conteo->activos;
        if ($activos !== 1) {
            throw new \RuntimeException(
                "La paramétrica fiscal del corte tiene $activos métodos activos y debe tener exactamente uno.");
        }
        if ((int) $conteo->con_tarifa !== 1) {
            throw new \RuntimeException(
                'El método fiscal activo del corte no tiene pct_anual definido: sin tarifa la deducción quedaría en cero.');
        }
        return DB::table('det_corte_param_fiscal')
            ->where('id_corte', $idCorte)->where('activo', 1)->value('metodo');
    }

    /* ---------------------------------------------------------------------
     | Paso 2 · Extracción
     |-------------------------------------------------------------------- */

    public static function sqlExtraccion($tablaOrigen = null)
    {
        $tablaOrigen = $tablaOrigen ?: self::origenCorte();
        return "INSERT INTO det_corte_detalle_cuota (
                    id_corte, id_operacion, id_cuota, id_detalle_operacion,
                    id_ano, id_periodo, id_cliente, cliente, id_pagador, pagador,
                    id_comisionista, comisionista, fec_operacion, tasa_interes_cliente,
                    saldo_capital, saldo_intereses, saldo_intereses_causado, saldo_mora,
                    saldo_mora_causado, saldo_admon, saldo_neto_recibir,
                    fec_inicial_corriente, fec_final_corriente, fec_inicial_mora,
                    dias_vencidos, dias_corriente, reliquida_mora,
                    tipo_operacion, nom_operacion, nom_mod_operacion, valor_credito)
                SELECT ?, r.IdOperacion, r.IdCuota, r.IdDetalleOperacion,
                    r.IdAno, r.IdPeriodo, r.IdCliente, r.Cliente, r.IdPagador, r.Pagador,
                    r.IdComisionista, r.Comisionista, r.FecOperacion, r.TasaInteresCliente,
                    r.SaldoCapital, r.SaldoIntereses, r.SaldoInteresesCausado, r.SaldoMora,
                    r.SaldoMoraCausado, r.SaldoAdmon, r.SaldoNetoRecibir,
                    r.FecInicialCorriente, r.FecFinalCorriente, r.FecInicialMora,
                    r.DiasVencidos, r.DiasCorriente, r.ReliquidaMora,
                    r.TipoOperacion, r.NomOperacion, r.NomModOperacion, o.TotalVrEntregarBruto
                FROM $tablaOrigen r
                INNER JOIN FactoringManagerDatos.dbo.Operaciones o
                        ON o.IdOperacion = r.IdOperacion";
    }

    public static function extraerCartera($idCorte, $tablaOrigen = null)
    {
        DB::insert(self::sqlExtraccion($tablaOrigen), [$idCorte]);
        return DB::table('det_corte_detalle_cuota')->where('id_corte', $idCorte)->count();
    }

    /* ---------------------------------------------------------------------
     | Paso 2a · Cuotas repetidas del origen
     |-------------------------------------------------------------------- */

    /**
     * Marca las cuotas que el origen entregó por duplicado, para que no cuenten
     * dos veces en el cálculo.
     *
     * ResumenVigentesClientes trae cuotas que son copia exacta de otras de la
     * misma operación: la 8267 repite en sus cuotas 69-72 las 33-36. El
     * duplicado viene del origen; el módulo no lo genera y tampoco lo borra —el
     * detalle sigue siendo copia fiel de lo que entregó factoring, fila por
     * fila, y esa es su razón de ser como prueba—, sólo lo separa del cálculo.
     *
     * EL CRITERIO son las columnas que describen la cuota como hecho económico:
     * misma operación, mismas fecha inicial y final del período corriente,
     * mismo saldo de capital y mismo saldo de intereses. Dos cuotas así son la
     * misma obligación descrita dos veces. id_detalle_operacion queda fuera a
     * propósito: es justamente el campo que las hace parecer distintas, y va a
     * la pantalla como dato de verificación, nunca al criterio.
     *
     * EL SUPERVIVIENTE es el menor id_cuota del grupo, por convención y no por
     * significado: las filas son idénticas en todo lo que importa, así que
     * cualquiera serviría y lo único que hace falta es que la elección sea
     * estable entre corridas. Las demás guardan su id_cuota en duplicada_de.
     *
     * SE ACOTA a fec_final_corriente no nula: sin vencimiento no hay cuota que
     * comparar, y agrupar por un nulo emparejaría filas que no se sabe si son
     * la misma. Hoy no hay ninguna en los tres cortes, pero el criterio no
     * puede depender de eso.
     *
     * FALSOS POSITIVOS ADMITIDOS: dos cuotas legítimamente distintas con las
     * mismas fechas y los mismos dos saldos quedarían marcadas, y en FACTORING
     * eso es concebible. Se admite con los ojos abiertos: medido sobre los tres
     * cortes de PRUEBAS, el criterio laxo —sólo vencimiento y saldos— da
     * exactamente el mismo resultado que éste, de modo que el falso positivo
     * real es cero hoy. Por eso el módulo no aborta ni depura: marca, deja la
     * fila en su sitio y publica cuánto excluyó en C-DUPLICADAS y cuánta base
     * en C-DUPLICADAS-BASE, que sí falla si lo excluido tuviera importe.
     */
    public static function sqlDuplicadas()
    {
        return "WITH g AS (
                    SELECT id_cuota, duplicada_de,
                           superviviente = MIN(id_cuota) OVER (
                               PARTITION BY id_corte, id_operacion, fec_inicial_corriente,
                                            fec_final_corriente, saldo_capital, saldo_intereses)
                    FROM det_corte_detalle_cuota
                    WHERE id_corte = ? AND fec_final_corriente IS NOT NULL
                )
                UPDATE g SET duplicada_de = superviviente
                WHERE id_cuota <> superviviente";
    }

    public static function marcarDuplicadas($idCorte)
    {
        return DB::update(self::sqlDuplicadas(), [$idCorte]);
    }

    /* ---------------------------------------------------------------------
     | Paso 3 · Derivadas por cuota
     |-------------------------------------------------------------------- */

    /**
     * Calcula en una sola pasada las trece columnas derivadas.
     *
     * Sobre la clasificación: FecInicialMora nunca viene nula, porque el
     * procedimiento origen la deriva como el vencimiento de la cuota pendiente
     * más antigua. Para una operación al día esa fecha está en el futuro y
     * DAYS360 sale negativo; ese negativo es el que produce el "Corriente" del
     * Excel, no un nulo. Las operaciones corrientes quedan con rango_codigo
     * nulo, igual que en el libro, donde el BUSCARV falla y devuelve 0.
     */
    public static function sqlDerivadas()
    {
        $diasCuota = self::sqlDays360('d.fec_final_corriente', '@corte');
        $diasOper = self::sqlDays360('d.fec_inicial_mora', '@corte');

        return "DECLARE @corte date = ?;
                DECLARE @idCorte int = ?;

                UPDATE d SET
                    producto = pp.producto,
                    dias_mora_cuota = $diasCuota,
                    dias_mora_operacion = CASE WHEN $diasOper < 0 THEN 0 ELSE $diasOper END,
                    calificacion_abc = CASE WHEN $diasOper < 0 THEN 'Corriente' ELSE pr.codigo END,
                    rango_codigo = CASE WHEN $diasOper < 0 THEN NULL ELSE pr.codigo END,
                    estado_cuota = CASE WHEN $diasCuota > 0 THEN 'VENCIDA' ELSE 'CORRIENTE' END,
                    capital_corriente = CASE WHEN $diasCuota <= 0 THEN d.saldo_capital ELSE 0 END,
                    interes_corriente = CASE WHEN $diasCuota <= 0 THEN d.saldo_intereses ELSE 0 END,
                    capital_vencido   = CASE WHEN $diasCuota >  0 THEN d.saldo_capital ELSE 0 END,
                    interes_vencido   = CASE WHEN $diasCuota >  0 THEN d.saldo_intereses ELSE 0 END,
                    interes_mora = CASE
                        WHEN ISNULL(pi.aplica_mora, 0) = 1 AND $diasCuota > 0
                        THEN d.saldo_capital * pi.tasa_mora_mensual / 30 * $diasCuota
                        ELSE 0 END
                FROM det_corte_detalle_cuota d
                LEFT JOIN det_corte_param_producto pp
                       ON pp.id_corte = d.id_corte AND pp.nom_operacion = d.nom_operacion
                LEFT JOIN det_corte_param_interes pi
                       ON pi.id_corte = d.id_corte AND pi.producto = pp.producto
                LEFT JOIN det_corte_param_rango_mora pr
                       ON pr.id_corte = d.id_corte
                      AND $diasOper BETWEEN pr.dias_desde AND pr.dias_hasta
                WHERE d.id_corte = @idCorte";
    }

    public static function calcularDerivadas($idCorte, $fechaCorte)
    {
        return DB::update(self::sqlDerivadas(), [$fechaCorte, $idCorte]);
    }

    /* ---------------------------------------------------------------------
     | Paso 4 · Enlace con el corte anterior
     |-------------------------------------------------------------------- */

    /**
     * Trae el capital de la misma cuota en el corte anterior. Sustituye el
     * BUSCARV contra la hoja Mes_anterior.
     */
    public static function sqlMesAnterior()
    {
        return "UPDATE d SET capital_mes_anterior = a.saldo_capital
                FROM det_corte_detalle_cuota d
                INNER JOIN det_corte_detalle_cuota a
                        ON a.id_corte = ? AND a.id_operacion = d.id_operacion AND a.id_cuota = d.id_cuota
                WHERE d.id_corte = ?";
    }

    public static function enlazarMesAnterior($idCorte, $idCorteAnterior)
    {
        if (!$idCorteAnterior) {
            return 0;
        }
        return DB::update(self::sqlMesAnterior(), [$idCorteAnterior, $idCorte]);
    }

    /* ---------------------------------------------------------------------
     | Paso 5 · Consolidación por operación
     |-------------------------------------------------------------------- */

    /**
     * Una fila por operación. El rango se toma del máximo de días de mora de
     * sus cuotas, que es constante dentro de la operación porque
     * FecInicialMora es de la operación, no de la cuota (D-01).
     *
     * base_deterioro = capital vencido + interés vencido (RN-03 / D-03).
     * Excluye administración e interés de mora. La prórroga vencida se le suma
     * después, en enlazarSaldoSiesa(), porque no viene de factoring sino del
     * snapshot de SIESA.
     *
     * Y excluye las cuotas que el origen entregó por duplicado: contarlas sería
     * deteriorar dos veces la misma obligación. Las filas siguen en el detalle
     * —es copia fiel del origen—, pero no entran al agregado.
     */
    public static function sqlConsolidacion()
    {
        return "INSERT INTO det_deterioro_operacion (
                    id_corte, id_operacion, id_cliente, cliente, producto, nom_operacion,
                    fec_operacion, fec_inicial_mora, cuotas,
                    capital_corriente, interes_corriente, capital_vencido, interes_vencido,
                    interes_mora, saldo_admon, dias_mora_operacion, calificacion_abc,
                    rango_codigo, base_deterioro, capital_mes_anterior, variacion_capital)
                SELECT a.id_corte, a.id_operacion, a.id_cliente, a.cliente, a.producto, a.nom_operacion,
                    a.fec_operacion, a.fec_inicial_mora, a.cuotas,
                    a.capital_corriente, a.interes_corriente, a.capital_vencido, a.interes_vencido,
                    a.interes_mora, a.saldo_admon, a.dias_mora_operacion, a.calificacion_abc,
                    a.rango_codigo, a.capital_vencido + a.interes_vencido,
                    a.capital_mes_anterior,
                    CASE WHEN a.capital_mes_anterior IS NULL THEN NULL
                         ELSE a.capital_mes_anterior - a.saldo_capital END
                FROM (
                    SELECT id_corte, id_operacion,
                           id_cliente = MIN(id_cliente), cliente = MIN(cliente),
                           producto = MIN(producto), nom_operacion = MIN(nom_operacion),
                           fec_operacion = MIN(fec_operacion), fec_inicial_mora = MIN(fec_inicial_mora),
                           cuotas = COUNT(*),
                           capital_corriente = SUM(capital_corriente),
                           interes_corriente = SUM(interes_corriente),
                           capital_vencido = SUM(capital_vencido),
                           interes_vencido = SUM(interes_vencido),
                           interes_mora = SUM(interes_mora),
                           saldo_admon = SUM(saldo_admon),
                           saldo_capital = SUM(saldo_capital),
                           dias_mora_operacion = MAX(dias_mora_operacion),
                           calificacion_abc = MIN(calificacion_abc),
                           rango_codigo = MIN(rango_codigo),
                           capital_mes_anterior = CASE WHEN COUNT(capital_mes_anterior) = 0
                                                       THEN NULL ELSE SUM(capital_mes_anterior) END
                    FROM det_corte_detalle_cuota
                    WHERE id_corte = ? AND duplicada_de IS NULL
                    GROUP BY id_corte, id_operacion
                ) a";
    }

    public static function consolidarPorOperacion($idCorte)
    {
        DB::insert(self::sqlConsolidacion(), [$idCorte]);
        return DB::table('det_deterioro_operacion')->where('id_corte', $idCorte)->count();
    }

    /* ---------------------------------------------------------------------
     | Paso 5a · Extracción de SIESA (D-13, §14)
     |-------------------------------------------------------------------- */

    /**
     * Snapshot congelado de la cartera abierta de SIESA a la fecha del corte.
     *
     * Sobre la fórmula del saldo, que el documento técnico deja en dos formas.
     * Medido el 4 de septiembre de 2026 contra UNOEEARAR, compañía 7:
     * `SUM(f353_total_db - f353_total_cr)` da 8.553.341.244,10, que es el total
     * documentado en §14.1; la suma equivalente por movimientos sin acotar por
     * fecha da exactamente el mismo número, y la identidad se cumple fila por
     * fila, sin un solo descuadre en toda la compañía. El conteo de filas no se
     * cita porque crece cada día: el ancla estable es el total en pesos. Se
     * usa por tanto la forma por movimientos: es la única que admite el corte
     * por fecha —el encabezado sólo guarda el saldo de hoy— y coincide con la
     * documentada cuando no se acota. Acotada al 31 de julio de 2026 da
     * 8.187.874.511,05, que es el saldo que tenía SIESA a esa fecha y no el de
     * hoy, que es justo lo que un snapshot inmutable necesita.
     *
     * Sobre dónde viaja el número de operación de factoring: el documento dice
     * `f353_consec_docto_cruce` y el SQL de ejemplo lee `f354_consec_docto_cruce`.
     * Comprobado contra la base: tipo y consecutivo coinciden en las 958.862
     * filas de movimiento de la compañía 7, sin una sola discrepancia. Se lee
     * del encabezado, que es una fila por saldo abierto y no depende de que el
     * documento tenga movimientos dentro de la ventana de fechas.
     *
     * Sobre el filtro por prefijo, que llega el 17 de septiembre de 2026. Hasta
     * entonces el snapshot traía los 17 tipos de cruce de la compañía 7 y las
     * filas sin cruce —11.106 hoy, 11.070 cuando las midió la fase 6: la tabla
     * crece—, y por eso reproducía el total de SIESA al peso:
     * 8.187.874.511,05 al 31 de julio de 2026. Contabilidad definió que el
     * saldo de cartera son cuatro prefijos —PREFIJOS_SALDO_SIESA—, de modo que
     * el snapshot pasa a 15.517.122.157,97 y **deja de cuadrar contra el total
     * de SIESA a propósito**: fuera quedan las filas sin cruce, que eran el
     * lado crédito (−8.004.113.842,80), y los ajustes y saldos iniciales que no
     * son cartera del cliente (AJC +1.472.512.072,50, SI −1.533.269.387,00,
     * RC −1.854.615.802,12 entre otros). Quien busque el total de SIESA ya no
     * lo encuentra aquí, y esa es la intención.
     *
     * El filtro no movió ninguna cifra del deterioro cuando entró: el enlace por
     * operación, la conciliación C-3 y el tope fiscal de RN-09 leían entonces
     * sólo los `OPE`. Dejó de ser así el 18 de septiembre de 2026, cuando los
     * `CC`, `FAT` y `FEX` pasaron a atribuirse a la operación por sus notas.
     *
     * Sobre la atribución por notas, que llega el 18 de septiembre de 2026. El
     * consecutivo sólo identifica la operación en los `OPE`; en los `CC` y `FAT`
     * es el del documento, y el número de operación viaja en el texto. Como el
     * consecutivo `OPE` más bajo de la compañía 7 es 4024, **ninguna operación
     * con número menor puede tener uno**: eran 170 operaciones sin saldo en el
     * corte de agosto de 2026, 167 de ellas por debajo de ese número.
     *
     * Se reconocen tres anclas, medidas sobre la compañía 7: `OPE: n` (las
     * 30.048 `FAT` con forma y 982 `CC`), `FAC. OPE-1-n` (3.850 `FEX` en toda la
     * tabla, que hoy están en cero y por eso no llegan al snapshot: la tercera
     * ancla queda de reserva, no de adorno) y `OPE n` en cualquier posición
     * (42.802 `CC` con forma `OPE n CUOTA m`, más las `SALDO OPE n` y
     * `PRORROGA … OPE n`). El ancla es siempre el literal completo y nunca el
     * número suelto: la nota `FAC. REC-1-23266` contiene '3266' y no es de esa
     * operación.
     *
     * `PRORROGA` se clasifica aparte y **no entra en el capital**. La prueba es
     * que en las cinco operaciones donde aparece —2133, 2316, 1276, 1238 y
     * 2136— la fila `SALDO OPE n` **sola** cuadra al peso contra el capital de
     * factoring, y las prórrogas van encima: son 56 filas por 82.170.273 que,
     * contadas como capital, fabricaban partidas de conciliación falsas.
     *
     * Lo que sí cambia desde el corte de agosto de 2026, por definición de
     * Contabilidad: la prórroga **vencida** es interés de la obligación y entra
     * en la base de deterioro, no sólo en el valor nominal. Capital no es y no
     * pasa a serlo —`saldo_siesa` sigue sin tocarla y C-3 no se mueve—: viaja en
     * `interes_prorroga_siesa` y la base la suma al interés vencido, que es el
     * tratamiento que le corresponde a un accesorio ya exigible. La no vencida
     * queda en `PRORROGA_NV` y sigue fuera de la base, igual que el interés
     * corriente por D-03; la que no trae fecha de vencimiento se trata como no
     * vencida, porque meter en la base algo que no se sabe exigible es el error
     * que no se puede cometer en silencio. Hoy las 57 filas de prórroga están
     * todas vencidas y ninguna tiene la fecha nula, de modo que el corte de
     * agosto no cambia una sola fila del snapshot por este reparto.
     *
     * Los `OPE` se excluyen del parseo a propósito —`resto` sale nulo para
     * ellos—: cruzan por consecutivo y dejarlos entrar por las dos vías haría
     * que una operación recibiera el mismo peso dos veces. El control
     * `C-SIESA-DOBLE` vigila que las dos poblaciones sigan sin solaparse.
     *
     * `componente` separa capital de interés porque `saldo_siesa` significa
     * capital y así tiene que seguir significando: la conciliación C-3 lo
     * compara contra el capital de factoring. El interés facturado sólo suma al
     * valor nominal, que es el tope del acumulado fiscal.
     *
     * La separación se hace por la cuenta contable del auxiliar y no por el
     * texto del documento, como definió Contabilidad el 18 de septiembre de
     * 2026: es interés lo que está en la cuenta `13451001` ('Ing. por Cobrar
     * Intereses') **y además** es `CC` o `FAT`. Medido sobre la compañía 7 con
     * saldo abierto al 31 de agosto de 2026, la regla reclasifica 8 filas `CC`
     * por 4.188.150,00 que la heurística de texto anterior —prefijo `FAT` y las
     * notas con 'FACTURA AUTOMATICA', 'FACTURACION AUTOMATICA' o 'INTERES'—
     * daba por capital, y nada más: cero `FAT`, y en sentido contrario ninguna
     * fila fuera de esa cuenta que la heurística llamara interés.
     *
     * Las `FEX` quedan fuera por decisión de Contabilidad, no por descuido: hay
     * 154 en la cuenta de interés por 12.057.279,00 y siguen siendo capital.
     * Sus notas no son cartera del cliente —una dice 'PAGO NOMINA SOLDADOS
     * AGOSTO 2026 FABIAN ANDRES'— y ninguna cruza contra una operación del
     * corte, así que ponerlas de un lado o del otro no mueve un peso de
     * `saldo_siesa`.
     *
     * De las 8 `CC` sólo dos llevan una operación que exista en el corte:
     * `SALDO FAT 8165 OPE 3090` por 120.266,00 y `SALDO CUOTA 11 OPE 2589` por
     * 16.956,00. Esos 137.222,00 son todo el efecto de la regla sobre
     * `saldo_siesa`, y son los mismos con `FEX` o sin ellas. Otra de las ocho
     * dice `FAT 26859 OP 6225` y no se atribuye, porque el ancla reconocida es
     * `OPE n` y ahí dice `OP`: es límite del parseo de notas, no de esta regla.
     *
     * `PRORROGA` se decide antes que la cuenta y no compite con ella: las 57
     * filas de prórroga están todas en `13050501`.
     *
     * El texto se conserva como respaldo sólo cuando el auxiliar no resuelve
     * (`aux.f253_id IS NULL`). Hoy son cero filas en los cuatro prefijos con
     * saldo distinto de cero, pero un auxiliar que no resuelva convertiría
     * interés en capital en silencio, y eso infla el capital sin dejar rastro.
     *
     * Sobre el filtro por cuenta contable, que llega el 21 de septiembre de
     * 2026. Hasta entonces no se filtraba ninguna, y al saldo entraban tanto el
     * deterioro `13990501..05` como el pasivo `28050501`. Contabilidad definió
     * que el saldo son las cuentas que empiezan por `13`, de modo que el pasivo queda
     * fuera y el deterioro sigue dentro.
     *
     * El efecto sobre el corte de agosto de 2026 está medido aislando el
     * cambio, con los mismos datos de SIESA a los dos lados: **12 operaciones**
     * suben su `saldo_siesa` en 3.798.302,00 en total, y **las 12 pasan a
     * cuadrar al peso** contra el capital de factoring, porque lo que les
     * restaba era su propio anticipo. Ninguna baja y ninguna deja de cuadrar.
     *
     * Sobre las dos anclas de nota que llegan el mismo día, `OPE-1-n` y `OPEn`.
     * La primera absorbe la antigua `FAC. OPE-1-n`, que era un caso suyo; la
     * segunda recoge las notas viejas sin espacio, 'FACTURACION AUTOMATICA
     * CORTE: 31/10/17 OPE569'. Son 12 filas por 9.335.145,00, todas de
     * `13451001` y por tanto interés: no mueven un peso de `saldo_siesa` y
     * suben el `valor_nominal_siesa` de la operación 569, que es el techo
     * fiscal que les corresponde.
     *
     * Lo que **no** se hizo, y conviene que quede escrito porque fue lo primero
     * que se propuso: atribuir por `notas LIKE '%<operación>%'`. Medido sobre
     * el corte de agosto de 2026, de las 74 filas que ese `LIKE` engancha
     * **64 son de otro tercero** —47.296.735,00 de terceros que no son el
     * cliente de la operación—, porque las `FEX` traen 'FAC. PRO-1-5478' y ese
     * 5478 es el consecutivo de la factura del proveedor, no una operación.
     * Las 10 legítimas son justamente las que recogen las dos anclas nuevas.
     *
     * Añadir `id_operacion_nota` y `componente` al agrupamiento lleva el
     * snapshot de 12.234 a 12.238 filas y no mueve un peso la suma: medido, no
     * hay un solo grupo `(tipo, consec, tercero)` con unas filas atribuidas y
     * otras no, y sólo 4 grupos `CC` resuelven a más de una operación.
     *
     * Se guardan también las filas que no cruzan contra ninguna operación del
     * corte, porque la conciliación de C-3 necesita los dos sentidos. Se
     * descartan los documentos cuyo saldo neto quedó en cero, que no son
     * cartera abierta, salvo los OPE: un OPE cancelado en SIESA que todavía
     * tiene capital en factoring es una partida de conciliación con saldo cero,
     * no una operación ausente de SIESA, y la diferencia entre las dos cosas es
     * lo que el usuario tiene que explicar.
     *
     * Esa excepción exige que el OPE tenga al menos un movimiento a la fecha
     * del corte: un OPE abierto después del corte todavía no existía y entraría
     * con saldo cero, lo que llevaría el tope R de RN-09 a cero, anularía la
     * deducción del año y fabricaría una partida de conciliación falsa, todo en
     * silencio. Hoy ninguno de esos cruza contra el corte de julio de 2026,
     * pero el modo de falla es silencioso y se cierra por construcción.
     */
    public static function sqlSaldoSiesa()
    {
        $siesa = self::origenSiesa();
        $prefijos = self::prefijosSaldoSiesa();
        $cuentaInteres = self::CUENTA_INTERES_SIESA;
        $cuentaCapital = self::CUENTA_CAPITAL_SIESA;
        $restoNota = self::restoDeAnclaOpe('s.f353_notas');
        $operacionNota = self::operacionDeRestoOpe('a.resto');
        $operacionOpe = self::operacionDeConsecutivoOpe('s.f353_consec_docto_cruce');
        $unicas = self::sqlOperacionesUnicasPorTercero();

        return "DECLARE @corte date = ?;
                DECLARE @idCorte int = ?;
                DECLARE @cia smallint = ?;
                DECLARE @ahora datetime = ?;

                INSERT INTO det_corte_saldo_siesa (
                    id_corte, tipo_docto_cruce, consec_docto_cruce,
                    id_operacion_nota, componente,
                    nit, razon_social, saldo, saldo_vencido, fecha_extraccion)
                SELECT @idCorte, z.tipo, z.consec,
                    z.operacion, z.componente,
                    MIN(z.nit), MIN(z.razon_social),
                    SUM(ISNULL(z.saldo, 0)),
                    SUM(CASE WHEN z.fecha_vcto <= @corte THEN ISNULL(z.saldo, 0) ELSE 0 END), @ahora
                FROM (
                    SELECT tipo = s.f353_id_tipo_docto_cruce,
                           consec = s.f353_consec_docto_cruce,
                           tercero = s.f353_rowid_tercero,
                           nit = t.f200_nit,
                           razon_social = t.f200_razon_social,
                           saldo = m.saldo,
                           fecha_vcto = s.f353_fecha_vcto,
                           movimientos = m.f354_rowid_sa,
                           operacion = CASE
                               WHEN u.id_operacion IS NOT NULL THEN u.id_operacion
                               WHEN s.f353_id_tipo_docto_cruce = 'OPE' THEN {$operacionOpe}
                               ELSE {$operacionNota} END,
                           componente = CASE
                               WHEN aux.f253_id = '{$cuentaInteres}' THEN 'INTERES'
                               WHEN CHARINDEX('PRORROGA', s.f353_notas) > 0
                               THEN CASE WHEN s.f353_fecha_vcto <= @corte
                                         THEN 'PRORROGA' ELSE 'PRORROGA_NV' END
                               ELSE 'CAPITAL' END
                    FROM {$siesa}t353_co_saldo_abierto s
                    INNER JOIN {$siesa}t200_mm_terceros t
                            ON t.f200_rowid = s.f353_rowid_tercero
                    INNER JOIN {$siesa}t253_co_auxiliares aux
                            ON aux.f253_rowid = s.f353_rowid_auxiliar
                    LEFT JOIN ({$unicas}) u
                           ON u.nit = t.f200_nit COLLATE DATABASE_DEFAULT
                    LEFT JOIN (
                        SELECT f354_rowid_sa,
                               saldo = SUM(ISNULL(f354_valor_db, 0) - ISNULL(f354_valor_cr, 0))
                        FROM {$siesa}t354_co_mov_saldo_abierto
                        WHERE f354_fecha < DATEADD(day, 1, @corte)
                        GROUP BY f354_rowid_sa) m
                           ON m.f354_rowid_sa = s.f353_rowid
                    CROSS APPLY (SELECT resto = CASE
                        WHEN s.f353_id_tipo_docto_cruce = 'OPE' THEN NULL
                        ELSE ({$restoNota}) END) a
                    WHERE s.f353_id_cia = @cia
                      AND s.f353_id_tipo_docto_cruce IN ({$prefijos})
                      AND aux.f253_id IN ('{$cuentaCapital}', '{$cuentaInteres}')) z
                GROUP BY z.tipo, z.consec, z.tercero, z.operacion, z.componente
                HAVING SUM(ISNULL(z.saldo, 0)) <> 0
                    OR (z.tipo = 'OPE' AND COUNT(z.movimientos) > 0)";
    }

    public static function extraerSaldoSiesa($idCorte, $fechaCorte)
    {
        DB::insert(self::sqlSaldoSiesa(),
            [$fechaCorte, $idCorte, config('services.siesa.id_cia'), self::ahora()]);

        return DB::table('det_corte_saldo_siesa')->where('id_corte', $idCorte)->count();
    }

    /**
     * Lleva a cada operación del corte su saldo en SIESA, por dos caminos: el
     * consecutivo de un documento `OPE`, y la atribución por notas de los `CC`,
     * `FAT` y `FEX` para la cartera antigua, que no tiene `OPE` posible.
     *
     * Se agrupa antes de unir por las dos vías. Por consecutivo, porque en la
     * compañía 7 hay dos consecutivos `OPE` repartidos entre más de un tercero;
     * por nota, porque una operación reúne decenas de documentos —la 3266 tiene
     * en SIESA 53 filas de `CC` capital, una por cuota, que el `GROUP BY` por
     * tipo, consecutivo, tercero, operación y componente deja en 1 fila del
     * snapshot por 4.763.343,00, más cuatro `FAT` de interés—. Sin el
     * agrupamiento la unión multiplicaría la fila de la operación.
     *
     * **`saldo_siesa` sigue significando capital**, y por eso el `FAT` no entra:
     * `generarConciliacion()` lo compara contra el capital de factoring. Meter
     * ahí el interés facturado movería las 1.939 operaciones que ya cruzan por
     * `OPE` —hay 428.675.552 de `FAT` atribuidos a ellas—, rompería C-3 y
     * contradiría lo que el propio módulo tiene escrito sobre qué es el saldo
     * de SIESA.
     *
     * La prórroga vencida se toma **siempre de la nota**, aunque la operación
     * además cruce por `OPE`: es la única vía que la identifica, y no compite
     * con el `COALESCE` del capital. Se guarda en `interes_prorroga_siesa` y se
     * suma a `base_deterioro` aquí, que es el sitio: el paso corre después de la
     * consolidación por operación y antes del deterioro contable y del fiscal,
     * de modo que los dos toman ya la base con prórroga. `valor_nominal_siesa`
     * no cambia, porque ya sumaba la prórroga por las dos vías.
     *
     * `interes_prorroga_siesa` lleva `ISNULL(…, 0)` y no el nulo de la unión, a
     * diferencia de `saldo_siesa`: como el `UPDATE` recorre todas las
     * operaciones del corte, el cero significa «se midió y no hay prórroga» y el
     * nulo queda reservado para «este corte se calculó antes de la política».
     * Son dos cosas distintas y las pantallas tienen que poder separarlas para
     * decidir si pintan un cero o un «no evaluado», igual que hacen con la marca
     * de cuotas repetidas.
     *
     * `valor_nominal_siesa` es la otra cifra, y es nueva: la suma de los cuatro
     * prefijos que Contabilidad definió, capital más interés facturado. Es el
     * 100 % del valor nominal de la obligación y el techo del acumulado fiscal
     * de RN-09. Para la operación 3266 son 5.391.886, que coinciden al peso con
     * lo que el Excel `1399` declaró como ya deducido en 2025.
     *
     * El `COALESCE` deja la precedencia escrita: si un día una operación tuviera
     * saldo por los dos caminos, manda el `OPE` y `C-SIESA-DOBLE` lo delata, en
     * vez de sumarse en silencio. Hoy no ocurre en ninguna: medido, de las 1.939
     * con `OPE`, ni una recibe un peso de capital por nota. `CC` y `OPE` son
     * esquemas sucesivos, no solapados.
     *
     * Las operaciones que no están en SIESA por ningún camino siguen quedando
     * con nulo, que es el comportamiento neutro de siempre: con nulo, R toma la
     * base.
     *
     * El consecutivo con que se cruza el `OPE` pasa por
     * `operacionDeConsecutivoOpe()` desde el 21 de septiembre de 2026, porque
     * una operación refinanciada lleva su capital en el `OPE 10000000 + n` y no
     * en el `OPE n`, que queda cancelado. Sin normalizar, esas operaciones
     * aparecían con saldo cero o sin saldo y su capital figuraba a la vez como
     * partida `SOLO_SIESA` de un documento que no existe como operación: dos
     * partidas falsas por el mismo dinero.
     *
     * Medido sobre el corte de agosto de 2026: son **2 operaciones**, la 3517
     * por 23.312.495,00 y la 4411 por 1.247.431,00, y las dos pasan a cuadrar
     * al peso contra el capital de factoring. La conciliación queda con una
     * partida `SOLO_FACTORING` menos —la única que tenía el corte— y dos
     * `SOLO_SIESA` menos, 24.559.926,00 que dejan de pedir explicación.
     */
    public static function sqlEnlazarSaldoSiesa()
    {
        $unicas = self::sqlOperacionesUnicasPorTercero();
        $capital = self::sqlCapitalSiesaAtribuido('s', 'u');

        return "DECLARE @idCorte int = ?;

                UPDATE o SET
                    saldo_siesa = c.capital,
                    origen_saldo_siesa = CASE
                        WHEN c.capital IS NULL THEN NULL
                        WHEN u.id_operacion IS NOT NULL THEN 'TERCERO'
                        WHEN s.opes > 0 THEN 'OPE' ELSE 'NOTA' END,
                    valor_nominal_siesa = CASE WHEN c.capital IS NULL THEN NULL ELSE s.total END,
                    interes_prorroga_siesa = ISNULL(s.prorroga, 0),
                    capital_vencido_siesa = CASE WHEN c.capital IS NULL THEN NULL
                        WHEN s.opes > 0 AND u.id_operacion IS NULL THEN s.capital_ope_vencido
                        ELSE s.capital_vencido END,
                    interes_vencido_siesa = CASE WHEN c.capital IS NULL THEN NULL ELSE s.interes_vencido END,
                    base_deterioro = o.base_deterioro + ISNULL(s.prorroga, 0)
                FROM det_deterioro_operacion o
                LEFT JOIN (".self::sqlSaldoSiesaPorOperacion('@idCorte').") s
                       ON s.id_operacion_nota = o.id_operacion
                LEFT JOIN ({$unicas}) u
                       ON u.id_operacion = o.id_operacion
                CROSS APPLY (SELECT capital = {$capital}) c
                WHERE o.id_corte = @idCorte";
    }

    public static function sqlSaldoSiesaPorOperacion($idCorte)
    {
        return "SELECT id_operacion_nota,
                       opes = SUM(CASE WHEN tipo_docto_cruce = 'OPE' THEN 1 ELSE 0 END),
                       capital_ope = SUM(CASE WHEN componente = 'CAPITAL' AND tipo_docto_cruce = 'OPE' THEN saldo ELSE 0 END),
                       capital = SUM(CASE WHEN componente = 'CAPITAL' THEN saldo ELSE 0 END),
                       prorroga = SUM(CASE WHEN componente = 'PRORROGA' THEN saldo ELSE 0 END),
                       capital_ope_vencido = SUM(CASE WHEN componente = 'CAPITAL' AND tipo_docto_cruce = 'OPE' THEN saldo_vencido ELSE 0 END),
                       capital_vencido = SUM(CASE WHEN componente = 'CAPITAL' THEN saldo_vencido ELSE 0 END),
                       interes_vencido = SUM(CASE WHEN componente = 'INTERES' THEN saldo_vencido ELSE 0 END),
                       total = SUM(saldo)
                FROM det_corte_saldo_siesa
                WHERE id_corte = {$idCorte} AND id_operacion_nota IS NOT NULL
                GROUP BY id_operacion_nota";
    }

    public static function sqlCapitalSiesaAtribuido($s, $u)
    {
        return "CASE
                    WHEN {$s}.opes > 0
                    THEN CASE WHEN {$u}.id_operacion IS NULL THEN {$s}.capital_ope ELSE {$s}.capital END
                    ELSE NULLIF({$s}.capital, 0) END";
    }

    public static function enlazarSaldoSiesa($idCorte)
    {
        return DB::update(self::sqlEnlazarSaldoSiesa(), [$idCorte]);
    }

    /* ---------------------------------------------------------------------
     | Paso 5b · Suspensión de causación de intereses (D-05, D-06)
     |-------------------------------------------------------------------- */

    /**
     * Congela, sobre las operaciones ya consolidadas del corte, el efecto de
     * las marcas de suspensión aplicables.
     *
     * Una marca aplica al corte cuando su evento ya ocurrió (fecha_evento <=
     * fecha de corte) y, si se levantó, se levantó después de esa fecha
     * (fecha_reactivacion IS NULL OR fecha_reactivacion > fecha de corte):
     * levantar una marca hoy no puede cambiar un corte ya calculado.
     *
     * interes_vencido y base_deterioro NO se tocan aquí: son el insumo de
     * C-INTERES y C-BASE contra el detalle de cuotas de origen más la prórroga
     * vencida del snapshot, que enlazarSaldoSiesa() es el único paso que suma a
     * la base. El congelamiento vive en columnas propias.
     *
     * Con atribución SIESA (capital_vencido_siesa no nulo) la base de una
     * operación suspendida es el capital vencido más el interés vencido más la
     * prórroga vencida de SIESA, con origen_base 'SIESA'. Sin ella es el
     * capital vencido a la fecha de corte más el interés congelado más la
     * prórroga vencida de SIESA:
     * el capital que todavía no ha vencido no entra al cálculo, igual que en las
     * operaciones sin suspender, y la prórroga entra porque ya está vencida y
     * suspender la causación no la borra. Con ella a los dos lados, la
     * diferencia base_deterioro − base_congelada sigue siendo interes_vencido −
     * interes_vencido_congelado, que es lo que verifica C-SUSPENSION.
     * El congelado se suma al capital vencido y no al capital total porque la
     * base excluye el interés corriente por D-03, y se suma para que el interés
     * no desaparezca de la base, como exige D-06.
     *
     * El saldo total de SIESA no es base: es el capital total abierto de la
     * operación —corriente más vencido, porque SIESA no distingue la cuota
     * vencida de la que está por vencer (§14.1)—, de modo que tomarlo metía
     * capital no vencido en el cálculo. La política inicial de D-15 se planteó
     * incompleta y aquí queda corregida. saldo_siesa se sigue calculando y
     * enlazando para la conciliación con SIESA y sus controles, y
     * valor_nominal_siesa para el tope fiscal R de RN-09; ninguna de las dos es
     * base. El parámetro siesa_manda_sobre_base tampoco se consulta aquí.
     *
     * origen_base queda en 'SIESA' o 'FACTORING' según la fuente de la base;
     * C-SUSPENSION sólo cubre las 'FACTORING' y C-SIESA-BASE las 'SIESA'.
     *
     * interes_no_facturado sólo se llena para productos con
     * sigue_calculando_suspendido = 1 (FACTORING) y con piso en cero: un
     * interés extraído menor que el congelado es una inconsistencia de datos,
     * no un ingreso negativo.
     *
     * Si la marca no trae interes_congelado la operación no se toca: no se
     * puede congelar sin saber a qué valor, y así queda detectable por
     * C-SUSPENSION.
     */
    public static function sqlAplicarSuspensiones()
    {
        return "DECLARE @corte date = ?;
                DECLARE @idCorte int = ?;

                UPDATE o SET
                    suspendida = 1,
                    id_suspension = s.id_suspension,
                    interes_vencido_congelado = s.interes_congelado,
                    base_congelada = CASE WHEN o.capital_vencido_siesa IS NOT NULL
                                     THEN o.capital_vencido_siesa + ISNULL(o.interes_vencido_siesa, 0)
                                     ELSE o.capital_vencido + s.interes_congelado END
                                     + ISNULL(o.interes_prorroga_siesa, 0),
                    origen_base = CASE WHEN o.capital_vencido_siesa IS NOT NULL
                                  THEN 'SIESA' ELSE 'FACTORING' END,
                    interes_no_facturado = CASE
                        WHEN ISNULL(pi.sigue_calculando_suspendido, 0) = 1
                        THEN CASE WHEN o.interes_vencido - s.interes_congelado > 0
                                  THEN o.interes_vencido - s.interes_congelado ELSE 0 END
                        ELSE NULL END
                FROM det_deterioro_operacion o
                INNER JOIN det_suspension_interes s
                        ON s.id_operacion = o.id_operacion
                       AND s.fecha_evento <= @corte
                       AND (s.fecha_reactivacion IS NULL OR s.fecha_reactivacion > @corte)
                       AND s.interes_congelado IS NOT NULL
                LEFT JOIN det_corte_param_interes pi
                       ON pi.id_corte = o.id_corte AND pi.producto = o.producto
                WHERE o.id_corte = @idCorte";
    }

    public static function aplicarSuspensiones($idCorte, $fechaCorte)
    {
        return DB::update(self::sqlAplicarSuspensiones(), [$fechaCorte, $idCorte]);
    }

    /* ---------------------------------------------------------------------
     | Paso 6 · Deterioro contable
     |-------------------------------------------------------------------- */

    /**
     * Base por el porcentaje del rango vigente (RN-04).
     *
     * Corrige los defectos D-A y D-B del libro: la unión compara el código de
     * rango contra el código, no contra la etiqueta de texto ("0 A 31") ni
     * contra la base en pesos. Las operaciones corrientes, sin rango, quedan
     * en cero.
     *
     * ISNULL(o.base_congelada, o.base_deterioro): en una operación suspendida
     * la base efectiva es la congelada (D-06); en las demás, base_congelada es
     * nula y el deterioro se calcula igual que antes de esta fase.
     */
    public static function sqlDeterioroContable()
    {
        return "UPDATE o SET
                    pct_contable = ISNULL(pr.pct_deterioro_contable, 0),
                    deterioro_contable = ISNULL(o.base_congelada, o.base_deterioro) * ISNULL(pr.pct_deterioro_contable, 0)
                FROM det_deterioro_operacion o
                LEFT JOIN det_corte_param_rango_mora pr
                       ON pr.id_corte = o.id_corte AND pr.codigo = o.rango_codigo
                WHERE o.id_corte = ?";
    }

    public static function aplicarDeterioroContable($idCorte)
    {
        return DB::update(self::sqlDeterioroContable(), [$idCorte]);
    }

    /* ---------------------------------------------------------------------
     | Paso 7 · Deterioro fiscal y tope del acumulado
     |-------------------------------------------------------------------- */

    /**
     * Resuelve en una sola pasada O, el general, P, R y N (RN-07 a RN-09).
     *
     * O · individual: la condición del libro es `SI(O(L=E; L=F); I*33%; 0)`.
     * Aquí se evalúa por días de mora contra dias_minimos_mora de la
     * paramétrica, que es como el documento define el campo; con la semilla en
     * 361 el resultado es idéntico, porque el rango E arranca en 361 días.
     *
     * El método adoptado (D-04) sale del dato, no de una constante: se une por
     * activo = 1 y validarMetodoFiscal() garantiza que haya exactamente uno.
     *
     * R · saldo topado: el menor entre el **valor nominal** de la obligación en
     * SIESA y la base. Desde el 18 de septiembre de 2026 el nominal es
     * `valor_nominal_siesa` —los cuatro prefijos que Contabilidad definió,
     * capital más interés facturado— y no `saldo_siesa`, que es sólo capital.
     * Contabilidad lo definió así: el acumulado fiscal se acumula hasta el 100 %
     * del valor nominal y, alcanzado el tope, no genera valores nuevos.
     *
     * Con `saldo_siesa` el tope habría sido más agresivo que lo definido: medido
     * sobre el corte de agosto de 2026, topaba 192 operaciones y recortaba
     * 45.929.892 en vez de 24.283.186. Con el nominal nulo —la operación no está
     * en SIESA por ningún camino— R toma la base, que es el comportamiento
     * neutro de siempre.
     *
     * O · la base del 33 %: `ISNULL(base_congelada, base_deterioro)`, la misma
     * que usa el contable. Contabilidad decidió el 18 de septiembre de 2026 que
     * en una operación con intereses suspendidos el 33 % del art. 145 se calcula
     * sobre la base congelada. Antes el contable usaba la congelada y el fiscal
     * la completa, y esa asimetría era la que fabricaba diferencias temporarias
     * negativas —un activo por impuesto diferido en negativo, que no significa
     * nada— en 14 de las 26 operaciones que las tenían.
     *
     * **P no se recalcula.** Sale de `det_fiscal_acumulado` y de ningún otro
     * sitio: es lo efectivamente declarado ante la DIAN, y el módulo no
     * reconstruye la antigüedad histórica de una obligación. La regla de
     * Contabilidad —«el sistema calcula el acumulado con base en la antigüedad,
     * limitado al 100 % del valor nominal»— gobierna el incremento del año, que
     * es O, y el techo, que es R; no el saldo de arranque. El precio de esa
     * precedencia es que 19 operaciones quedan con P por encima del tope, por
     * 58.053.537,10, y el módulo no las corrige: las publica en
     * `C-FISCAL-NOMINAL` para que se vean en vez de taparlas.
     *
     * **Invariante: no hay reversión por el paso del tiempo.** N nunca es
     * negativa —las dos ramas tienen piso en cero— y `persistirAcumuladoFiscal()`
     * sólo escribe deducciones positivas, de modo que el acumulado nunca baja
     * solo. Si alguna vez hiciera falta bajarlo, es un ajuste de Contabilidad y
     * tiene que entrar por `det_fiscal_acumulado`, no por el motor.
     *
     * Los cuatro valores se calculan en un CROSS APPLY porque N los necesita ya
     * resueltos y SET no puede referenciar columnas que se están asignando.
     */
    public static function sqlDeterioroFiscal()
    {
        return "DECLARE @corte date = ?;
                DECLARE @idCorte int = ?;

                UPDATE o SET
                    deterioro_fiscal_individual = v.ind,
                    deterioro_fiscal_general = v.gen,
                    fiscal_acumulado_anterior = v.p,
                    saldo_topado = v.r,
                    deduccion_fiscal_ano = CASE
                        WHEN v.p + v.ind > v.r THEN CASE WHEN v.r - v.p > 0 THEN v.r - v.p ELSE 0 END
                        ELSE CASE WHEN v.ind > 0 THEN v.ind ELSE 0 END END
                FROM det_deterioro_operacion o
                LEFT JOIN det_corte_param_fiscal pf
                       ON pf.id_corte = o.id_corte AND pf.activo = 1
                LEFT JOIN det_corte_param_fiscal_rango pg
                       ON pg.id_corte = o.id_corte AND pg.metodo = 'GENERAL'
                      AND pg.rango_codigo = o.rango_codigo
                CROSS APPLY (
                    SELECT acumulado = ISNULL((
                        SELECT SUM(fa.valor_deducido)
                        FROM det_fiscal_acumulado fa
                        WHERE fa.id_operacion = o.id_operacion
                          AND fa.ano_gravable < YEAR(@corte)), 0)) a
                CROSS APPLY (
                    SELECT ind = CASE
                                    WHEN o.dias_mora_operacion >= pf.dias_minimos_mora
                                    THEN ISNULL(o.base_congelada, o.base_deterioro) * ISNULL(pf.pct_anual, 0)
                                    ELSE 0 END,
                           gen = o.base_deterioro * ISNULL(pg.pct, 0),
                           p = a.acumulado,
                           r = CASE
                                    WHEN o.valor_nominal_siesa IS NULL
                                      OR o.valor_nominal_siesa >= ISNULL(o.base_congelada, o.base_deterioro)
                                    THEN ISNULL(o.base_congelada, o.base_deterioro)
                                    ELSE o.valor_nominal_siesa END) v
                WHERE o.id_corte = @idCorte";
    }

    public static function aplicarDeterioroFiscal($idCorte, $fechaCorte)
    {
        return DB::update(self::sqlDeterioroFiscal(), [$fechaCorte, $idCorte]);
    }

    /* ---------------------------------------------------------------------
     | Paso 8 · Comparativo contable contra fiscal e impuesto diferido
     |-------------------------------------------------------------------- */

    /**
     * Resuelve §11 en una sola pasada: acumulado fiscal, diferencia temporaria,
     * impuesto diferido y año proyectado de reversión.
     *
     * La diferencia conserva el signo: positiva es activo por impuesto diferido;
     * negativa es pasivo, y aparece cuando el acumulado fiscal supera al
     * contable. Netear ambos sentidos sería incorrecto para la revelación.
     *
     * La tarifa es tarifa_renta de la convención (art. 240), no el pct_anual del
     * 33 % (art. 145 ET), que es la provisión deducible del año y ya se aplicó
     * en el paso anterior. La convención entra por INNER JOIN porque su llave es
     * id_corte, no multiplica filas, y si faltara el resultado debe fallar en
     * validarTarifaRenta(), no quedar nulo en silencio.
     *
     * La proyección de reversión estima cuántos años de deducción faltan para
     * agotar el tope: los años de espera hasta alcanzar dias_minimos_mora más lo
     * pendiente dividido por la deducción anual. Con pf nula, anual da cero y el
     * año sale nulo, que es el resultado honesto.
     *
     * Una operación con el tope agotado **no proyecta año**. Hasta el 18 de
     * septiembre de 2026 devolvía el año del corte, que se leía como «revierte
     * este año» cuando lo que dice el dato es «ya no queda nada que deducir»:
     * eran 70 operaciones rotuladas «2026» en el corte de agosto, las 70 con
     * pendiente en cero. Contabilidad lo cerró: alcanzado el 100 % no se genera
     * reversión por el paso del tiempo, y las que haya corresponden a
     * movimientos o ajustes que ella reconozca.
     *
     * Por eso `estado_reversion` acompaña al año: sin esa columna el nulo
     * significaría dos cosas incompatibles —el tope agotado, que es correcto, y
     * la operación que no alcanza a deducir nunca, que hay que vigilar— y
     * `C-REVERSION` no podría distinguirlas.
     *
     * Esta fase NO escribe en det_fiscal_acumulado: esa tabla la alimenta el
     * cierre de diciembre (fase 7). Si escribiera aquí, reejecutar un corte
     * duplicaría el acumulado del año.
     */
    public static function sqlImpuestoDiferido()
    {
        return "DECLARE @corte date = ?;
                DECLARE @idCorte int = ?;

                UPDATE o SET
                    deterioro_fiscal_acumulado = f.fiscal,
                    diferencia_temporaria      = ISNULL(o.deterioro_contable, 0) - f.fiscal,
                    impuesto_diferido_activo   = (ISNULL(o.deterioro_contable, 0) - f.fiscal) * pc.tarifa_renta,
                    ano_reversion_fiscal       = v.ano,
                    estado_reversion           = CASE
                        WHEN c.pendiente <= 0 AND ISNULL(o.base_congelada, o.base_deterioro) > 0
                             AND c.anual > 0 THEN 'AGOTADA'
                        WHEN v.ano IS NULL THEN 'SIN_PROYECCION'
                        ELSE 'PROYECTADA' END
                FROM det_deterioro_operacion o
                INNER JOIN det_corte_param_convencion pc ON pc.id_corte = o.id_corte
                LEFT JOIN det_corte_param_fiscal pf ON pf.id_corte = o.id_corte AND pf.activo = 1
                CROSS APPLY (SELECT fiscal = ISNULL(o.fiscal_acumulado_anterior,0) + ISNULL(o.deduccion_fiscal_ano,0)) f
                CROSS APPLY (SELECT anual = ISNULL(o.base_congelada, o.base_deterioro) * ISNULL(pf.pct_anual,0),
                                    pendiente = ISNULL(o.saldo_topado, ISNULL(o.base_congelada, o.base_deterioro)) - f.fiscal,
                                    espera = CASE WHEN o.dias_mora_operacion >= pf.dias_minimos_mora THEN 0
                                                  ELSE CEILING((pf.dias_minimos_mora - o.dias_mora_operacion) / 360.0) END) c
                CROSS APPLY (SELECT ano = CASE
                        WHEN ISNULL(o.base_congelada, o.base_deterioro) <= 0 OR c.anual <= 0 THEN NULL
                        WHEN c.pendiente <= 0 THEN NULL
                        ELSE YEAR(@corte) + c.espera + CEILING(c.pendiente / c.anual)
                             - CASE WHEN c.espera > 0 THEN 1 ELSE 0 END END) v
                WHERE o.id_corte = @idCorte";
    }

    public static function aplicarImpuestoDiferido($idCorte, $fechaCorte)
    {
        return DB::update(self::sqlImpuestoDiferido(), [$fechaCorte, $idCorte]);
    }

    /**
     * La tarifa de renta congelada multiplica todo el impuesto diferido: sin
     * ella o en cero el resultado sería un activo nulo, no un error visible.
     */
    public static function validarTarifaRenta($idCorte)
    {
        $tarifa = DB::table('det_corte_param_convencion')
            ->where('id_corte', $idCorte)->value('tarifa_renta');

        if ($tarifa === null) {
            throw new \RuntimeException(
                'No hay convención congelada para el corte: revise la vigencia de det_param_convencion.');
        }
        if ((float) $tarifa <= 0) {
            throw new \RuntimeException(
                'La tarifa de renta congelada del corte es cero: el impuesto diferido quedaría anulado.');
        }
        return $tarifa;
    }

    /* ---------------------------------------------------------------------
     | Paso 9 · Movimiento del mes
     |-------------------------------------------------------------------- */

    /**
     * Trae el deterioro contable de la misma operación en el corte anterior y
     * resuelve la variación del período (RN-10).
     *
     * Es el T9 del libro, que allí se digita a mano por rango y aquí se calcula
     * por operación, más el T10, que es la resta. El enlace es por operación y
     * no reutiliza sqlMesAnterior(), que trabaja a nivel de cuota y compara
     * capital, no deterioro.
     *
     * El LEFT JOIN deja deterioro_mes_anterior nulo en las altas: nulo significa
     * "no existía", que es distinto de "existía en cero". La variación usa
     * ISNULL para que en una alta valga el deterioro completo.
     */
    public static function sqlMovimientoMes()
    {
        return "UPDATE o SET
                    deterioro_mes_anterior = a.deterioro_contable,
                    variacion_deterioro = ISNULL(o.deterioro_contable, 0) - ISNULL(a.deterioro_contable, 0),
                    movimiento = CASE WHEN a.id_operacion IS NULL THEN 'ALTA' ELSE 'VARIACION' END
                FROM det_deterioro_operacion o
                LEFT JOIN det_deterioro_operacion a
                       ON a.id_corte = ? AND a.id_operacion = o.id_operacion
                WHERE o.id_corte = ?";
    }

    public static function aplicarMovimientoMes($idCorte, $idCorteAnterior)
    {
        if (!$idCorteAnterior) {
            return 0;
        }
        return DB::update(self::sqlMovimientoMes(), [$idCorteAnterior, $idCorte]);
    }

    /* ---------------------------------------------------------------------
     | Paso 10 · Conciliación con SIESA (C-3, RN-11)
     |-------------------------------------------------------------------- */

    /** Diferencia en pesos a partir de la cual una operación es partida de C-3. */
    const TOLERANCIA_CONCILIACION = 1;

    /**
     * Regenera las partidas de C-3 del corte en sus tres sentidos.
     *
     * El lado de factoring es el capital total —corriente más vencido— y no la
     * base de deterioro: el saldo de SIESA es capital total, y §14.1 comprobó
     * que la coincidencia al peso se da contra esa suma. Comparar contra la
     * base de D-03 haría aparecer como diferencia todo el capital corriente de
     * la cartera al día.
     *
     * SOLO_SIESA se restringe a los `OPE` con saldo distinto de cero: un
     * documento cancelado en SIESA y ausente de factoring está cerrado en las
     * dos partes y no es una diferencia que explicar. Los `OPE` en cero que sí
     * están en factoring no se pierden: caen en DIFERENCIA por el importe
     * completo del capital de factoring.
     *
     * Las partidas se regeneran enteras en cada corrida, como el resto del
     * corte. La explicación del usuario vive en det_conciliacion_explicacion,
     * fuera de esta tabla y con llave estable (corte y número de operación),
     * justamente para que un recálculo no la borre.
     */
    public static function generarConciliacion($idCorte)
    {
        DB::table('det_conciliacion_partida')->where('id_corte', $idCorte)->delete();

        $columnas = 'id_corte, id_operacion, numero_operacion, nit, cliente,
                     saldo_siesa, saldo_factoring, diferencia, tipo';

        DB::insert(
            "INSERT INTO det_conciliacion_partida ($columnas)
             SELECT s.id_corte, NULL, s.id_operacion_nota, MIN(s.nit), MIN(s.razon_social),
                    SUM(s.saldo), NULL, SUM(s.saldo), 'SOLO_SIESA'
             FROM det_corte_saldo_siesa s
             WHERE s.id_corte = ? AND s.tipo_docto_cruce = 'OPE' AND s.componente = 'CAPITAL'
               AND NOT EXISTS (SELECT 1 FROM det_deterioro_operacion o
                               WHERE o.id_corte = s.id_corte
                                 AND o.id_operacion = s.id_operacion_nota)
             GROUP BY s.id_corte, s.id_operacion_nota
             HAVING SUM(s.saldo) <> 0", [$idCorte]);

        DB::insert(
            "INSERT INTO det_conciliacion_partida ($columnas)
             SELECT o.id_corte, o.id_operacion, o.id_operacion, o.id_cliente, o.cliente,
                    NULL, o.capital_corriente + o.capital_vencido,
                    -(o.capital_corriente + o.capital_vencido), 'SOLO_FACTORING'
             FROM det_deterioro_operacion o
             WHERE o.id_corte = ? AND o.saldo_siesa IS NULL", [$idCorte]);

        DB::insert(
            "INSERT INTO det_conciliacion_partida ($columnas)
             SELECT o.id_corte, o.id_operacion, o.id_operacion, o.id_cliente, o.cliente,
                    o.saldo_siesa, o.capital_corriente + o.capital_vencido,
                    o.saldo_siesa - (o.capital_corriente + o.capital_vencido), 'DIFERENCIA'
             FROM det_deterioro_operacion o
             WHERE o.id_corte = ? AND o.saldo_siesa IS NOT NULL
               AND ABS(o.saldo_siesa - (o.capital_corriente + o.capital_vencido)) > ?",
            [$idCorte, self::TOLERANCIA_CONCILIACION]);

        return DB::table('det_conciliacion_partida')->where('id_corte', $idCorte)->count();
    }

    /* ---------------------------------------------------------------------
     | Paso 11 · Cuadres
     |-------------------------------------------------------------------- */

    /**
     * Vía agregada e independiente del individual (AB30 del Anexo A): base de
     * los rangos que deducen por el porcentaje anual, ambos leídos de la
     * paramétrica congelada del método activo.
     *
     * El motor decide operación por operación con dias_mora_operacion contra
     * dias_minimos_mora; aquí se decide por rango, con los rangos cuyo
     * dias_desde alcanza ese mismo mínimo. Son dos caminos distintos sobre dos
     * paramétricas distintas, de modo que si una se mueve sin la otra el
     * control lo delata en vez de cuadrar por construcción.
     *
     * La base es ISNULL(base_congelada, base_deterioro), la misma que usa el
     * motor desde que el 33 % de una suspendida se calcula sobre la base
     * congelada. Con base_deterioro a secas el control fallaba por 84.895.049,25
     * en el corte de agosto de 2026, que es justo lo que el cambio movió.
     */
    public static function sqlResumenFiscalIndependiente()
    {
        return "SELECT individual = ISNULL(SUM(ISNULL(o.base_congelada, o.base_deterioro) * pf.pct_anual), 0)
                FROM det_deterioro_operacion o
                INNER JOIN det_corte_param_fiscal pf
                        ON pf.id_corte = o.id_corte AND pf.activo = 1
                INNER JOIN det_corte_param_rango_mora pr
                        ON pr.id_corte = o.id_corte AND pr.codigo = o.rango_codigo
                       AND pr.dias_desde >= pf.dias_minimos_mora
                WHERE o.id_corte = ?";
    }

    /**
     * Vía independiente de la variación del período: recorre las operaciones
     * del corte y resta el deterioro que cada una tenía en el anterior, sin
     * mirar movimiento ni variacion_deterioro.
     *
     * El resultado son las altas más la variación de las que continúan, porque
     * las bajas no tienen fila en el corte actual. Si el motor dejara sin
     * enlazar o mal clasificada alguna operación, este camino no lo repetiría.
     */
    public static function sqlVariacionIndependiente()
    {
        return "SELECT variacion = ISNULL(SUM(ISNULL(t.deterioro_contable, 0) - ISNULL(p.deterioro_contable, 0)), 0)
                FROM det_deterioro_operacion t
                LEFT JOIN det_deterioro_operacion p
                       ON p.id_corte = ? AND p.id_operacion = t.id_operacion
                WHERE t.id_corte = ?";
    }

    /**
     * Controles de RN-12 y de las fases posteriores. Desde la fase 6 entran
     * los tres que dependen de SIESA: la extracción, la conciliación de C-3 y
     * C-SIESA-BASE, que cubre las suspendidas con base tomada de SIESA.
     *
     * Un control cuyo insumo no existe en el corte se registra en N/A con el
     * motivo, no en FALLA: los cortes calculados antes de las fases 2 y 3 no
     * tienen las columnas fiscales y el primer corte de la serie no tiene con
     * qué comparar. Marcarlos en rojo sería ruido, y el rojo tiene que seguir
     * significando que algo está mal.
     */
    public static function verificarCuadres($idCorte)
    {
        $fechaCorte = DB::table('det_corte')->where('id_corte', $idCorte)->value('fecha_corte');
        $unicas = self::sqlOperacionesUnicasPorTercero('?');
        $capitalSiesa = self::sqlCapitalSiesaAtribuido('x', 'u');

        // El lado detalle de C-CUOTAS, C-CAPITAL, C-INTERES, C-PARTIC y C-BASE
        // excluye de forma explícita las cuotas repetidas del origen, porque el
        // consolidado tampoco las tiene. Absorberlas en la tolerancia sería
        // taparlas: lo excluido se publica aparte, en C-DUPLICADAS y
        // C-DUPLICADAS-BASE, donde se puede contar y auditar.
        $origen = DB::selectOne(
            'SELECT filas = COUNT(*), capital = SUM(saldo_capital), interes = SUM(saldo_intereses),
                    corriente = SUM(capital_corriente), vencido = SUM(capital_vencido),
                    icorriente = SUM(interes_corriente), ivencido = SUM(interes_vencido)
             FROM det_corte_detalle_cuota WHERE id_corte = ? AND duplicada_de IS NULL', [$idCorte]);

        $duplicadas = DB::selectOne(
            'SELECT filas = COUNT(*), operaciones = COUNT(DISTINCT id_operacion),
                    base = ISNULL(SUM(capital_vencido + interes_vencido), 0)
             FROM det_corte_detalle_cuota WHERE id_corte = ? AND duplicada_de IS NOT NULL', [$idCorte]);

        $oper = DB::selectOne(
            'SELECT operaciones = COUNT(*), cuotas = SUM(cuotas),
                    capital = SUM(capital_corriente + capital_vencido),
                    interes = SUM(interes_corriente + interes_vencido),
                    base = SUM(base_deterioro), deterioro = SUM(deterioro_contable),
                    prorroga = SUM(interes_prorroga_siesa)
             FROM det_deterioro_operacion WHERE id_corte = ?', [$idCorte]);

        // La prórroga vencida que entró a la base, medida por el camino
        // independiente: el snapshot de SIESA y no la columna que el enlace
        // escribió. Es el lado detalle que C-BASE le suma al detalle de cuotas
        // —donde la prórroga no existe, porque no viene de factoring— y el lado
        // resumen de C-SIESA-PRORROGA.
        //
        // Se restringe a las operaciones que existen en el corte: el snapshot
        // también atribuye filas a operaciones de fuera, que el enlace descarta
        // y que C-SIESA-NOTA publica aparte.
        $prorrogaSiesa = DB::selectOne(
            "SELECT valor = ISNULL(SUM(s.saldo), 0),
                    operaciones = COUNT(DISTINCT s.id_operacion_nota)
             FROM det_corte_saldo_siesa s
             WHERE s.id_corte = ? AND s.componente = 'PRORROGA'
               AND s.id_operacion_nota IS NOT NULL
               AND EXISTS (SELECT 1 FROM det_deterioro_operacion o
                           WHERE o.id_corte = s.id_corte
                             AND o.id_operacion = s.id_operacion_nota)", [$idCorte]);

        // Dos caminos independientes sobre la reducción de base que produjo el
        // congelamiento (D-06): uno parte de base_deterioro/base_congelada, el
        // otro de interes_vencido/interes_vencido_congelado. Tienen que dar lo
        // mismo porque el capital vencido no cambia; si el paso de suspensión
        // escribiera mal una de las dos columnas los caminos se separarían.
        //
        // Se restringe a origen_base = 'FACTORING' porque esa identidad sólo se
        // sostiene mientras el capital vencido sea el mismo de los dos lados.
        // Las suspendidas con base de SIESA las vigila C-SIESA-BASE.
        $suspension = DB::selectOne(
            "SELECT operaciones = SUM(CASE WHEN suspendida = 1 AND origen_base = 'FACTORING' THEN 1 ELSE 0 END),
                    suspendidas = SUM(CASE WHEN suspendida = 1 THEN 1 ELSE 0 END),
                    reduccion_base = SUM(CASE WHEN suspendida = 1 AND origen_base = 'FACTORING'
                        THEN base_deterioro - base_congelada ELSE 0 END)
             FROM det_deterioro_operacion WHERE id_corte = ?", [$idCorte]);

        $suspensionInteres = DB::selectOne(
            "SELECT reduccion_interes = SUM(CASE WHEN suspendida = 1 AND origen_base = 'FACTORING'
                        THEN interes_vencido - interes_vencido_congelado ELSE 0 END)
             FROM det_deterioro_operacion WHERE id_corte = ?", [$idCorte]);

        // D-15 por el camino contrario al del motor: éste suma la columna que
        // el motor escribió, aquél rearma la base desde sus dos componentes.
        $siesaBase = DB::selectOne(
            "SELECT operaciones = COUNT(*), base = ISNULL(SUM(base_congelada), 0),
                    componentes = ISNULL(SUM(ISNULL(capital_vencido_siesa, 0) + ISNULL(interes_vencido_siesa, 0)
                                             + ISNULL(interes_prorroga_siesa, 0)), 0)
             FROM det_deterioro_operacion WHERE id_corte = ? AND origen_base = 'SIESA'", [$idCorte]);

        // El saldo que el motor dejó en la operación contra el que el snapshot
        // tiene para esa misma operación. Son dos lados que se pueden separar
        // de verdad: si el enlace pierde una fila, si toma la equivocada, si el
        // snapshot cambia después de enlazar o si una fila deja de ser OPE, los
        // dos lados dejan de coincidir y el control lo dice.
        //
        // La versión anterior comparaba lo enlazado más lo no enlazado contra
        // el total del snapshot, y era una tautología: los dos sumandos son una
        // partición del mismo conjunto y su suma da el total para cualquier
        // contenido de la tabla. El único modo de falla que declaraba —que la
        // unión multiplicara filas— además lo impide la llave primaria de
        // det_deterioro_operacion.
        $snapshot = DB::selectOne(
            'SELECT filas = COUNT(*),
                    atribuidas = SUM(CASE WHEN id_operacion_nota IS NOT NULL THEN 1 ELSE 0 END)
             FROM det_corte_saldo_siesa WHERE id_corte = ?', [$idCorte]);

        $enlaceSiesa = DB::selectOne(
            'SELECT motor = ISNULL(SUM(saldo_siesa), 0),
                    operaciones = SUM(CASE WHEN saldo_siesa IS NOT NULL THEN 1 ELSE 0 END)
             FROM det_deterioro_operacion WHERE id_corte = ?', [$idCorte]);

        // Se agrupa antes de sumar por las dos vías, igual que hace el enlace:
        // hay consecutivos OPE repartidos entre más de un tercero, y una sola
        // operación reúne decenas de documentos CC atribuidos por nota. El
        // motor guarda la suma en la única fila de la operación.
        //
        // El lado del snapshot suma los OPE más el capital atribuido por nota a
        // operaciones que NO tienen OPE, que es exactamente la partición que
        // hace el COALESCE del enlace. Sumar los dos sin excluir daría de más
        // justamente en el caso que C-SIESA-DOBLE vigila.
        $snapshotEnlazado = DB::selectOne(
            "SELECT snapshot = ISNULL(SUM({$capitalSiesa}), 0)
             FROM (".self::sqlSaldoSiesaPorOperacion('?').") x
             INNER JOIN det_deterioro_operacion o
                     ON o.id_corte = ? AND o.id_operacion = x.id_operacion_nota
             LEFT JOIN ({$unicas}) u
                    ON u.id_operacion = o.id_operacion",
            [$idCorte, $idCorte, $idCorte]);

        // Invariante del que depende que no haya doble conteo: capital
        // atribuido por nota a una operación que ya cruza contra un OPE. CC y
        // OPE son esquemas sucesivos y hoy no se solapan en una sola operación;
        // si algún día lo hicieran, el COALESCE del enlace se queda con el OPE
        // y este control dice cuánto se dejó fuera, en vez de sumarlo callando.
        $siesaDoble = DB::selectOne(
            "SELECT valor = ISNULL(SUM(n.saldo), 0),
                    operaciones = COUNT(DISTINCT n.id_operacion_nota)
             FROM det_corte_saldo_siesa n
             WHERE n.id_corte = ? AND n.id_operacion_nota IS NOT NULL
               AND n.componente = 'CAPITAL' AND n.tipo_docto_cruce <> 'OPE'
               AND EXISTS (SELECT 1 FROM det_corte_saldo_siesa e
                           WHERE e.id_corte = n.id_corte
                             AND e.tipo_docto_cruce = 'OPE'
                             AND e.id_operacion_nota = n.id_operacion_nota)
               AND NOT EXISTS (SELECT 1 FROM ({$unicas}) u
                               WHERE u.id_operacion = n.id_operacion_nota)", [$idCorte, $idCorte]);

        // Cobertura del parseo: saldo de los prefijos que ninguna nota pudo
        // atribuir. Es informativo y no puede fallar, porque hay cartera en
        // SIESA que legítimamente no es de una operación de factoring —nóminas,
        // deterioros, ajustes—. Lo que importa es que la cifra se vea y que un
        // salto delate un cambio de redacción en el origen.
        // La cifra es el CONTEO de filas, no el saldo. El neto esconde que las
        // dos poblaciones tienen signo contrario —FEX en positivo, CC de ajuste
        // en negativo—, y el valor absoluto queda dominado por asientos
        // contables enormes que se compensan entre sí: 5.417 millones absolutos
        // contra 149 netos en 213 filas CC, con una sola de +794 millones. Ni
        // uno ni otro sirven de sensor. El conteo sí: una nota con redacción
        // nueva es una fila más sin atribuir, y los importes van al motivo.
        //
        // La segunda cifra son las filas que SÍ se atribuyeron pero a una
        // operación que no está en el corte: el enlace las descarta por el LEFT
        // JOIN, SOLO_SIESA no las ve porque sólo mira los OPE, y sin esto no
        // aparecerían en ningún control.
        $siesaNota = DB::selectOne(
            "SELECT neto = ISNULL(SUM(saldo), 0), filas = COUNT(*)
             FROM det_corte_saldo_siesa
             WHERE id_corte = ? AND tipo_docto_cruce <> 'OPE'
               AND id_operacion_nota IS NULL", [$idCorte]);

        $siesaHuerfana = DB::selectOne(
            "SELECT valor = ISNULL(SUM(s.saldo), 0), filas = COUNT(*),
                    operaciones = COUNT(DISTINCT s.id_operacion_nota)
             FROM det_corte_saldo_siesa s
             WHERE s.id_corte = ? AND s.id_operacion_nota IS NOT NULL
               AND s.tipo_docto_cruce <> 'OPE'
               AND NOT EXISTS (SELECT 1 FROM det_deterioro_operacion o
                               WHERE o.id_corte = s.id_corte
                                 AND o.id_operacion = s.id_operacion_nota)", [$idCorte]);

        // Acumulado declarado por encima del 100 % del valor nominal. Es el
        // precio explícito de que P salga de det_fiscal_acumulado y no se
        // recalcule: el módulo no corrige lo ya declarado, lo publica.
        $fiscalNominal = DB::selectOne(
            "SELECT valor = ISNULL(SUM(fiscal_acumulado_anterior - valor_nominal_siesa), 0),
                    operaciones = COUNT(*)
             FROM det_deterioro_operacion
             WHERE id_corte = ? AND valor_nominal_siesa IS NOT NULL
               AND fiscal_acumulado_anterior > valor_nominal_siesa", [$idCorte]);

        $conciliacion = DB::selectOne(
            "SELECT partidas = COUNT(*),
                    sin_explicar = ISNULL(SUM(CASE WHEN e.estado = 'EXPLICADA' THEN 0 ELSE 1 END), 0)
             FROM det_conciliacion_partida p
             LEFT JOIN det_conciliacion_explicacion e
                    ON e.id_corte = p.id_corte AND e.numero_operacion = p.numero_operacion
             WHERE p.id_corte = ?", [$idCorte]);

        // Cobertura de las marcas (C-5 del documento técnico): operaciones del
        // corte con marca aplicable que el paso de suspensión no pudo congelar
        // porque la marca no trae interes_congelado. Se deterioran con la base
        // de D-03 sin congelar, en contra de D-06, y sin este control el corte
        // cuadraría en verde omitiéndolas, que es el peor resultado posible.
        //
        // Se mide siempre, no colgado de que otro conteo sea cero: antes vivía
        // como rama informativa de C-SUSPENSION y sólo se disparaba cuando no
        // había ninguna suspendida con base de factoring, de modo que con 23
        // suspendidas las 114 sin congelar del corte de julio desaparecían.
        //
        // @corte se declara como date, no se compara con el parámetro crudo: la
        // conversión implícita de nvarchar a datetime (fecha_reactivacion) es
        // sensible al idioma del servidor, la de date a datetime no.
        $marcasSinCongelar = DB::selectOne(
            "DECLARE @corte date = ?;
             DECLARE @idCorte int = ?;
             SELECT aplicables = COUNT(*),
                    pendientes = SUM(CASE WHEN s.interes_congelado IS NULL THEN 1 ELSE 0 END),
                    base_pendiente = ISNULL(SUM(CASE WHEN s.interes_congelado IS NULL
                        THEN o.base_deterioro ELSE 0 END), 0),
                    deterioro_pendiente = ISNULL(SUM(CASE WHEN s.interes_congelado IS NULL
                        THEN o.deterioro_contable ELSE 0 END), 0)
             FROM det_suspension_interes s
             INNER JOIN det_deterioro_operacion o
                     ON o.id_corte = @idCorte AND o.id_operacion = s.id_operacion
             WHERE s.fecha_evento <= @corte
               AND (s.fecha_reactivacion IS NULL OR s.fecha_reactivacion > @corte)",
            [$fechaCorte, $idCorte]);

        // El exceso se mide contra el tope disponible con piso en cero: si el
        // acumulado anterior ya superó el saldo topado, lo deducible del año es
        // cero, no un negativo.
        $fiscal = DB::selectOne(
            "SELECT individual = SUM(deterioro_fiscal_individual),
                    acumulado = SUM(fiscal_acumulado_anterior),
                    exceso = SUM(CASE
                        WHEN deduccion_fiscal_ano > CASE WHEN saldo_topado - fiscal_acumulado_anterior > 0
                                                         THEN saldo_topado - fiscal_acumulado_anterior ELSE 0 END
                        THEN deduccion_fiscal_ano - CASE WHEN saldo_topado - fiscal_acumulado_anterior > 0
                                                         THEN saldo_topado - fiscal_acumulado_anterior ELSE 0 END
                        ELSE 0 END)
             FROM det_deterioro_operacion WHERE id_corte = ?", [$idCorte]);

        $fiscalResumen = DB::selectOne(self::sqlResumenFiscalIndependiente(), [$idCorte]);

        $fiscalFuente = DB::selectOne(
            'SELECT acumulado = ISNULL(SUM(fa.valor_deducido), 0)
             FROM det_fiscal_acumulado fa
             WHERE fa.ano_gravable < ?
               AND EXISTS (SELECT 1 FROM det_deterioro_operacion o
                           WHERE o.id_corte = ? AND o.id_operacion = fa.id_operacion)',
            [(int) date('Y', strtotime($fechaCorte)), $idCorte]);

        $diferido = DB::selectOne(
            'SELECT contable = SUM(deterioro_contable),
                    fiscal = SUM(deterioro_fiscal_acumulado),
                    temporaria = SUM(diferencia_temporaria),
                    impuesto = SUM(impuesto_diferido_activo),
                    sin_proyeccion = SUM(CASE WHEN ISNULL(base_congelada, base_deterioro) > 0
                                              AND estado_reversion = ?
                                         THEN 1 ELSE 0 END)
             FROM det_deterioro_operacion WHERE id_corte = ?', ['SIN_PROYECCION', $idCorte]);

        $tarifa = (float) DB::table('det_corte_param_convencion')
            ->where('id_corte', $idCorte)->value('tarifa_renta');

        $descomposicion = self::descomposicionMovimiento($idCorte);
        $corte = DB::table('det_corte')->where('id_corte', $idCorte)->first();
        $idCorteAnterior = $corte->id_corte_anterior;
        $variacion = DB::selectOne(self::sqlVariacionIndependiente(), [$idCorteAnterior, $idCorte]);
        $libro = self::validacionExcel($idCorte);

        // C-1 · las dos vías sobre las operaciones que bajaron de antigüedad.
        $prorroga = DB::selectOne(self::sqlControlProrroga(), [$idCorteAnterior, $idCorte]);

        // C-2 · bajas del período y cuántas siguen sin clasificar. La cifra del
        // control es el conteo y no el deterioro: una baja de cero pesos sin
        // clasificar también deja el corte sin poder cerrarse, y con el importe
        // pasaría inadvertida.
        $salidas = DB::selectOne(
            "SELECT bajas = COUNT(*),
                    sin_clasificar = ISNULL(SUM(CASE WHEN s.id_operacion IS NULL THEN 1 ELSE 0 END), 0),
                    deterioro_sin_clasificar = ISNULL(SUM(CASE WHEN s.id_operacion IS NULL
                        THEN a.deterioro_contable ELSE 0 END), 0)
             FROM det_corte c
             INNER JOIN det_deterioro_operacion a ON a.id_corte = c.id_corte_anterior
             LEFT JOIN det_salida_operacion s
                    ON s.id_corte = c.id_corte AND s.id_operacion = a.id_operacion
             WHERE c.id_corte = ?
               AND NOT EXISTS (SELECT 1 FROM det_deterioro_operacion o
                               WHERE o.id_corte = c.id_corte AND o.id_operacion = a.id_operacion)",
            [$idCorte]);

        // Lo que el cierre de diciembre escribió en det_fiscal_acumulado contra
        // la deducción del año que el corte calculó. Es el control del riesgo
        // catalogado en §21: un acumulado inflado por una escritura doble se
        // arrastra por años, y aquí se ve el mismo año.
        $cierreFiscal = DB::selectOne(
            'SELECT escrito = ISNULL((SELECT SUM(fa.valor_deducido) FROM det_fiscal_acumulado fa
                        WHERE fa.ano_gravable = ? AND fa.id_corte_origen = ? AND fa.origen = ?), 0),
                    esperado = ISNULL((SELECT SUM(o.deduccion_fiscal_ano) FROM det_deterioro_operacion o
                        WHERE o.id_corte = ? AND o.deduccion_fiscal_ano > 0), 0)',
            [(int) date('Y', strtotime($fechaCorte)), $idCorte, self::ORIGEN_ACUMULADO_CIERRE, $idCorte]);

        // Motivo por el que un control no aplica; nulo significa que sí aplica.
        $sinFase2 = DB::table('det_corte_param_fiscal')->where('id_corte', $idCorte)->exists()
            ? null : 'el corte no tiene paramétrica fiscal congelada';
        $sinFase3 = $diferido->fiscal === null
            ? 'el corte no tiene calculado el comparativo contable contra fiscal' : null;
        $sinAnterior = $idCorteAnterior
            ? null : 'es el primer corte de la serie y no hay corte anterior con qué comparar';

        // N/A y no OK con cero: un cero ahí significaría "cuadra" cuando en
        // realidad no hay nada que cuadrar. Si además hay marcas aplicables
        // sin congelar, el motivo lo dice y queda visible con sus cifras
        // (0 = 0) en vez de perderse detrás del N/A genérico.
        $sinSuspension = null;
        if ((int) $suspension->operaciones === 0) {
            $sinSuspension = (int) $suspension->suspendidas > 0
                ? 'las ' . $suspension->suspendidas
                    . ' operación(es) suspendida(s) del corte tomaron su base del saldo de SIESA y las vigila C-SIESA-BASE'
                : 'el corte no tiene operaciones con suspensión de intereses';
        }

        // Sin cuotas repetidas no hay nada excluido que declarar, y un cero ahí
        // diría "se excluyeron cero" cuando lo cierto es que no hubo ninguna.
        $sinDuplicadas = (int) $duplicadas->filas === 0
            ? 'el origen no entregó cuotas repetidas en este corte' : null;

        // Cuántas operaciones concentran lo excluido: cuatro filas en una sola
        // operación y cuatro repartidas en cuatro no se miran igual, y la cifra
        // del control es el conteo de filas.
        $motivoDuplicadas = $sinDuplicadas ?: 'el duplicado lo entrega el origen, en '
            . $duplicadas->operaciones . ' operación(es), y la cifra es lo que el módulo dejó fuera del cálculo, no un descuadre';

        // Sin marcas aplicables no hay cobertura que medir; con ellas el
        // control compara contra cero y falla si alguna quedó sin congelar.
        $sinMarcas = (int) $marcasSinCongelar->aplicables === 0
            ? 'el corte no tiene marcas de suspensión aplicables a su fecha' : null;

        // Los tres controles de la fase 6 siguen la misma regla: sin insumo van
        // a N/A con el motivo, nunca a OK con cero.
        $sinSiesaBase = (int) $siesaBase->operaciones === 0
            ? 'el corte no tiene operaciones suspendidas con la base tomada de SIESA' : null;
        $sinSnapshot = (int) $snapshot->filas === 0 || (int) $enlaceSiesa->operaciones === 0
            ? 'el corte no tiene extraído el saldo de SIESA o ninguna operación cruzó contra un OPE' : null;
        $sinPartidas = (int) $conciliacion->partidas === 0
            ? 'el corte no tiene generadas las partidas de conciliación con SIESA' : null;

        // Los tres controles de la atribución por notas necesitan que el
        // snapshot del corte la tenga poblada. Un corte extraído antes del 18 de
        // septiembre de 2026 —el de julio, que está cerrado y no se recalcula—
        // conserva su snapshot sin atribuir, y sobre él estos controles no
        // miden nada: van a N/A con motivo, no a OK con cero ni a falla.
        $sinAtribucion = (int) $snapshot->atribuidas === 0
            ? 'el snapshot de SIESA de este corte se extrajo antes de la atribución por notas' : null;
        // Los dos informativos llevan motivo SIEMPRE, igual que C-DUPLICADAS:
        // así el estado es N/A y la cifra se lee como lo que es —cobertura y
        // exceso declarado— en vez de como un descuadre contra cero. Pasarlos
        // como valor contra sí mismos los dejaba en OK con diferencia cero, que
        // es justo esconder el dato que el control existe para publicar.
        // El motivo cabe en 200 caracteres: la columna los tiene y un texto más
        // largo aborta el cuadre entero al insertar.
        $motivoSiesaNota = $sinAtribucion ?: 'cobertura del parseo, no un descuadre: la cifra son filas, de saldo neto '
            . number_format($siesaNota->neto, 0, ',', '.') . '; más ' . $siesaHuerfana->filas
            . ' atribuida(s) a ' . $siesaHuerfana->operaciones . ' operación(es) fuera del corte por '
            . number_format($siesaHuerfana->valor, 0, ',', '.');
        // El SUM de la columna se deja sin ISNULL y su nulo se atiende aquí: es
        // lo que dice que el corte se calculó antes de que la prórroga entrara
        // en la base. Aplanado a cero, el control compararía un cero fabricado
        // contra la cifra real del snapshot y saldría en FALLA por una
        // diferencia que no es un descuadre sino un corte viejo.
        //
        // Y la población puede estar legítimamente vacía —un corte sin prórroga
        // vencida en SIESA—, que en cero saldría en verde diciendo que cuadra
        // algo que no existe. Se exige que los dos lados estén en cero: si el
        // motor llevó prórroga a la base y el snapshot no la tiene, eso sí es el
        // descuadre que el control existe para ver.
        $sinProrrogaSiesa = $sinAtribucion ?: ($oper->prorroga === null
            ? 'este corte se calculó antes de que la prórroga vencida entrara en la base de deterioro'
            : ((int) $prorrogaSiesa->operaciones === 0 && (float) $oper->prorroga == 0.0
                ? 'ninguna operación del corte tiene saldo de prórroga vencida en SIESA' : null));
        $motivoFiscalNominal = $sinAtribucion ?: ((int) $fiscalNominal->operaciones === 0
            ? 'ninguna operación del corte tiene acumulado declarado por encima de su valor nominal en SIESA'
            : 'el módulo no corrige el acumulado ya declarado: la cifra es lo que excede el valor nominal en '
              . $fiscalNominal->operaciones . ' operación(es)');

        // Los tres de la fase 7 siguen la misma regla. La población de C-1 y
        // C-2 puede estar legítimamente vacía —un mes sin prórrogas, un mes sin
        // bajas—, y en cero saldrían en verde diciendo que cuadran algo que no
        // existe.
        $sinProrrogas = $sinAnterior ?: ((int) $prorroga->operaciones === 0
            ? 'ninguna operación del corte bajó su antigüedad de mora respecto al corte anterior' : null);
        $sinBajas = $sinAnterior ?: ((int) $salidas->bajas === 0
            ? 'ninguna operación del corte anterior salió de la base en este corte' : null);

        // El acumulado fiscal del año gravable lo escribe el cierre de
        // diciembre: antes de cerrarlo no hay nada que comparar, y en un corte
        // que no es de diciembre no lo habrá nunca.
        $sinCierreFiscal = null;
        if ((int) date('n', strtotime($fechaCorte)) !== 12) {
            $sinCierreFiscal = 'el corte no es de diciembre y no alimenta el acumulado del año gravable';
        } elseif ($corte->estado !== self::ESTADO_CERRADO) {
            $sinCierreFiscal = 'el corte de diciembre todavía no está cerrado y no ha escrito el acumulado del año gravable';
        }

        // Los cinco controles cuya cifra es un conteo —C-CUOTAS, C-DUPLICADAS,
        // C-CONCILIA, C-SALIDAS y C-REVERSION— llevan tolerancia cero. La de un peso viene
        // del criterio de aceptación de §20 y sólo tiene sentido donde la cifra
        // son pesos; donde son unidades, un peso es una cuota, una partida, una
        // baja o una operación entera pasando en verde.
        //
        // C-DUPLICADAS-BASE también lleva cero aunque su cifra sean pesos, y es
        // la excepción que precisa la regla: el peso de §20 absorbe el redondeo
        // de dos caminos de cálculo, no una cantidad que debe ser cero.
        $controles = [
            ['C-CUOTAS', 'Cuotas del detalle contra la suma consolidada por operación',
                $origen->filas, $oper->cuotas, null, false, 0],
            ['C-CAPITAL', 'Capital del detalle contra el consolidado por operación',
                $origen->capital, $oper->capital],
            ['C-INTERES', 'Interés del detalle contra el consolidado por operación',
                $origen->interes, $oper->interes],
            ['C-PARTIC', 'Capital corriente más vencido contra el capital total',
                $origen->capital, $origen->corriente + $origen->vencido],
            ['C-BASE', 'Base de deterioro contra capital vencido más interés vencido más prórroga vencida de SIESA',
                $oper->base, $origen->vencido + $origen->ivencido + $prorrogaSiesa->valor],
            // Cuánto dejó fuera del cálculo la marca de cuotas repetidas.
            // Informativo y nunca en falla: es una cifra que hay que poder ver
            // y explicar, no un descuadre. Su presencia en det_corte_cuadre es
            // además lo que distingue un corte con la marca evaluada de uno
            // calculado antes de que existiera.
            ['C-DUPLICADAS', 'Cuotas repetidas por el origen que quedaron fuera del cálculo',
                $duplicadas->filas, 0, $motivoDuplicadas, !$sinDuplicadas, 0],
            // Y cuánta base de deterioro se fue con ellas. Éste sí falla, y su
            // falla bloquea el cierre por la vía de CUADRE_EN_FALLA: excluir
            // una cuota vencida cambiaría el deterioro del corte, y eso no
            // puede pasar sin que alguien lo mire.
            //
            // Tolerancia cero aunque la cifra sean pesos: no es la diferencia
            // entre dos caminos de cálculo, donde el peso de §20 absorbe el
            // redondeo, sino una cantidad que tiene que ser cero. Con un peso de
            // margen, una cuota repetida que aportara exactamente un peso de
            // base pasaría en verde, que es lo contrario de para lo que existe.
            ['C-DUPLICADAS-BASE', 'Base de deterioro de las cuotas repetidas que quedaron fuera del cálculo',
                $duplicadas->base, 0, $sinDuplicadas, false, 0],
            ['C-SUSPENSION', 'Reducción de base por congelamiento de intereses contra la reducción de interés vencido de las mismas operaciones',
                $suspension->reduccion_base, $suspensionInteres->reduccion_interes, $sinSuspension],
            // La cifra es la base que se está deteriorando sin congelar, no el
            // conteo: es la magnitud contable del faltante.
            ['C-MARCAS', 'Base de las operaciones con marca de suspensión aplicable que no se pudieron congelar por no traer interés congelado',
                $marcasSinCongelar->base_pendiente, 0, $sinMarcas],
            ['C-SIESA-BASE', 'Base congelada de las suspendidas con base de SIESA contra capital vencido más interés vencido más prórroga vencida de SIESA',
                $siesaBase->base, $siesaBase->componentes, $sinSiesaBase],
            ['C-SIESA-EXTRAC', 'Saldo de SIESA que el motor dejó en las operaciones contra el que el snapshot tiene para esas mismas operaciones',
                $enlaceSiesa->motor, $snapshotEnlazado->snapshot, $sinSnapshot],
            // Invariante de la atribución por notas: capital contado dos veces.
            ['C-SIESA-DOBLE', 'Capital atribuido por nota a operaciones que ya cruzan contra un documento OPE de SIESA',
                $siesaDoble->valor, 0, $sinAtribucion, false, 0],
            // Cobertura del parseo, informativo: hay cartera en SIESA que no es
            // de ninguna operación de factoring y su saldo no es un descuadre.
            ['C-SIESA-NOTA', 'Filas de los prefijos de SIESA que ninguna nota pudo atribuir a una operación',
                $siesaNota->filas, 0, $motivoSiesaNota, true, 0],
            // Cuánta prórroga vencida de SIESA entró a la base de deterioro: la
            // columna que el enlace escribió contra la suma del snapshot, que es
            // el mismo lado con que C-BASE completa el detalle de cuotas.
            ['C-SIESA-PRORROGA', 'Prórroga vencida de SIESA que entró en la base de deterioro contra la del snapshot para esas mismas operaciones',
                $oper->prorroga, $prorrogaSiesa->valor, $sinProrrogaSiesa],
            // Informativo: el acumulado declarado que excede el valor nominal no
            // lo corrige el módulo, porque P es lo declarado ante la DIAN.
            ['C-FISCAL-NOMINAL', 'Acumulado fiscal de años anteriores por encima del 100 % del valor nominal en SIESA',
                $fiscalNominal->valor, 0, $motivoFiscalNominal, true],
            // Activo desde la fase 7a: una partida sin explicar impide cerrar
            // el corte, así que deja de ser informativo y pasa a poder fallar.
            ['C-CONCILIA', 'Partidas de conciliación con SIESA sin explicar',
                $conciliacion->sin_explicar, 0, $sinPartidas, false, 0],
            // C-1 · dos vías sobre el deterioro liberado por las operaciones
            // cuya antigüedad de mora bajó respecto al corte anterior (D-08).
            ['C-PRORROGA', 'Deterioro liberado por las operaciones que bajaron de antigüedad contra el recalculado contra el corte anterior',
                $prorroga->motor, $prorroga->recalculado, $sinProrrogas],
            // C-2 · una baja sin clasificar impide cerrar el corte.
            ['C-SALIDAS', 'Operaciones que salieron de la base entre cortes y siguen sin clasificar',
                $salidas->sin_clasificar, 0, $sinBajas, false, 0],
            // El acumulado que el cierre de diciembre escribió contra la
            // deducción del año que el corte calculó.
            ['C-CIERRE-FISCAL', 'Acumulado fiscal escrito por el cierre de diciembre contra la deducción del año del corte',
                $cierreFiscal->escrito, $cierreFiscal->esperado, $sinCierreFiscal],
            ['C-FISCAL', 'Deterioro fiscal individual del detalle contra la base de los rangos que deducen por el porcentaje anual',
                $fiscal->individual, $fiscalResumen->individual, $sinFase2],
            ['C-FISCAL-ACUM', 'Acumulado fiscal del detalle contra los años anteriores del acumulado',
                $fiscal->acumulado, $fiscalFuente->acumulado, $sinFase2],
            ['C-FISCAL-TOPE', 'Deducción del año por encima del tope disponible',
                $fiscal->exceso, 0, $sinFase2],
            // SUM ignora los nulos y el ISNULL fila a fila no: si alguna
            // operación quedó sin comparativo, los dos lados se separan.
            ['C-DIF-TEMP', 'Diferencia temporaria del detalle contra contable menos fiscal acumulado',
                $diferido->temporaria, $diferido->contable - $diferido->fiscal, $sinFase3],
            // El motor multiplica y luego suma; el control suma y luego
            // multiplica: son dos caminos, no la misma cuenta escrita dos veces.
            ['C-DIFERIDO', 'Impuesto diferido del detalle contra la diferencia temporaria por la tarifa de renta',
                $diferido->impuesto, $diferido->temporaria * $tarifa, $sinFase3],
            // Cuenta SIN_PROYECCION y no el nulo del año: desde que una
            // operación con el tope agotado deja de proyectar, el nulo dejó de
            // significar una sola cosa y contarlo pondría el control en falla
            // por las que están bien.
            ['C-REVERSION', 'Operaciones con base de deterioro que quedan sin proyección de año de reversión',
                $diferido->sin_proyeccion, 0, $sinFase3, false, 0],
            // La ecuación del período contra el saldo del corte: anterior más
            // altas más variación menos bajas tiene que dar el deterioro actual.
            ['C-MOVIMIENTO', 'Descomposición del movimiento del mes contra el deterioro contable del corte',
                $descomposicion->control, $oper->deterioro, $sinAnterior],
            // El motor clasifica y guarda; el control vuelve a restar operación
            // por operación contra el corte anterior sin usar lo clasificado.
            ['C-VARIACION', 'Altas más variación de las que continúan contra la variación recalculada contra el corte anterior',
                $descomposicion->altas + $descomposicion->variacion, $variacion->variacion, $sinAnterior],
            // Informativo y nunca en falla: las dos cifras se calculan de forma
            // distinta y que difieran no dice que el módulo esté mal.
            ['C-LIBRO', 'Gasto del período del módulo contra el del libro',
                $descomposicion->gasto, isset($libro['F10_TOTAL']) ? $libro['F10_TOTAL'] : null,
                $sinAnterior ?: (isset($libro['F10_TOTAL'])
                    ? 'la cifra del mes anterior del libro se digita a mano y el origen de junio está pendiente de confirmar'
                    : 'el corte no tiene cargadas las cifras del libro en det_validacion_excel'),
                !$sinAnterior && isset($libro['F10_TOTAL'])],
        ];

        DB::table('det_corte_cuadre')->where('id_corte', $idCorte)->delete();
        $resultado = [];
        foreach ($controles as $c) {
            $motivo = isset($c[4]) ? $c[4] : null;
            // El informativo también queda en N/A, pero conserva sus cifras:
            // la fila existe justo para mostrar la diferencia, no para juzgarla.
            $informativo = isset($c[5]) && $c[5];
            $muestra = !$motivo || $informativo;
            $diferencia = $muestra ? round((float) $c[2] - (float) $c[3], 4) : null;
            // Un peso por defecto; cero en los que cuentan unidades.
            $tolerancia = isset($c[6]) ? $c[6] : 1;
            $fila = [
                'id_corte' => $idCorte,
                'codigo' => $c[0],
                'descripcion' => $c[1],
                'motivo' => $motivo,
                'informativo' => $informativo,
                'valor_detalle' => $muestra ? $c[2] : null,
                'valor_resumen' => $muestra ? $c[3] : null,
                'diferencia' => $diferencia,
                'tolerancia' => $tolerancia,
                'estado' => $motivo ? 'N/A' : (abs($diferencia) <= $tolerancia ? 'OK' : 'FALLA'),
            ];
            DB::table('det_corte_cuadre')->insert($fila);
            $resultado[] = $fila;
        }
        return $resultado;
    }

    /* ---------------------------------------------------------------------
     | Orquestación
     |-------------------------------------------------------------------- */

    /**
     * Ejecuta el pipeline completo. Reejecutable mientras el corte esté
     * abierto: limpia y rehace desde el principio.
     */
    public static function ejecutar($idCorte, $idUsuario, $tablaOrigen = null)
    {
        $tablaOrigen = $tablaOrigen ?: self::origenCorte();
        $corte = DB::table('det_corte')->where('id_corte', $idCorte)->first();
        if (!$corte) {
            throw new \RuntimeException("El corte $idCorte no existe.");
        }
        if ($corte->estado === self::ESTADO_CERRADO) {
            throw new \RuntimeException('El corte está cerrado y no se puede recalcular.');
        }

        $validacion = self::validarPeriodoOrigen($corte->fecha_corte, $tablaOrigen);
        if (!$validacion['ok']) {
            throw new \RuntimeException($validacion['mensaje']);
        }

        $inicio = microtime(true);
        $pasos = [];

        DB::transaction(function () use ($corte, $idCorte, $tablaOrigen, &$pasos) {
            $t = microtime(true);
            self::limpiarCorte($idCorte);
            $pasos[] = ['paso' => 'Limpieza', 'filas' => null, 'ms' => self::ms($t)];

            $t = microtime(true);
            $hash = self::congelarParametros($idCorte, $corte->fecha_corte);
            self::validarMetodoFiscal($idCorte);
            self::validarTarifaRenta($idCorte);
            $pasos[] = ['paso' => 'Congelar paramétricas', 'filas' => null, 'ms' => self::ms($t)];

            $t = microtime(true);
            $filas = self::extraerCartera($idCorte, $tablaOrigen);
            $pasos[] = ['paso' => 'Extracción de cartera', 'filas' => $filas, 'ms' => self::ms($t)];

            // Va aquí y no después: el criterio sólo lee columnas del origen,
            // de modo que la marca es una lectura de lo que entregó factoring y
            // no un resultado del cálculo. Todo lo que viene después —derivadas
            // incluidas— ya sabe cuáles cuotas cuentan.
            $t = microtime(true);
            $duplicadas = self::marcarDuplicadas($idCorte);
            $pasos[] = ['paso' => 'Cuotas repetidas del origen', 'filas' => $duplicadas, 'ms' => self::ms($t)];

            $t = microtime(true);
            self::calcularDerivadas($idCorte, $corte->fecha_corte);
            $pasos[] = ['paso' => 'Derivadas por cuota', 'filas' => $filas, 'ms' => self::ms($t)];

            $t = microtime(true);
            $enlazadas = self::enlazarMesAnterior($idCorte, $corte->id_corte_anterior);
            $pasos[] = ['paso' => 'Enlace con el corte anterior', 'filas' => $enlazadas, 'ms' => self::ms($t)];

            $t = microtime(true);
            $operaciones = self::consolidarPorOperacion($idCorte);
            $pasos[] = ['paso' => 'Consolidación por operación', 'filas' => $operaciones, 'ms' => self::ms($t)];

            // Va antes del cálculo: valor_nominal_siesa alimenta el tope fiscal
            // R del deterioro fiscal y saldo_siesa la conciliación, y las dos
            // tienen que estar ya en la fila. La base ya no depende de SIESA.
            $t = microtime(true);
            $saldosSiesa = self::extraerSaldoSiesa($idCorte, $corte->fecha_corte);
            self::enlazarSaldoSiesa($idCorte);
            $pasos[] = ['paso' => 'Extracción de SIESA', 'filas' => $saldosSiesa, 'ms' => self::ms($t)];

            $t = microtime(true);
            $suspendidas = self::aplicarSuspensiones($idCorte, $corte->fecha_corte);
            $pasos[] = ['paso' => 'Suspensiones', 'filas' => $suspendidas, 'ms' => self::ms($t)];

            $t = microtime(true);
            self::aplicarDeterioroContable($idCorte);
            $pasos[] = ['paso' => 'Deterioro contable', 'filas' => $operaciones, 'ms' => self::ms($t)];

            $t = microtime(true);
            self::aplicarDeterioroFiscal($idCorte, $corte->fecha_corte);
            $pasos[] = ['paso' => 'Deterioro fiscal y tope', 'filas' => $operaciones, 'ms' => self::ms($t)];

            $t = microtime(true);
            self::aplicarImpuestoDiferido($idCorte, $corte->fecha_corte);
            $pasos[] = ['paso' => 'Impuesto diferido', 'filas' => $operaciones, 'ms' => self::ms($t)];

            $t = microtime(true);
            $movidas = self::aplicarMovimientoMes($idCorte, $corte->id_corte_anterior);
            $pasos[] = ['paso' => 'Movimiento del mes', 'filas' => $movidas, 'ms' => self::ms($t)];

            $t = microtime(true);
            $partidas = self::generarConciliacion($idCorte);
            $pasos[] = ['paso' => 'Conciliación con SIESA', 'filas' => $partidas, 'ms' => self::ms($t)];

            // Cuenta TODAS las filas, las repetidas incluidas: filas_origen, las
            // dos sumas y el hash son la huella de lo que entregó el origen, no
            // un resultado del cálculo. Descontar aquí lo excluido volvería la
            // huella irreproducible contra ResumenVigentesClientes, que es
            // justo para lo que sirve.
            $totales = DB::selectOne(
                'SELECT capital = SUM(saldo_capital), interes = SUM(saldo_intereses)
                 FROM det_corte_detalle_cuota WHERE id_corte = ?', [$idCorte]);

            DB::table('det_corte')->where('id_corte', $idCorte)->update([
                'estado' => self::ESTADO_CALCULADO,
                'fecha_ejecucion' => self::ahora(),
                'filas_origen' => $filas,
                'suma_capital_origen' => $totales->capital,
                'suma_interes_origen' => $totales->interes,
                'hash_datos' => hash('sha256', $filas . '|' . $totales->capital . '|' . $totales->interes),
                'hash_parametros' => $hash,
            ]);
        });

        $t = microtime(true);
        $cuadres = self::verificarCuadres($idCorte);
        $pasos[] = ['paso' => 'Cuadres', 'filas' => count($cuadres), 'ms' => self::ms($t)];

        $duracion = self::ms($inicio);
        DB::table('det_corte')->where('id_corte', $idCorte)->update(['duracion_ms' => $duracion]);

        self::registrarBitacora('EJECUTAR_CORTE', $idCorte, null, null,
            "duración {$duracion} ms", $idUsuario);

        return ['duracion_ms' => $duracion, 'pasos' => $pasos, 'cuadres' => $cuadres];
    }

    /* ---------------------------------------------------------------------
     | Cierre y reapertura del corte (§8 principio 1, §18)
     |-------------------------------------------------------------------- */

    /**
     * Lo que impide cerrar el corte, enumerado y contable.
     *
     * Devuelve conteo y cifra por tipo, no una frase: el usuario tiene que
     * saber cuántas partidas le faltan y de qué clase para poder terminarlas, y
     * un mensaje de texto no se puede contar ni congelar. La misma estructura
     * es la que se guarda en foto_salvedad cuando el cierre se fuerza, y por eso
     * lleva las cifras dentro y no una referencia a dónde consultarlas: un año
     * después las tablas ya no darán estos números.
     *
     * Los tres bloqueos son los de §15 y §16: un cuadre en FALLA, una partida de
     * conciliación sin explicar (C-3) y una baja del período sin clasificar
     * (C-2). C-4 y C-5 no entran: dependen de definiciones que no han llegado.
     */
    public static function condicionesCierre($idCorte)
    {
        $bloqueos = [];

        $fallas = DB::select(
            "SELECT codigo, descripcion, valor_detalle, valor_resumen, diferencia
             FROM det_corte_cuadre
             WHERE id_corte = ? AND estado = 'FALLA' ORDER BY codigo", [$idCorte]);

        if ($fallas) {
            $suma = 0;
            $detalle = [];
            foreach ($fallas as $f) {
                $suma += abs((float) $f->diferencia);
                $detalle[] = [
                    'codigo' => $f->codigo,
                    'descripcion' => $f->descripcion,
                    'valor_detalle' => (float) $f->valor_detalle,
                    'valor_resumen' => (float) $f->valor_resumen,
                    'diferencia' => (float) $f->diferencia,
                ];
            }
            $bloqueos[] = [
                'tipo' => 'CUADRE_EN_FALLA',
                'concepto' => 'Controles de cuadre en falla',
                'cantidad' => count($fallas),
                'unidad' => 'control',
                'valor' => $suma,
                'detalle' => $detalle,
            ];
        }

        $partidas = DB::selectOne(
            "SELECT partidas = COUNT(*), valor = ISNULL(SUM(ABS(p.diferencia)), 0)
             FROM det_conciliacion_partida p
             LEFT JOIN det_conciliacion_explicacion e
                    ON e.id_corte = p.id_corte AND e.numero_operacion = p.numero_operacion
             WHERE p.id_corte = ? AND ISNULL(e.estado, 'PENDIENTE') <> 'EXPLICADA'", [$idCorte]);

        if ((int) $partidas->partidas > 0) {
            // Por tipo, porque una partida SOLO_SIESA y una DIFERENCIA no son el
            // mismo trabajo ni la misma gravedad.
            $porTipo = DB::select(
                "SELECT p.tipo, partidas = COUNT(*), valor = ISNULL(SUM(ABS(p.diferencia)), 0)
                 FROM det_conciliacion_partida p
                 LEFT JOIN det_conciliacion_explicacion e
                        ON e.id_corte = p.id_corte AND e.numero_operacion = p.numero_operacion
                 WHERE p.id_corte = ? AND ISNULL(e.estado, 'PENDIENTE') <> 'EXPLICADA'
                 GROUP BY p.tipo ORDER BY p.tipo", [$idCorte]);

            $detalle = [];
            foreach ($porTipo as $t) {
                $detalle[] = ['tipo' => $t->tipo, 'cantidad' => (int) $t->partidas,
                    'valor' => (float) $t->valor];
            }
            $bloqueos[] = [
                'tipo' => 'CONCILIACION_SIN_EXPLICAR',
                'concepto' => 'Partidas de conciliación con SIESA sin explicar',
                'cantidad' => (int) $partidas->partidas,
                'unidad' => 'partida',
                'valor' => (float) $partidas->valor,
                'detalle' => $detalle,
            ];
        }

        $bajas = DB::selectOne(
            "SELECT bajas = COUNT(*), valor = ISNULL(SUM(a.deterioro_contable), 0)
             FROM det_corte c
             INNER JOIN det_deterioro_operacion a ON a.id_corte = c.id_corte_anterior
             WHERE c.id_corte = ?
               AND NOT EXISTS (SELECT 1 FROM det_deterioro_operacion o
                               WHERE o.id_corte = c.id_corte AND o.id_operacion = a.id_operacion)
               AND NOT EXISTS (SELECT 1 FROM det_salida_operacion s
                               WHERE s.id_corte = c.id_corte AND s.id_operacion = a.id_operacion)",
            [$idCorte]);

        if ((int) $bajas->bajas > 0) {
            $bloqueos[] = [
                'tipo' => 'BAJA_SIN_CLASIFICAR',
                'concepto' => 'Operaciones que salieron de la base y siguen sin clasificar',
                'cantidad' => (int) $bajas->bajas,
                'unidad' => 'operación',
                'valor' => (float) $bajas->valor,
                'detalle' => [],
            ];
        }

        return ['puede' => empty($bloqueos), 'bloqueos' => $bloqueos];
    }

    /**
     * Cierra el corte. Con condiciones que bloquean, sólo cierra si viene
     * motivo de salvedad, y entonces el corte queda marcado como cerrado con
     * salvedades de forma permanente.
     *
     * La regla es "bloquea, con excepción registrada": un control en falla o una
     * partida sin explicar impiden cerrar, pero el mes contable no se puede
     * quedar sin cerrar indefinidamente porque una diferencia no se resolvió a
     * tiempo. El escape existe y deja rastro; lo que no puede pasar es que un
     * cierre forzado se vea igual que uno limpio, porque entonces el módulo
     * mentiría sobre su propio estado.
     *
     * foto_salvedad congela la enumeración completa —qué controles, con qué
     * cifras, cuántas partidas y de qué tipo— dentro del corte. Es la única
     * forma de que dentro de un año se pueda reconstruir por qué se cerró así:
     * las tablas de origen ya no darán esos números.
     *
     * Con el corte de diciembre, el cierre alimenta además det_fiscal_acumulado
     * con la deducción del año gravable, que es el insumo de P en RN-09 para los
     * años siguientes. Todo va en la misma transacción: un cierre a medias
     * dejaría el acumulado escrito y el corte abierto, y la siguiente corrida lo
     * escribiría otra vez.
     */
    public static function cerrarCorte($idCorte, $idUsuario, $motivoSalvedad = null)
    {
        $corte = DB::table('det_corte')->where('id_corte', $idCorte)->first();
        if (!$corte) {
            return ['ok' => false, 'mensaje' => "El corte $idCorte no existe."];
        }
        if ($corte->estado === self::ESTADO_CERRADO) {
            return ['ok' => false, 'mensaje' => 'El corte ya está cerrado.'];
        }
        if ($corte->estado !== self::ESTADO_CALCULADO) {
            return ['ok' => false, 'mensaje' =>
                'El corte está en estado '.$corte->estado.': hay que calcularlo antes de cerrarlo.'];
        }

        $condiciones = self::condicionesCierre($idCorte);
        $motivoSalvedad = trim((string) $motivoSalvedad);

        if (!$condiciones['puede'] && $motivoSalvedad === '') {
            return ['ok' => false, 'bloqueos' => $condiciones['bloqueos'], 'mensaje' =>
                'El corte tiene condiciones pendientes que impiden cerrarlo.'];
        }
        if (mb_strlen($motivoSalvedad) > 500) {
            return ['ok' => false, 'mensaje' => 'El motivo de la salvedad no puede superar los 500 caracteres.'];
        }

        // Un motivo sobre un corte sin bloqueos no fabrica una salvedad: el
        // corte está limpio y marcarlo como forzado sería una falsedad al revés.
        $conSalvedad = !$condiciones['puede'];
        $foto = $conSalvedad ? json_encode([
            'fecha' => self::ahora(),
            'id_usuario' => $idUsuario,
            // El nombre se congela dentro de la foto y no se resuelve al leer:
            // se consulta años después, cuando el id puede ya no resolver a nada.
            'usuario' => DB::table('users')->where('idUsuario', $idUsuario)->value('nombreUsuario'),
            'motivo' => $motivoSalvedad,
            'bloqueos' => $condiciones['bloqueos'],
        ], JSON_UNESCAPED_UNICODE) : null;

        $acumulado = null;
        $esDiciembre = (int) date('n', strtotime($corte->fecha_corte)) === 12;

        DB::transaction(function () use ($idCorte, $idUsuario, $corte, $conSalvedad,
                                         $motivoSalvedad, $foto, $esDiciembre, &$acumulado) {
            DB::table('det_corte')->where('id_corte', $idCorte)->update([
                'estado' => self::ESTADO_CERRADO,
                'fecha_cierre' => self::ahora(),
                'id_usuario_cierre' => $idUsuario,
                'cerrado_con_salvedad' => $conSalvedad ? 1 : 0,
                'motivo_salvedad' => $conSalvedad ? $motivoSalvedad : null,
                'foto_salvedad' => $foto,
            ]);

            if (!$esDiciembre) {
                return;
            }

            $acumulado = self::persistirAcumuladoFiscal($idCorte, $corte->fecha_corte, $idUsuario);

            // El cuadre se rehace con el corte ya cerrado para que
            // C-CIERRE-FISCAL deje de estar en N/A y compare lo escrito contra
            // lo esperado. Si no cuadra, el cierre entero se revierte: es
            // preferible un corte sin cerrar a un acumulado fiscal torcido, que
            // según §21 se arrastra por años.
            foreach (self::verificarCuadres($idCorte) as $c) {
                if ($c['codigo'] === 'C-CIERRE-FISCAL' && $c['estado'] === 'FALLA') {
                    throw new \RuntimeException(
                        'El acumulado fiscal escrito por el cierre no cuadra con la deducción del año del corte '
                        . '(diferencia '.$c['diferencia'].'): el cierre se revirtió. '
                        . 'Revise si otro origen ya escribió el año gravable '.$acumulado['ano'].'.');
                }
            }
        });

        self::registrarBitacora('CERRAR_CORTE', $idCorte, null, $corte->estado,
            ($conSalvedad ? 'CERRADO CON SALVEDAD: '.$motivoSalvedad.' · '.$foto : 'CERRADO')
            . ($acumulado ? ' · acumulado fiscal '.$acumulado['ano'].': '.$acumulado['operaciones']
                . ' operación(es) por '.$acumulado['valor'] : ''),
            $idUsuario);

        return [
            'ok' => true,
            'conSalvedad' => $conSalvedad,
            'bloqueos' => $condiciones['bloqueos'],
            'acumuladoFiscal' => $acumulado,
        ];
    }

    /**
     * Reabre un corte cerrado. Permiso propio, motivo obligatorio y bitácora.
     *
     * Es la acción más delicada del módulo: deshace la inmutabilidad de un mes
     * que ya se reportó. Deja el corte en CALCULADO —no en ABIERTO— porque sus
     * resultados siguen ahí y no hay que recalcularlo para consultarlo.
     *
     * Revierte además el acumulado fiscal que escribió su propio cierre, y sólo
     * ése: se acota por id_corte_origen y por origen, de modo que el 1399
     * histórico y lo escrito por cualquier otro corte quedan intactos. Ésa es la
     * mitad de la idempotencia; la otra la pone el cierre, que vuelve a escribir
     * desde cero. Cerrar, reabrir y volver a cerrar deja el acumulado idéntico.
     *
     * Los campos del cierre se limpian porque describen el estado actual del
     * corte, no su historia: la historia —incluida la foto de la salvedad— queda
     * en bitácora, que es la tabla que existe para eso.
     */
    public static function reabrirCorte($idCorte, $idUsuario, $motivo)
    {
        $corte = DB::table('det_corte')->where('id_corte', $idCorte)->first();
        if (!$corte) {
            return ['ok' => false, 'mensaje' => "El corte $idCorte no existe."];
        }
        if ($corte->estado !== self::ESTADO_CERRADO) {
            return ['ok' => false, 'mensaje' => 'El corte no está cerrado.'];
        }

        $motivo = trim((string) $motivo);
        if ($motivo === '') {
            return ['ok' => false, 'mensaje' => 'El motivo de la reapertura es obligatorio.'];
        }
        if (mb_strlen($motivo) > 500) {
            return ['ok' => false, 'mensaje' => 'El motivo no puede superar los 500 caracteres.'];
        }

        $revertidas = 0;
        DB::transaction(function () use ($idCorte, $idUsuario, $motivo, &$revertidas) {
            $revertidas = self::revertirAcumuladoFiscal($idCorte);

            DB::table('det_corte')->where('id_corte', $idCorte)->update([
                'estado' => self::ESTADO_CALCULADO,
                'fecha_cierre' => null,
                'id_usuario_cierre' => null,
                'cerrado_con_salvedad' => 0,
                'motivo_salvedad' => null,
                'foto_salvedad' => null,
                'fecha_reapertura' => self::ahora(),
                'id_usuario_reapertura' => $idUsuario,
                'motivo_reapertura' => $motivo,
            ]);

            // Con el corte ya abierto, C-CIERRE-FISCAL vuelve a N/A y deja de
            // comparar contra un acumulado que se acaba de revertir.
            if ($revertidas > 0) {
                self::verificarCuadres($idCorte);
            }
        });

        self::registrarBitacora('REABRIR_CORTE', $idCorte, null,
            'CERRADO'.($corte->cerrado_con_salvedad ? ' CON SALVEDAD · '.$corte->foto_salvedad : ''),
            $motivo.($revertidas ? ' · se revirtieron '.$revertidas.' fila(s) de acumulado fiscal' : ''),
            $idUsuario);

        return ['ok' => true, 'acumuladoRevertido' => $revertidas];
    }

    /**
     * Escribe en det_fiscal_acumulado la deducción fiscal del año de cada
     * operación del corte de diciembre (§9, RN-09).
     *
     * Es la pieza que la fase 3 dejó explícitamente para aquí: `sqlImpuestoDiferido()`
     * no escribe esta tabla porque reejecutar un corte duplicaría el acumulado
     * del año. Aquí se escribe una sola vez, en el cierre, y la idempotencia se
     * apoya en tres cosas:
     *
     * - Se borra primero lo que este mismo corte escribió con este mismo origen,
     *   de modo que cerrar dos veces deja lo mismo que cerrar una.
     * - Se firma cada fila con id_corte_origen y origen, que son las columnas
     *   que la fase 2 dejó previstas justo para esto y que permiten a la
     *   reapertura revertir lo suyo y nada más.
     * - No se toca ninguna fila del mismo año gravable escrita por otro origen o
     *   por otro corte. Si existiera, esa operación queda sin escribir y
     *   C-CIERRE-FISCAL lo delata por la diferencia exacta, que es lo contrario
     *   de sobreescribir en silencio un acumulado ajeno.
     *
     * Sólo entran las operaciones con deducción positiva: una fila en cero no es
     * acumulado, y P las sumaría igual a cambio de ensuciar la tabla con una
     * fila por operación y año.
     */
    public static function persistirAcumuladoFiscal($idCorte, $fechaCorte, $idUsuario)
    {
        $ano = (int) date('Y', strtotime($fechaCorte));

        $revertidas = self::revertirAcumuladoFiscal($idCorte, $ano);

        $escritas = DB::affectingStatement(
            'INSERT INTO det_fiscal_acumulado
                (id_operacion, ano_gravable, valor_deducido, id_corte_origen, origen, id_usuario, fecha_registro)
             SELECT o.id_operacion, ?, o.deduccion_fiscal_ano, ?, ?, ?, ?
             FROM det_deterioro_operacion o
             WHERE o.id_corte = ? AND o.deduccion_fiscal_ano > 0
               AND NOT EXISTS (SELECT 1 FROM det_fiscal_acumulado fa
                               WHERE fa.id_operacion = o.id_operacion AND fa.ano_gravable = ?)',
            [$ano, $idCorte, self::ORIGEN_ACUMULADO_CIERRE, $idUsuario, self::ahora(), $idCorte, $ano]);

        $total = DB::table('det_fiscal_acumulado')
            ->where('ano_gravable', $ano)->where('id_corte_origen', $idCorte)
            ->where('origen', self::ORIGEN_ACUMULADO_CIERRE)->sum('valor_deducido');

        return ['ano' => $ano, 'operaciones' => $escritas, 'valor' => (float) $total,
            'revertidas' => $revertidas];
    }

    /** Borra del acumulado fiscal lo que escribió el cierre de este corte, y sólo eso. */
    public static function revertirAcumuladoFiscal($idCorte, $ano = null)
    {
        $consulta = DB::table('det_fiscal_acumulado')
            ->where('id_corte_origen', $idCorte)
            ->where('origen', self::ORIGEN_ACUMULADO_CIERRE);

        if ($ano !== null) {
            $consulta->where('ano_gravable', $ano);
        }

        return $consulta->delete();
    }

    /* ---------------------------------------------------------------------
     | Consultas de presentación
     |-------------------------------------------------------------------- */

    public static function listarCortes()
    {
        $filas = DB::select(
            "SELECT c.*,
                    anterior = a.fecha_corte,
                    operaciones = (SELECT COUNT(*) FROM det_deterioro_operacion o WHERE o.id_corte = c.id_corte),
                    deterioro = (SELECT SUM(deterioro_contable) FROM det_deterioro_operacion o WHERE o.id_corte = c.id_corte),
                    cuadres_total = (SELECT COUNT(*) FROM det_corte_cuadre q WHERE q.id_corte = c.id_corte),
                    cuadres_falla = (SELECT COUNT(*) FROM det_corte_cuadre q WHERE q.id_corte = c.id_corte AND q.estado = 'FALLA'),
                    cuadres_na = (SELECT COUNT(*) FROM det_corte_cuadre q WHERE q.id_corte = c.id_corte AND q.estado = 'N/A')
             FROM det_corte c
             LEFT JOIN det_corte a ON a.id_corte = c.id_corte_anterior
             ORDER BY c.fecha_corte DESC");
        foreach ($filas as $f) {
            $f->modo_compatibilidad_excel = (bool) $f->modo_compatibilidad_excel;
            $f->cerrado_con_salvedad = (bool) $f->cerrado_con_salvedad;
        }
        return $filas;
    }

    public static function corte($idCorte)
    {
        $corte = DB::selectOne(
            'SELECT c.*, usuario_cierre = u.nombreUsuario
             FROM det_corte c
             LEFT JOIN users u ON u.idUsuario = c.id_usuario_cierre
             WHERE c.id_corte = ?', [$idCorte]);
        if ($corte) {
            $corte->modo_compatibilidad_excel = (bool) $corte->modo_compatibilidad_excel;
            $corte->cerrado_con_salvedad = (bool) $corte->cerrado_con_salvedad;
            // La foto se guarda como JSON para no fabricar ocho columnas de
            // salvedad, y se devuelve decodificada para que la pantalla no
            // tenga que saber que por dentro es una cadena.
            $corte->salvedad = $corte->foto_salvedad
                ? json_decode($corte->foto_salvedad, true) : null;
        }
        return $corte;
    }

    /**
     * Matriz producto x rango con capital, interés, base y deterioro.
     * Réplica del bloque T14:AB27 del libro.
     *
     * El impuesto diferido se devuelve además desdoblado por signo: neto para
     * el cuadre, activo y pasivo por separado porque compensarlos entre sí
     * falsearía la revelación.
     *
     * Se agrupa por calificacion_abc y no por ISNULL(rango_codigo, 'Corriente'):
     * rango_codigo es nchar(1) y ISNULL devuelve el tipo del primer argumento,
     * de modo que 'Corriente' se truncaría a 'C' y se mezclaría con ese rango.
     *
     * interes_prorroga_siesa se suma SIN ISNULL, al contrario que el resto: el
     * nulo tiene que sobrevivir la agregación porque es lo que distingue un
     * corte calculado antes de que la prórroga entrara en la base. Un cero ahí
     * diría «se midió y no hay», y la pantalla no podría avisar de que ese corte
     * no la evaluó. SUM ignora los nulos, así que el grupo sale nulo sólo si
     * ninguna de sus operaciones la tiene medida, que es justo el caso.
     */
    public static function resumenPorProductoRango($idCorte)
    {
        return DB::select(
            "SELECT producto,
                    rango = ISNULL(calificacion_abc, 'Corriente'),
                    orden_rango = ISNULL(pr.orden, 0),
                    operaciones = COUNT(*),
                    cuotas = SUM(o.cuotas),
                    capital_corriente = SUM(o.capital_corriente),
                    capital_vencido = SUM(o.capital_vencido),
                    interes_corriente = SUM(o.interes_corriente),
                    interes_vencido = SUM(o.interes_vencido),
                    interes_prorroga_siesa = SUM(o.interes_prorroga_siesa),
                    base = SUM(o.base_deterioro),
                    pct = MAX(o.pct_contable),
                    deterioro = SUM(o.deterioro_contable),
                    deterioro_fiscal_individual = SUM(o.deterioro_fiscal_individual),
                    deterioro_fiscal_general = SUM(o.deterioro_fiscal_general),
                    fiscal_acumulado_anterior = SUM(o.fiscal_acumulado_anterior),
                    saldo_topado = SUM(o.saldo_topado),
                    deduccion_fiscal_ano = SUM(o.deduccion_fiscal_ano),
                    deterioro_fiscal_acumulado = SUM(o.deterioro_fiscal_acumulado),
                    diferencia_temporaria = SUM(o.diferencia_temporaria),
                    impuesto_diferido_activo = SUM(o.impuesto_diferido_activo),
                    diferido_activo = SUM(CASE WHEN o.diferencia_temporaria > 0 THEN o.impuesto_diferido_activo ELSE 0 END),
                    diferido_pasivo = SUM(CASE WHEN o.diferencia_temporaria < 0 THEN -o.impuesto_diferido_activo ELSE 0 END),
                    deduce_fiscal = MAX(CASE WHEN pr.dias_desde >= pf.dias_minimos_mora THEN 1 ELSE 0 END),
                    pct_fiscal = MAX(CASE WHEN pr.dias_desde >= pf.dias_minimos_mora
                                          THEN ISNULL(pf.pct_anual, 0) ELSE 0 END)
             FROM det_deterioro_operacion o
             LEFT JOIN det_corte_param_rango_mora pr
                    ON pr.id_corte = o.id_corte AND pr.codigo = o.rango_codigo
             LEFT JOIN det_corte_param_fiscal pf
                    ON pf.id_corte = o.id_corte AND pf.activo = 1
             WHERE o.id_corte = ?
             GROUP BY o.producto, ISNULL(o.calificacion_abc, 'Corriente'), ISNULL(pr.orden, 0)
             ORDER BY o.producto, ISNULL(pr.orden, 0)", [$idCorte]);
    }

    /**
     * Totales fiscales por rango. Equivale al bloque Z25:AB32 del libro, con
     * los dos métodos en paralelo y el tope de RN-09 ya aplicado.
     */
    public static function resumenFiscal($idCorte)
    {
        return DB::select(
            "SELECT rango = ISNULL(o.calificacion_abc, 'Corriente'),
                    orden_rango = ISNULL(pr.orden, 0),
                    operaciones = COUNT(*),
                    base = SUM(o.base_deterioro),
                    deterioro_fiscal_individual = SUM(o.deterioro_fiscal_individual),
                    deterioro_fiscal_general = SUM(o.deterioro_fiscal_general),
                    fiscal_acumulado_anterior = SUM(o.fiscal_acumulado_anterior),
                    saldo_topado = SUM(o.saldo_topado),
                    deduccion_fiscal_ano = SUM(o.deduccion_fiscal_ano)
             FROM det_deterioro_operacion o
             LEFT JOIN det_corte_param_rango_mora pr
                    ON pr.id_corte = o.id_corte AND pr.codigo = o.rango_codigo
             WHERE o.id_corte = ?
             GROUP BY ISNULL(o.calificacion_abc, 'Corriente'), ISNULL(pr.orden, 0)
             ORDER BY ISNULL(pr.orden, 0)", [$idCorte]);
    }

    /** Tarifa de renta congelada del corte (art. 240). */
    public static function tarifaRenta($idCorte)
    {
        return DB::table('det_corte_param_convencion')->where('id_corte', $idCorte)->value('tarifa_renta');
    }

    /** Operaciones a las que el tope de RN-09 les recortó deducción, mismo criterio que el filtro soloTopadas. */
    public static function operacionesTopadas($idCorte)
    {
        return DB::table('det_deterioro_operacion')
            ->where('id_corte', $idCorte)
            ->whereColumn('deduccion_fiscal_ano', '<', 'deterioro_fiscal_individual')
            ->count();
    }

    /**
     * Puente de movimiento: totales del corte contra los del corte anterior.
     *
     * tiene_anterior distingue el primer corte de la serie de uno cuyo anterior
     * dio cero, para que la pantalla no presente el saldo inicial como variación.
     */
    public static function puenteMovimiento($idCorte)
    {
        return DB::selectOne(
            "SELECT tiene_anterior = CASE WHEN c.id_corte_anterior IS NULL OR p.fiscal IS NULL THEN 0 ELSE 1 END,
                    anterior_sin_fase3 = CASE WHEN c.id_corte_anterior IS NOT NULL AND p.fiscal IS NULL THEN 1 ELSE 0 END,
                    id_corte_anterior = c.id_corte_anterior,
                    fecha_anterior = a.fecha_corte,
                    contable = t.contable, contable_ant = p.contable,
                    fiscal = t.fiscal, fiscal_ant = p.fiscal,
                    temporaria = t.temporaria, temporaria_ant = p.temporaria,
                    diferido = t.diferido, diferido_ant = p.diferido,
                    deduccion = t.deduccion, deduccion_ant = p.deduccion
             FROM det_corte c
             LEFT JOIN det_corte a ON a.id_corte = c.id_corte_anterior
             CROSS APPLY (SELECT contable = SUM(o.deterioro_contable),
                                 fiscal = SUM(o.deterioro_fiscal_acumulado),
                                 temporaria = SUM(o.diferencia_temporaria),
                                 diferido = SUM(o.impuesto_diferido_activo),
                                 deduccion = SUM(o.deduccion_fiscal_ano)
                          FROM det_deterioro_operacion o WHERE o.id_corte = c.id_corte) t
             CROSS APPLY (SELECT contable = SUM(o.deterioro_contable),
                                 fiscal = SUM(o.deterioro_fiscal_acumulado),
                                 temporaria = SUM(o.diferencia_temporaria),
                                 diferido = SUM(o.impuesto_diferido_activo),
                                 deduccion = SUM(o.deduccion_fiscal_ano)
                          FROM det_deterioro_operacion o WHERE o.id_corte = c.id_corte_anterior) p
             WHERE c.id_corte = ?", [$idCorte]);
    }

    /**
     * Serie histórica por corte, hasta la fecha del corte pedido: tamaño de la
     * cartera, deterioro contable, bloque fiscal y gasto del período.
     *
     * El gasto se recalcula aquí contra el total del corte anterior en vez de
     * sumar variacion_deterioro: la suma de la columna deja fuera las bajas,
     * que sí bajan el saldo. Con un corte sin anterior el gasto queda nulo,
     * porque el saldo inicial de la serie no es gasto del mes.
     */
    public static function serieHistorica($idCorte, $cortes = 24)
    {
        return DB::select(
            "SELECT TOP (?) c.id_corte, c.fecha_corte, c.id_corte_anterior,
                    operaciones = COUNT(o.id_operacion),
                    base = SUM(o.base_deterioro),
                    contable = SUM(o.deterioro_contable),
                    fiscal = SUM(o.deterioro_fiscal_acumulado),
                    temporaria = SUM(o.diferencia_temporaria),
                    diferido = SUM(o.impuesto_diferido_activo),
                    gasto = CASE WHEN c.id_corte_anterior IS NULL THEN NULL
                                 ELSE SUM(o.deterioro_contable) - ISNULL((
                                     SELECT SUM(p.deterioro_contable)
                                     FROM det_deterioro_operacion p
                                     WHERE p.id_corte = c.id_corte_anterior), 0) END
             FROM det_corte c
             INNER JOIN det_deterioro_operacion o ON o.id_corte = c.id_corte
             WHERE c.fecha_corte <= (SELECT fecha_corte FROM det_corte WHERE id_corte = ?)
             GROUP BY c.id_corte, c.fecha_corte, c.id_corte_anterior
             ORDER BY c.fecha_corte DESC", [(int) $cortes, $idCorte]);
    }

    /** Nombre anterior de serieHistorica(), que usa la pantalla de la fase 3. */
    public static function evolucionDiferenciaTemporaria($idCorte, $cortes = 24)
    {
        return self::serieHistorica($idCorte, $cortes);
    }

    /**
     * Descomposición del movimiento del mes (RN-10): la ecuación del período
     * `anterior + altas + variación de las que continúan − bajas = actual`.
     *
     * `control` es el lado izquierdo armado con lo que dejó el motor y `actual`
     * el saldo del corte; C-MOVIMIENTO compara los dos. `gasto` es el T10 del
     * libro a nivel de total, que allí sale de restar una cifra digitada.
     *
     * ISNULL(movimiento, 'ALTA') cubre el primer corte de la serie, donde no
     * hay anterior y el motor no clasifica: todo entra como alta contra un
     * saldo anterior de cero y la ecuación cierra igual.
     */
    public static function descomposicionMovimiento($idCorte)
    {
        return DB::selectOne(
            "SELECT id_corte_anterior = c.id_corte_anterior,
                    fecha_anterior = a.fecha_corte,
                    anterior = p.deterioro,
                    altas = t.altas, operaciones_alta = t.n_altas,
                    variacion = t.variacion, operaciones_variacion = t.n_variacion,
                    bajas = b.deterioro, operaciones_baja = b.operaciones,
                    actual = t.actual,
                    gasto = CASE WHEN c.id_corte_anterior IS NULL THEN NULL
                                 ELSE t.actual - p.deterioro END,
                    control = p.deterioro + t.altas + t.variacion - b.deterioro
             FROM det_corte c
             LEFT JOIN det_corte a ON a.id_corte = c.id_corte_anterior
             CROSS APPLY (
                 SELECT actual = ISNULL(SUM(o.deterioro_contable), 0),
                        altas = ISNULL(SUM(CASE WHEN ISNULL(o.movimiento, 'ALTA') = 'ALTA'
                                                THEN o.deterioro_contable ELSE 0 END), 0),
                        n_altas = SUM(CASE WHEN ISNULL(o.movimiento, 'ALTA') = 'ALTA' THEN 1 ELSE 0 END),
                        variacion = ISNULL(SUM(CASE WHEN o.movimiento = 'VARIACION'
                                                    THEN o.variacion_deterioro ELSE 0 END), 0),
                        n_variacion = SUM(CASE WHEN o.movimiento = 'VARIACION' THEN 1 ELSE 0 END)
                 FROM det_deterioro_operacion o WHERE o.id_corte = c.id_corte) t
             CROSS APPLY (
                 SELECT deterioro = ISNULL(SUM(o.deterioro_contable), 0)
                 FROM det_deterioro_operacion o WHERE o.id_corte = c.id_corte_anterior) p
             CROSS APPLY (
                 SELECT operaciones = COUNT(*), deterioro = ISNULL(SUM(o.deterioro_contable), 0)
                 FROM det_deterioro_operacion o
                 WHERE o.id_corte = c.id_corte_anterior
                   AND NOT EXISTS (SELECT 1 FROM det_deterioro_operacion x
                                   WHERE x.id_corte = c.id_corte AND x.id_operacion = o.id_operacion)) b
             WHERE c.id_corte = ?", [$idCorte]);
    }

    /* ---------------------------------------------------------------------
     | C-2 · Salidas de la base entre cortes (D-09, §15)
     |-------------------------------------------------------------------- */

    /**
     * Operaciones presentes en el corte anterior y ausentes en el actual, con
     * el deterioro que traían y con su clasificación si ya la tienen (§10 paso
     * 10, C-2).
     *
     * PENDIENTE no se guarda: es la ausencia de fila en det_salida_operacion,
     * igual que en la conciliación. Una baja que aparece por primera vez tras
     * un recálculo nace pendiente sin que nadie tenga que escribirla, y una que
     * deja de serlo —porque la operación reapareció— desaparece de la lista sin
     * arrastrar una fila de estado que ya no significa nada.
     *
     * La detección sigue siendo la misma consulta de la fase 4; lo que esta
     * fase agrega es el lado derecho: quién la clasificó, cómo y cuándo.
     */
    public static function bajasDelPeriodo($idCorte)
    {
        return DB::select(
            "SELECT a.id_operacion, a.id_cliente, a.cliente, a.producto, a.nom_operacion,
                    rango = ISNULL(a.calificacion_abc, 'Corriente'),
                    a.dias_mora_operacion, a.base_deterioro, a.deterioro_contable,
                    clasificacion = ISNULL(s.clasificacion, 'PENDIENTE'),
                    causal = ISNULL(p.descripcion, q.descripcion),
                    cierra_fiscal = ISNULL(p.cierra_fiscal, q.cierra_fiscal),
                    s.observacion, s.referencia_operacion_nueva,
                    s.fiscal_acumulado_cerrado, s.id_usuario, usuario = u.nombreUsuario, s.fecha
             FROM det_corte c
             INNER JOIN det_deterioro_operacion a ON a.id_corte = c.id_corte_anterior
             LEFT JOIN det_salida_operacion s
                    ON s.id_corte = c.id_corte AND s.id_operacion = a.id_operacion
             LEFT JOIN det_corte_param_causal_salida p
                    ON p.id_corte = c.id_corte AND p.codigo = s.clasificacion
             LEFT JOIN (".self::sqlCausalSalidaViva().") q ON q.codigo = s.clasificacion
             LEFT JOIN users u ON u.idUsuario = s.id_usuario
             WHERE c.id_corte = ?
               AND NOT EXISTS (SELECT 1 FROM det_deterioro_operacion o
                               WHERE o.id_corte = c.id_corte AND o.id_operacion = a.id_operacion)
             ORDER BY a.deterioro_contable DESC, a.id_operacion", [$idCorte]);
    }

    /**
     * Rótulo y tratamiento de la causal cuando el corte no tiene copia
     * congelada, que es el caso de los cortes calculados antes de esta fase.
     *
     * Se agrupa por código porque la paramétrica lleva vigencias y una misma
     * causal puede tener varias filas a lo largo del tiempo: sin el agrupamiento
     * la unión multiplicaría la fila de la baja. La copia congelada del corte
     * manda siempre que exista; esto es sólo el respaldo para no dejar la
     * clasificación sin rótulo.
     */
    private static function sqlCausalSalidaViva()
    {
        return "SELECT codigo, descripcion = MIN(descripcion),
                       cierra_fiscal = MAX(CAST(cierra_fiscal AS int)),
                       orden = MIN(orden)
                FROM det_param_causal_salida GROUP BY codigo";
    }

    /**
     * Causales de salida del corte, para poblar la captura y para validarla.
     *
     * Se leen de la copia congelada del corte, que es la que estaba vigente
     * cuando se calculó. Si el corte no la tiene —los calculados antes de esta
     * fase— se cae a la paramétrica vigente hoy: sin esa salida no se podría
     * clasificar la baja de un corte viejo, y quedarían pendientes para
     * siempre bloqueando su cierre.
     */
    public static function causalesSalida($idCorte = null)
    {
        if ($idCorte) {
            $congeladas = DB::select(
                'SELECT codigo, descripcion, activa, cierra_fiscal, pide_referencia
                 FROM det_corte_param_causal_salida
                 WHERE id_corte = ? AND activa = 1 ORDER BY orden', [$idCorte]);
            if ($congeladas) {
                return $congeladas;
            }
        }

        $hoy = date('Y-m-d');
        return DB::select(
            'SELECT codigo, descripcion, activa, cierra_fiscal, pide_referencia
             FROM det_param_causal_salida
             WHERE activa = 1 AND vigente_desde <= ? AND (vigente_hasta IS NULL OR vigente_hasta >= ?)
             ORDER BY orden', [$hoy, $hoy]);
    }

    /**
     * Clasifica una baja y la persiste (C-2). Es un acto contable con autor:
     * queda en bitácora igual que explicar una partida o marcar una suspensión.
     *
     * Las tres cifras se congelan aquí y no se recalculan al leer. El corte
     * anterior es inmutable, así que hoy darían lo mismo; se guardan porque en
     * el castigo son el asiento que cierra el deterioro acumulado en el mismo
     * movimiento, y un asiento no es una consulta que se rehace.
     *
     * Qué causal cierra el acumulado fiscal lo dice la paramétrica y no un
     * `=== 'CASTIGO'` en el código: fiscal_acumulado_cerrado se llena cuando
     * cierra_fiscal está en 1 y queda nulo cuando no, de modo que un cero
     * significa "no había deducción que cerrar" y el nulo "esta causal no
     * cierra ninguna", que son cosas distintas.
     *
     * La referencia de la operación nueva se guarda tal como se digita y no se
     * cruza contra nada: D-07 dice expresamente que la operación nueva no se
     * vincula con la cerrada.
     */
    public static function clasificarSalida($idCorte, $idOperacion, $clasificacion, $observacion,
                                            $idUsuario, $referencia = null)
    {
        $abierto = self::corteAbierto($idCorte);
        if (!$abierto['ok']) {
            return ['ok' => false, 'mensaje' => $abierto['mensaje']];
        }

        $causal = null;
        foreach (self::causalesSalida($idCorte) as $c) {
            if ($c->codigo === $clasificacion) {
                $causal = $c;
            }
        }
        if (!$causal) {
            return ['ok' => false, 'mensaje' => 'La causal de salida no existe o no está activa en este corte.'];
        }

        $observacion = trim((string) $observacion);
        if ($observacion === '') {
            return ['ok' => false, 'mensaje' => 'La observación es obligatoria.'];
        }
        if (mb_strlen($observacion) > 500) {
            return ['ok' => false, 'mensaje' => 'La observación no puede superar los 500 caracteres.'];
        }
        $referencia = trim((string) $referencia);
        if (mb_strlen($referencia) > 30) {
            return ['ok' => false, 'mensaje' => 'La referencia de la operación nueva no puede superar los 30 caracteres.'];
        }

        // La baja tiene que serlo de verdad: estar en el corte anterior y no en
        // éste. Sin esta comprobación se podría clasificar como castigada una
        // operación que sigue viva en el corte.
        $baja = DB::selectOne(
            'SELECT a.base_deterioro, a.deterioro_contable
             FROM det_corte c
             INNER JOIN det_deterioro_operacion a
                     ON a.id_corte = c.id_corte_anterior AND a.id_operacion = ?
             WHERE c.id_corte = ?
               AND NOT EXISTS (SELECT 1 FROM det_deterioro_operacion o
                               WHERE o.id_corte = c.id_corte AND o.id_operacion = a.id_operacion)',
            [$idOperacion, $idCorte]);

        if (!$baja) {
            return ['ok' => false, 'mensaje' =>
                'La operación no es una salida de este corte: no estaba en el corte anterior o sigue en el actual.'];
        }

        $acumulado = $causal->cierra_fiscal
            ? (float) DB::table('det_fiscal_acumulado')
                ->where('id_operacion', $idOperacion)->sum('valor_deducido')
            : null;

        $fila = DB::table('det_salida_operacion')
            ->where('id_corte', $idCorte)->where('id_operacion', $idOperacion);
        $anterior = $fila->first();

        $campos = [
            'clasificacion' => $clasificacion,
            'observacion' => $observacion,
            'referencia_operacion_nueva' => $referencia === '' ? null : $referencia,
            'base_cerrada' => $baja->base_deterioro,
            'deterioro_cerrado' => $baja->deterioro_contable,
            'fiscal_acumulado_cerrado' => $acumulado,
            'id_usuario' => $idUsuario,
            'fecha' => self::ahora(),
        ];

        if ($anterior) {
            $fila->update($campos);
        } else {
            DB::table('det_salida_operacion')->insert($campos + [
                'id_corte' => $idCorte,
                'id_operacion' => $idOperacion,
            ]);
        }

        self::registrarBitacora('CLASIFICAR_SALIDA', $idCorte, $idOperacion,
            $anterior ? $anterior->clasificacion.': '.$anterior->observacion : null,
            $clasificacion.': '.$observacion, $idUsuario);

        return ['ok' => true, 'clasificacion' => $clasificacion,
            'deterioroCerrado' => (float) $baja->deterioro_contable,
            'fiscalAcumuladoCerrado' => $acumulado];
    }

    /**
     * Descomposición de las bajas del mes por causa (C-2).
     *
     * Es la razón por la que la etiqueta importa: sin ella una caída por cierre
     * con apertura de una operación nueva se ve igual que un recaudo en la
     * descomposición del movimiento del mes, y son cosas contablemente
     * distintas. Las pendientes salen como una fila más, no se esconden.
     */
    public static function descomposicionBajas($idCorte)
    {
        return DB::select(
            "SELECT clasificacion = ISNULL(s.clasificacion, 'PENDIENTE'),
                    causal = ISNULL(ISNULL(p.descripcion, q.descripcion), 'Sin clasificar'),
                    orden = ISNULL(ISNULL(p.orden, q.orden), 99),
                    cierra_fiscal = ISNULL(ISNULL(CAST(p.cierra_fiscal AS int), q.cierra_fiscal), 0),
                    operaciones = COUNT(*),
                    base = ISNULL(SUM(a.base_deterioro), 0),
                    deterioro = ISNULL(SUM(a.deterioro_contable), 0),
                    fiscal_cerrado = ISNULL(SUM(s.fiscal_acumulado_cerrado), 0)
             FROM det_corte c
             INNER JOIN det_deterioro_operacion a ON a.id_corte = c.id_corte_anterior
             LEFT JOIN det_salida_operacion s
                    ON s.id_corte = c.id_corte AND s.id_operacion = a.id_operacion
             LEFT JOIN det_corte_param_causal_salida p
                    ON p.id_corte = c.id_corte AND p.codigo = s.clasificacion
             LEFT JOIN (".self::sqlCausalSalidaViva().") q ON q.codigo = s.clasificacion
             WHERE c.id_corte = ?
               AND NOT EXISTS (SELECT 1 FROM det_deterioro_operacion o
                               WHERE o.id_corte = c.id_corte AND o.id_operacion = a.id_operacion)
             GROUP BY ISNULL(s.clasificacion, 'PENDIENTE'),
                      ISNULL(ISNULL(p.descripcion, q.descripcion), 'Sin clasificar'),
                      ISNULL(ISNULL(p.orden, q.orden), 99),
                      ISNULL(ISNULL(CAST(p.cierra_fiscal AS int), q.cierra_fiscal), 0)
             ORDER BY ISNULL(ISNULL(p.orden, q.orden), 99)", [$idCorte]);
    }

    /* ---------------------------------------------------------------------
     | C-1 · Prórrogas que reducen la antigüedad de la mora (D-08, §15)
     |-------------------------------------------------------------------- */

    /**
     * Operaciones cuya antigüedad de mora bajó respecto al corte anterior, con
     * el deterioro que eso liberó.
     *
     * El control se define por el efecto y no por la causa, igual que en §15: el
     * módulo no ve la prórroga, ve que los días de mora bajaron. Una cuota
     * trasladada al final (D-08) produce esa caída, pero también la produce un
     * pago que cancela la cuota vencida más antigua y mueve FecInicialMora. Las
     * dos hay que revisarlas y por eso las dos entran a la lista.
     *
     * cruzo_umbral_fiscal marca las que además dejaron de cumplir el mínimo de
     * mora que exige la deducción del 33 %: la prórroga reinicia ese conteo y
     * eso tiene efecto fiscal propio, distinto de bajar de rango contable.
     * dias_minimos_mora sale de la paramétrica congelada del método activo, no
     * de un 361 escrito aquí.
     */
    public static function prorrogasQueReducenMora($idCorte)
    {
        $idCorteAnterior = DB::table('det_corte')->where('id_corte', $idCorte)->value('id_corte_anterior');
        if (!$idCorteAnterior) {
            return [];
        }

        return DB::select(
            "SELECT o.id_operacion, o.id_cliente, o.cliente, o.producto, o.nom_operacion,
                    dias_mora = o.dias_mora_operacion,
                    dias_mora_anterior = a.dias_mora_operacion,
                    dias_reducidos = a.dias_mora_operacion - o.dias_mora_operacion,
                    rango = ISNULL(o.calificacion_abc, 'Corriente'),
                    rango_anterior = ISNULL(a.calificacion_abc, 'Corriente'),
                    bajo_de_rango = CASE WHEN ISNULL(o.pct_contable, 0) < ISNULL(a.pct_contable, 0)
                                         THEN 1 ELSE 0 END,
                    o.base_deterioro, o.deterioro_contable,
                    deterioro_anterior = a.deterioro_contable,
                    deterioro_liberado = a.deterioro_contable - o.deterioro_contable,
                    deduccion_fiscal_ano = o.deduccion_fiscal_ano,
                    deduccion_anterior = a.deduccion_fiscal_ano,
                    cruzo_umbral_fiscal = CASE
                        WHEN pf.dias_minimos_mora IS NOT NULL
                         AND a.dias_mora_operacion >= pf.dias_minimos_mora
                         AND o.dias_mora_operacion <  pf.dias_minimos_mora
                        THEN 1 ELSE 0 END
             FROM det_deterioro_operacion o
             INNER JOIN det_deterioro_operacion a
                     ON a.id_corte = ? AND a.id_operacion = o.id_operacion
             LEFT JOIN det_corte_param_fiscal pf
                    ON pf.id_corte = o.id_corte AND pf.activo = 1
             WHERE o.id_corte = ? AND a.dias_mora_operacion > o.dias_mora_operacion
             ORDER BY a.deterioro_contable - o.deterioro_contable DESC, o.id_operacion",
            [$idCorteAnterior, $idCorte]);
    }

    /**
     * Las dos vías de C-1 sobre la misma población.
     *
     * `motor` suma la columna que dejó aplicarMovimientoMes, con el signo
     * invertido porque variacion_deterioro es actual − anterior y lo liberado es
     * lo contrario. `recalculado` vuelve al corte anterior y resta operación por
     * operación sin mirar ninguna columna del enlace.
     *
     * Son dos caminos, no la misma cuenta escrita dos veces: si el enlace del
     * movimiento del mes perdiera una operación, tomara la equivocada o dejara
     * variacion_deterioro nula, el primero se separa del segundo y el control lo
     * dice. Es el mismo criterio con que C-VARIACION vigila el total.
     */
    public static function sqlControlProrroga()
    {
        return "SELECT operaciones = COUNT(*),
                       motor = ISNULL(-SUM(o.variacion_deterioro), 0),
                       recalculado = ISNULL(SUM(a.deterioro_contable - o.deterioro_contable), 0)
                FROM det_deterioro_operacion o
                INNER JOIN det_deterioro_operacion a
                        ON a.id_corte = ? AND a.id_operacion = o.id_operacion
                WHERE o.id_corte = ? AND a.dias_mora_operacion > o.dias_mora_operacion";
    }

    /**
     * Comparativo del deterioro contra el corte anterior por producto, rango o
     * cliente. Con dimension = 'rango' reproduce las filas 8, 9 y 10 del libro.
     *
     * El FULL OUTER JOIN es necesario porque un valor de la dimensión puede
     * existir sólo en uno de los dos cortes: un producto nuevo o un rango que
     * se vació. Un INNER JOIN los perdería y el total no cuadraría con
     * descomposicionMovimiento().
     */
    public static function comparativoContraAnterior($idCorte, $dimension)
    {
        $expresiones = [
            'producto' => "ISNULL(o.producto, N'SIN PRODUCTO')",
            'rango' => "ISNULL(o.calificacion_abc, N'Corriente')",
            'cliente' => "ISNULL(o.cliente, N'SIN CLIENTE')",
        ];
        if (!isset($expresiones[$dimension])) {
            throw new \InvalidArgumentException("Dimensión no soportada: $dimension.");
        }
        $llave = $expresiones[$dimension];

        $agregado = "SELECT llave = $llave,
                            orden = MAX(ISNULL(pr.orden, 0)),
                            operaciones = COUNT(*),
                            base = SUM(o.base_deterioro),
                            deterioro = SUM(o.deterioro_contable)
                     FROM det_deterioro_operacion o
                     LEFT JOIN det_corte_param_rango_mora pr
                            ON pr.id_corte = o.id_corte AND pr.codigo = o.rango_codigo
                     WHERE o.id_corte = ?
                     GROUP BY $llave";

        return DB::select(
            "SELECT dimension = ISNULL(t.llave, p.llave),
                    orden = ISNULL(t.orden, p.orden),
                    operaciones = ISNULL(t.operaciones, 0),
                    operaciones_ant = ISNULL(p.operaciones, 0),
                    base = ISNULL(t.base, 0),
                    deterioro = ISNULL(t.deterioro, 0),
                    deterioro_ant = ISNULL(p.deterioro, 0),
                    variacion = ISNULL(t.deterioro, 0) - ISNULL(p.deterioro, 0)
             FROM ($agregado) t
             FULL OUTER JOIN ($agregado) p ON p.llave = t.llave
             ORDER BY ISNULL(t.orden, p.orden), ISNULL(t.llave, p.llave)",
            [$idCorte, DB::table('det_corte')->where('id_corte', $idCorte)->value('id_corte_anterior')]);
    }

    /* ---------------------------------------------------------------------
     | Conciliación con el libro
     |-------------------------------------------------------------------- */

    /**
     * Guarda las cifras del libro para un corte en det_validacion_excel, que
     * existe desde la fase 1 justo para esto y no tenía quién la poblara.
     *
     * Upsert por (id_corte, hoja, columna): el libro se rehace cada mes y la
     * carga tiene que poder repetirse sin duplicar ni dejar rastros del valor
     * anterior. $valores es un mapa columna => valor.
     *
     * Sobre un corte cerrado no se carga: las cifras del libro alimentan
     * C-LIBRO y cambiarlas movería un cuadre de un corte ya cerrado.
     */
    public static function registrarValidacionExcel($idCorte, $hoja, $valores, $idOperacion = null)
    {
        $abierto = self::corteAbierto($idCorte);
        if (!$abierto['ok']) {
            throw new \RuntimeException($abierto['mensaje']);
        }

        foreach ($valores as $columna => $valor) {
            $fila = DB::table('det_validacion_excel')
                ->where('id_corte', $idCorte)->where('hoja', $hoja)->where('columna', $columna);
            $fila = $idOperacion === null ? $fila->whereNull('id_operacion')
                                          : $fila->where('id_operacion', $idOperacion);

            if ($fila->exists()) {
                $fila->update(['valor_excel' => $valor]);
            } else {
                DB::table('det_validacion_excel')->insert([
                    'id_corte' => $idCorte, 'hoja' => $hoja,
                    'id_operacion' => $idOperacion, 'columna' => $columna,
                    'valor_excel' => $valor,
                ]);
            }
        }
        return count($valores);
    }

    /** Cifras del libro de una hoja como mapa columna => valor. */
    public static function validacionExcel($idCorte, $hoja = 'DETERIORO')
    {
        $filas = DB::table('det_validacion_excel')
            ->where('id_corte', $idCorte)->where('hoja', $hoja)->whereNull('id_operacion')
            ->pluck('valor_excel', 'columna');

        return $filas->map(function ($v) {
            return (float) $v;
        })->all();
    }

    /**
     * Conciliación del movimiento del mes contra el libro (RN-10).
     *
     * `digitada` marca las cifras que en el libro se teclean a mano: la fila 9
     * y, por arrastre, el gasto del período, que sale de restarla. Donde
     * difieren no está dicho cuál de las dos es la buena: el corte anterior del
     * módulo se extrajo con un conteo de filas distinto al de julio y está
     * pendiente de confirmar que sea un snapshot fiel de junio.
     *
     * Devuelve null si el corte no tiene cargadas las cifras del libro.
     */
    public static function conciliacionLibro($idCorte)
    {
        $libro = self::validacionExcel($idCorte);
        if (!$libro) {
            return null;
        }
        $d = self::descomposicionMovimiento($idCorte);
        $valor = function ($columna) use ($libro) {
            return isset($libro[$columna]) ? $libro[$columna] : null;
        };

        $filas = [
            ['concepto' => 'Deterioro del mes anterior', 'modulo' => (float) $d->anterior,
                'libro' => $valor('F9_TOTAL'), 'digitada' => true],
            ['concepto' => 'Deterioro de este corte', 'modulo' => (float) $d->actual,
                'libro' => $valor('F8_TOTAL'), 'digitada' => false],
        ];
        if ($d->gasto !== null) {
            $filas[] = ['concepto' => 'Gasto del período', 'modulo' => (float) $d->gasto,
                'libro' => $valor('F10_TOTAL'), 'digitada' => true];
        }

        // El desglose es del mes anterior porque ahí está la diferencia: es la
        // fila que el libro digita.
        $anterior = [];
        foreach (self::comparativoContraAnterior($idCorte, 'rango') as $r) {
            $anterior[$r->dimension] = (float) $r->deterioro_ant;
        }
        $rangos = [];
        foreach (['B', 'C', 'D', 'E', 'F'] as $rango) {
            $rangos[] = ['rango' => $rango,
                'modulo' => isset($anterior[$rango]) ? $anterior[$rango] : 0,
                'libro' => $valor('F9_'.$rango)];
        }

        return ['filas' => $filas, 'rangos' => $rangos];
    }

    /**
     * Proyección del año gravable en que cada operación completa el 100 %
     * fiscal. SIN_PROYECCION agrupa las que no alcanzan a deducir con la
     * paramétrica vigente.
     */
    public static function proyeccionReversion($idCorte)
    {
        return DB::select(
            "SELECT ano = o.ano_reversion_fiscal,
                    tramo = CASE
                        WHEN o.estado_reversion = 'AGOTADA' THEN 'AGOTADA'
                        WHEN o.ano_reversion_fiscal IS NULL THEN 'SIN_PROYECCION'
                        WHEN o.ano_reversion_fiscal <= YEAR(c.fecha_corte) THEN 'ANO_CORRIENTE'
                        WHEN o.ano_reversion_fiscal = YEAR(c.fecha_corte) + 1 THEN 'ANO_SIGUIENTE'
                        ELSE 'POSTERIOR' END,
                    operaciones = COUNT(*),
                    base = SUM(o.base_deterioro),
                    temporaria = SUM(o.diferencia_temporaria),
                    diferido = SUM(o.impuesto_diferido_activo)
             FROM det_deterioro_operacion o
             INNER JOIN det_corte c ON c.id_corte = o.id_corte
             WHERE o.id_corte = ?
             GROUP BY o.ano_reversion_fiscal, CASE
                        WHEN o.estado_reversion = 'AGOTADA' THEN 'AGOTADA'
                        WHEN o.ano_reversion_fiscal IS NULL THEN 'SIN_PROYECCION'
                        WHEN o.ano_reversion_fiscal <= YEAR(c.fecha_corte) THEN 'ANO_CORRIENTE'
                        WHEN o.ano_reversion_fiscal = YEAR(c.fecha_corte) + 1 THEN 'ANO_SIGUIENTE'
                        ELSE 'POSTERIOR' END
             ORDER BY o.ano_reversion_fiscal", [$idCorte]);
    }

    public static function cuadres($idCorte)
    {
        $filas = DB::select('SELECT * FROM det_corte_cuadre WHERE id_corte = ? ORDER BY codigo', [$idCorte]);
        foreach ($filas as $f) {
            $f->informativo = (bool) $f->informativo;
        }
        return $filas;
    }

    /**
     * Detalle por operación. Devuelve datos, no HTML: son ~2.100 filas.
     *
     * cuotas_duplicadas cuenta las cuotas que el origen repitió y que quedaron
     * fuera del cálculo de esa operación; no salen de det_deterioro_operacion,
     * que ya no las tiene, sino del detalle, que las conserva todas.
     *
     * duplicadas_evaluadas dice si este corte llegó a mirarlas. Los cortes
     * calculados antes de esta entrega no se recalculan y tienen la columna
     * nula en todo el detalle, que es indistinguible de "las miré y no había
     * ninguna": ahí un cero sería una afirmación falsa. Se resuelve por la
     * existencia del cuadre C-DUPLICADAS, que sólo escribe el motor con la
     * marca ya aplicada.
     */
    public static function detalleOperaciones($idCorte, $filtros = [])
    {
        $sql = "SELECT id_operacion, id_cliente, cliente, producto, nom_operacion,
                       fec_inicial_mora, cuotas, dias_mora_operacion,
                       rango = ISNULL(calificacion_abc, 'Corriente'),
                       capital_corriente, capital_vencido, interes_corriente, interes_vencido,
                       interes_mora, interes_prorroga_siesa,
                       capital_vencido_siesa, interes_vencido_siesa, origen_saldo_siesa, saldo_siesa,
                       interes_siesa = CASE WHEN saldo_siesa IS NULL THEN NULL ELSE ISNULL(si.saldo_interes, 0) END,
                       base_deterioro, suspendida, base_congelada, origen_base,
                       pct_contable, deterioro_contable,
                       deterioro_fiscal_individual, deterioro_fiscal_general,
                       fiscal_acumulado_anterior, saldo_topado, deduccion_fiscal_ano,
                       deterioro_fiscal_acumulado, diferencia_temporaria,
                       impuesto_diferido_activo, ano_reversion_fiscal, estado_reversion,
                       capital_mes_anterior, variacion_capital,
                       cuotas_duplicadas = (SELECT COUNT(*) FROM det_corte_detalle_cuota d
                           WHERE d.id_corte = det_deterioro_operacion.id_corte
                             AND d.id_operacion = det_deterioro_operacion.id_operacion
                             AND d.duplicada_de IS NOT NULL),
                       duplicadas_evaluadas = CASE WHEN EXISTS (SELECT 1 FROM det_corte_cuadre q
                           WHERE q.id_corte = det_deterioro_operacion.id_corte
                             AND q.codigo = 'C-DUPLICADAS') THEN 1 ELSE 0 END
                FROM det_deterioro_operacion
                LEFT JOIN (SELECT id_operacion_nota, saldo_interes = SUM(saldo)
                           FROM det_corte_saldo_siesa
                           WHERE id_corte = ? AND componente = 'INTERES' AND id_operacion_nota IS NOT NULL
                           GROUP BY id_operacion_nota) si
                       ON si.id_operacion_nota = det_deterioro_operacion.id_operacion
                WHERE det_deterioro_operacion.id_corte = ?";
        $bind = [$idCorte, $idCorte];

        if (!empty($filtros['producto'])) {
            $sql .= ' AND producto = ?';
            $bind[] = $filtros['producto'];
        }
        if (!empty($filtros['rango'])) {
            $sql .= " AND ISNULL(calificacion_abc, 'Corriente') = ?";
            $bind[] = $filtros['rango'];
        }
        if (!empty($filtros['soloDeterioro'])) {
            $sql .= ' AND deterioro_contable > 0';
        }
        if (!empty($filtros['soloDeduccion'])) {
            $sql .= ' AND deduccion_fiscal_ano > 0';
        }
        // Topada: el tope de RN-09 recortó la deducción por debajo del individual.
        if (!empty($filtros['soloTopadas'])) {
            $sql .= ' AND deduccion_fiscal_ano < deterioro_fiscal_individual';
        }
        // Pasivo: el acumulado fiscal superó al contable (§11).
        if (!empty($filtros['soloPasivo'])) {
            $sql .= ' AND diferencia_temporaria < 0';
        }
        // Repetidas: el origen entregó al menos una cuota duplicada.
        if (!empty($filtros['soloDuplicadas'])) {
            $sql .= ' AND EXISTS (SELECT 1 FROM det_corte_detalle_cuota d
                          WHERE d.id_corte = det_deterioro_operacion.id_corte
                            AND d.id_operacion = det_deterioro_operacion.id_operacion
                            AND d.duplicada_de IS NOT NULL)';
        }
        // Prórroga: la operación llevó prórroga vencida de SIESA a la base.
        if (!empty($filtros['soloProrroga'])) {
            $sql .= ' AND ISNULL(interes_prorroga_siesa, 0) > 0';
        }
        if (!empty($filtros['busqueda'])) {
            $sql .= ' AND (cliente LIKE ? OR id_cliente LIKE ? OR CAST(id_operacion AS varchar(20)) LIKE ?)';
            $like = '%'.$filtros['busqueda'].'%';
            $bind[] = $like; $bind[] = $like; $bind[] = $like;
        }
        $sql .= ' ORDER BY deterioro_contable DESC, id_operacion';

        return DB::select($sql, $bind);
    }

    /** Cuotas de una operación, para el descenso desde el detalle. */
    public static function cuotasDeOperacion($idCorte, $idOperacion)
    {
        return DB::select(
            'SELECT id_cuota, id_detalle_operacion, fec_inicial_corriente, fec_final_corriente,
                    dias_mora_cuota, estado_cuota, saldo_capital, saldo_intereses, saldo_admon,
                    capital_corriente, capital_vencido, interes_corriente, interes_vencido,
                    interes_mora, capital_mes_anterior, duplicada_de
             FROM det_corte_detalle_cuota
             WHERE id_corte = ? AND id_operacion = ?
             ORDER BY id_cuota', [$idCorte, $idOperacion]);
    }

    /* ---------------------------------------------------------------------
     | Conciliación con SIESA (C-3) · lectura y captura
     |-------------------------------------------------------------------- */

    /** Estados que admite una partida. Sólo EXPLICADA la da por resuelta. */
    const ESTADOS_CONCILIACION = ['EXPLICADA', 'EN_GESTION'];

    /**
     * Partidas de C-3 con su explicación. PENDIENTE no se guarda: es la
     * ausencia de fila en det_conciliacion_explicacion, de modo que una partida
     * que aparece por primera vez tras un recálculo nace pendiente sin que nadie
     * tenga que escribirla.
     *
     * $filtros admite tipo, estado y busqueda (operación, nit o cliente).
     */
    public static function conciliacionSiesa($idCorte, $filtros = [])
    {
        $sql = "SELECT p.id_partida, p.id_operacion, p.numero_operacion, p.nit, p.cliente,
                       p.saldo_siesa, p.saldo_factoring, p.diferencia, p.tipo,
                       estado = ISNULL(e.estado, 'PENDIENTE'),
                       e.explicacion, e.id_usuario, e.fecha
                FROM det_conciliacion_partida p
                LEFT JOIN det_conciliacion_explicacion e
                       ON e.id_corte = p.id_corte AND e.numero_operacion = p.numero_operacion
                WHERE p.id_corte = ?";
        $bind = [$idCorte];

        if (!empty($filtros['tipo'])) {
            $sql .= ' AND p.tipo = ?';
            $bind[] = $filtros['tipo'];
        }
        if (!empty($filtros['estado'])) {
            $sql .= " AND ISNULL(e.estado, 'PENDIENTE') = ?";
            $bind[] = $filtros['estado'];
        }
        if (!empty($filtros['busqueda'])) {
            $sql .= ' AND (p.cliente LIKE ? OR p.nit LIKE ? OR CAST(p.numero_operacion AS varchar(20)) LIKE ?)';
            $like = '%'.$filtros['busqueda'].'%';
            $bind[] = $like; $bind[] = $like; $bind[] = $like;
        }
        $sql .= ' ORDER BY ABS(p.diferencia) DESC, p.numero_operacion';

        return DB::select($sql, $bind);
    }

    /**
     * Conteos y sumas por tipo de partida, con lo explicado y lo pendiente.
     *
     * La diferencia se devuelve dos veces, algebraica y en valor absoluto: el
     * neto solo esconde las compensaciones entre partidas grandes de signo
     * contrario, y el trabajo de conciliar es proporcional al absoluto, no al
     * neto. Las dos se calculan aquí sobre el corte completo y no en el
     * cliente sobre lo que devuelve conciliacionSiesa(), que viene filtrado por
     * la pantalla: sumar lo filtrado haría que el total cambiara según lo que
     * el usuario estuviera mirando.
     */
    public static function resumenConciliacion($idCorte)
    {
        return DB::select(
            "SELECT p.tipo, partidas = COUNT(*),
                    saldo_siesa = SUM(p.saldo_siesa),
                    saldo_factoring = SUM(p.saldo_factoring),
                    diferencia = SUM(p.diferencia),
                    diferencia_absoluta = SUM(ABS(p.diferencia)),
                    explicadas = SUM(CASE WHEN e.estado = 'EXPLICADA' THEN 1 ELSE 0 END),
                    sin_explicar = SUM(CASE WHEN e.estado = 'EXPLICADA' THEN 0 ELSE 1 END)
             FROM det_conciliacion_partida p
             LEFT JOIN det_conciliacion_explicacion e
                    ON e.id_corte = p.id_corte AND e.numero_operacion = p.numero_operacion
             WHERE p.id_corte = ?
             GROUP BY p.tipo
             ORDER BY p.tipo", [$idCorte]);
    }

    /**
     * Cobertura del cruce y totales del corte completo, para las tarjetas de
     * la pantalla. Es hermana de resumenConciliacion() y no la sustituye: el
     * resumen sigue siendo una fila por tipo, que es como se arman los paneles.
     *
     * `conciliadas` es la cifra que no tiene partida y por eso hay que
     * devolverla aparte: las operaciones que cruzan y coinciden dentro de la
     * tolerancia no generan fila en det_conciliacion_partida, y son el único
     * lugar donde el usuario ve que la mayor parte del corte sí cuadra. Se
     * cuentan sobre det_deterioro_operacion, con el mismo criterio y la misma
     * tolerancia con que generarConciliacion() decide lo contrario, de modo que
     * conciliadas + DIFERENCIA + SOLO_FACTORING da el total de operaciones.
     *
     * Todo se mide sobre el corte completo, nunca sumando lo que devuelve
     * conciliacionSiesa(), que viene filtrado por la pantalla: un total armado
     * sobre lo filtrado cambiaría según lo que el usuario estuviera mirando.
     */
    public static function coberturaConciliacion($idCorte)
    {
        return DB::selectOne(
            "DECLARE @idCorte int = ?;
             DECLARE @tolerancia decimal(19, 4) = ?;

             SELECT operaciones = o.operaciones,
                    cruzan = o.cruzan,
                    sin_cruce = o.operaciones - o.cruzan,
                    conciliadas = o.conciliadas,
                    partidas = p.partidas,
                    diferencia = p.diferencia,
                    diferencia_absoluta = p.diferencia_absoluta,
                    sin_explicar = p.sin_explicar
             FROM (SELECT operaciones = COUNT(*),
                          cruzan = SUM(CASE WHEN saldo_siesa IS NOT NULL THEN 1 ELSE 0 END),
                          conciliadas = SUM(CASE WHEN saldo_siesa IS NOT NULL
                              AND ABS(saldo_siesa - (capital_corriente + capital_vencido)) <= @tolerancia
                              THEN 1 ELSE 0 END)
                   FROM det_deterioro_operacion WHERE id_corte = @idCorte) o
             CROSS JOIN (SELECT partidas = COUNT(*),
                                diferencia = ISNULL(SUM(x.diferencia), 0),
                                diferencia_absoluta = ISNULL(SUM(ABS(x.diferencia)), 0),
                                sin_explicar = ISNULL(SUM(CASE WHEN e.estado = 'EXPLICADA' THEN 0 ELSE 1 END), 0)
                         FROM det_conciliacion_partida x
                         LEFT JOIN det_conciliacion_explicacion e
                                ON e.id_corte = x.id_corte AND e.numero_operacion = x.numero_operacion
                         WHERE x.id_corte = @idCorte) p",
            [$idCorte, self::TOLERANCIA_CONCILIACION]);
    }

    /**
     * Guarda la explicación de una partida. Explicar es un acto contable con
     * autor: queda en bitácora igual que la marcación de una suspensión.
     *
     * La llave es (corte, número de operación) y no el identity de la partida,
     * porque el identity cambia en cada recálculo del corte y la explicación
     * tiene que sobrevivirlo.
     */
    public static function explicarPartida($idCorte, $numeroOperacion, $estado, $explicacion, $idUsuario)
    {
        $abierto = self::corteAbierto($idCorte);
        if (!$abierto['ok']) {
            return ['ok' => false, 'mensaje' => $abierto['mensaje']];
        }
        if (!in_array($estado, self::ESTADOS_CONCILIACION, true)) {
            return ['ok' => false, 'mensaje' => 'El estado de la partida no es válido.'];
        }
        $explicacion = trim((string) $explicacion);
        if ($explicacion === '') {
            return ['ok' => false, 'mensaje' => 'La explicación es obligatoria.'];
        }
        if (mb_strlen($explicacion) > 500) {
            return ['ok' => false, 'mensaje' => 'La explicación no puede superar los 500 caracteres.'];
        }

        $partida = DB::table('det_conciliacion_partida')
            ->where('id_corte', $idCorte)->where('numero_operacion', $numeroOperacion)->first();
        if (!$partida) {
            return ['ok' => false, 'mensaje' => 'La operación no es una partida de conciliación de este corte.'];
        }

        $fila = DB::table('det_conciliacion_explicacion')
            ->where('id_corte', $idCorte)->where('numero_operacion', $numeroOperacion);
        $anterior = $fila->first();

        $campos = [
            'estado' => $estado,
            'explicacion' => $explicacion,
            'id_usuario' => $idUsuario,
            'fecha' => self::ahora(),
        ];

        if ($anterior) {
            $fila->update($campos);
        } else {
            DB::table('det_conciliacion_explicacion')->insert($campos + [
                'id_corte' => $idCorte,
                'numero_operacion' => $numeroOperacion,
            ]);
        }

        self::registrarBitacora('EXPLICAR_PARTIDA', $idCorte, $numeroOperacion,
            $anterior ? $anterior->estado.': '.$anterior->explicacion : null,
            $estado.': '.$explicacion, $idUsuario);

        return ['ok' => true, 'tipo' => $partida->tipo, 'diferencia' => $partida->diferencia];
    }

    /* ---------------------------------------------------------------------
     | Suspensión de causación de intereses (D-05, D-06) · lectura
     |-------------------------------------------------------------------- */

    /** Causales de suspensión activas y vigentes hoy. */
    public static function causalesSuspension()
    {
        $hoy = date('Y-m-d');
        return DB::select(
            'SELECT id_param, codigo, descripcion, activa
             FROM det_param_causal_suspension
             WHERE activa = 1 AND vigente_desde <= ? AND (vigente_hasta IS NULL OR vigente_hasta >= ?)
             ORDER BY descripcion', [$hoy, $hoy]);
    }

    /**
     * Marcas de suspensión con su causal, cliente y estado (ACTIVA o
     * LEVANTADA). $filtros admite id_operacion, soloActivas, estado
     * (vigentes|levantadas|todas) y busqueda (operación o cliente).
     */
    public static function suspensiones($filtros = [])
    {
        $sql = "SELECT s.id_suspension, s.id_operacion, s.causal_codigo,
                       causal = c.descripcion,
                       d.id_cliente, d.cliente,
                       s.fecha_evento, s.observacion, s.soporte, s.origen,
                       s.existe_en_factoring, s.interes_congelado, s.id_corte_congelado,
                       s.id_usuario, s.fecha_registro,
                       s.fecha_reactivacion, s.id_usuario_reactivacion, s.observacion_reactivacion,
                       s.soporte_nombre, s.soporte_tipo,
                       tiene_soporte = CASE WHEN s.soporte_nombre IS NULL THEN 0 ELSE 1 END,
                       soporte_url = CASE WHEN s.soporte_nombre IS NULL THEN NULL ELSE s.soporte END,
                       estado = CASE WHEN s.fecha_reactivacion IS NULL THEN 'ACTIVA' ELSE 'LEVANTADA' END
                FROM det_suspension_interes s
                LEFT JOIN det_param_causal_suspension c ON c.codigo = s.causal_codigo
                LEFT JOIN (SELECT id_operacion, id_cliente = MAX(id_cliente), cliente = MAX(cliente)
                           FROM det_deterioro_operacion GROUP BY id_operacion) d
                       ON d.id_operacion = s.id_operacion
                WHERE 1 = 1";
        $bind = [];
        if (!empty($filtros['id_operacion'])) {
            $sql .= ' AND s.id_operacion = ?';
            $bind[] = $filtros['id_operacion'];
        }
        if (!empty($filtros['estado']) && $filtros['estado'] === 'vigentes') {
            $sql .= ' AND s.fecha_reactivacion IS NULL';
        } elseif (!empty($filtros['estado']) && $filtros['estado'] === 'levantadas') {
            $sql .= ' AND s.fecha_reactivacion IS NOT NULL';
        } elseif (!empty($filtros['soloActivas'])) {
            $sql .= ' AND s.fecha_reactivacion IS NULL';
        }
        if (!empty($filtros['busqueda'])) {
            $sql .= ' AND (d.cliente LIKE ? OR d.id_cliente LIKE ? OR CAST(s.id_operacion AS varchar(20)) LIKE ?)';
            $like = '%'.$filtros['busqueda'].'%';
            $bind[] = $like; $bind[] = $like; $bind[] = $like;
        }
        $sql .= ' ORDER BY s.fecha_evento DESC, s.id_suspension DESC';

        return DB::select($sql, $bind);
    }

    /**
     * Operaciones suspendidas de un corte: interés extraído, congelado, no
     * facturado y la reducción de base que produjo el congelamiento.
     */
    public static function suspensionesDelCorte($idCorte)
    {
        return DB::select(
            "SELECT o.id_operacion, o.id_cliente, o.cliente, o.producto,
                    s.id_suspension, s.causal_codigo, causal = c.descripcion, s.fecha_evento,
                    s.soporte_nombre, s.soporte_tipo,
                    tiene_soporte = CASE WHEN s.soporte_nombre IS NULL THEN 0 ELSE 1 END,
                    soporte_url = CASE WHEN s.soporte_nombre IS NULL THEN NULL ELSE s.soporte END,
                    interes_extraido = o.interes_vencido,
                    interes_congelado = o.interes_vencido_congelado,
                    interes_no_facturado = o.interes_no_facturado,
                    o.base_deterioro, o.base_congelada, o.saldo_siesa, o.origen_base,
                    reduccion_base = o.base_deterioro - o.base_congelada
             FROM det_deterioro_operacion o
             INNER JOIN det_suspension_interes s ON s.id_suspension = o.id_suspension
             LEFT JOIN det_param_causal_suspension c ON c.codigo = s.causal_codigo
             WHERE o.id_corte = ? AND o.suspendida = 1
             ORDER BY reduccion_base DESC", [$idCorte]);
    }

    /* ---------------------------------------------------------------------
     | Suspensión de causación de intereses (D-05, D-06) · escritura
     |-------------------------------------------------------------------- */

    /**
     * Interés de la operación a la fecha del evento de suspensión (D-06),
     * leído de las facturas automáticas de interés de SIESA. Regla de
     * Contabilidad del 17 de septiembre de 2026: **si hay fecha del evento, el
     * interés son las `FAT` hasta el mes del corte del evento**, por su saldo
     * pendiente a esa fecha.
     *
     * Esa regla habla de la **factura automática de interés**, no del literal
     * `FAT`: el tipo de documento `FAT` arranca en enero de 2022 y antes de esa
     * fecha las mismas facturas viajan como `CC`, con idéntica forma de nota.
     * Implementarlo como `= 'FAT'` truncaba el interés congelado a los meses
     * posteriores a 2022 en toda marca con evento cercano a esa frontera, en
     * silencio. Por eso el filtro son los dos tipos de
     * `TIPOS_DOCTO_INTERES_SIESA`, la misma constante que separa capital de
     * interés en `sqlSaldoSiesa()`: es la misma definición de factura de
     * interés y no puede divergir en dos sitios.
     *
     * Admitir las `CC` obliga a filtrar también por `CUENTA_INTERES_SIESA`: hay
     * `CC` de capital y sólo la cuenta contable distingue unas de otras, así
     * que sin ese filtro una `CC` de capital con esa forma de nota entraría al
     * interés congelado y reduciría la base por partida doble. A las `FAT` no
     * les quita nada: están todas en esa cuenta.
     *
     * Medido el 18 de septiembre de 2026 sobre las 259 marcas vigentes con
     * fecha de evento en PRUEBAS: **41 marcas** tenían al menos una `CC` de
     * interés hasta el mes del evento —204 filas— y 30 de ellas ven subir su
     * valor, en **16.856.114,00** que antes se perdían; las otras 11 tenían
     * esas `CC` ya pagadas, con saldo cero, que es un cero real. El total
     * congelado pasa de 332.616.052,00 a **349.472.166,00**.
     * **Ninguna marca sin congelar se rescata**: las que no tienen `FAT`
     * tampoco tienen `CC`, de modo que el conteo de marcas sin valor no se
     * mueve y el cambio sólo completa las que ya tenían algo.
     *
     * El ejemplo trabajado es la operación 3266, con evento 2022-04-29: 351.531
     * por sus cuatro `FAT` de 2022 más 277.012 por tres `CC` de 2021 —las de
     * los cortes de octubre, noviembre y diciembre, todas abiertas y anteriores
     * al evento— dan 628.543 que, sumados a los 4.763.343 de saldo de SIESA,
     * dan 5.391.886: **exactamente el `valor_nominal_siesa` de la operación**.
     * Su diferencia temporaria pasa de −277.012 a cero.
     *
     * La factura es una por operación y mes, y sus notas traen las dos piezas
     * que hacen falta:
     * 'FACTURA AUTOMATICA CORTE : 31/07/2025 OPE: 7582 NOMBRE'. De ahí salen el
     * mes facturado y el número de operación, porque el consecutivo de la fila
     * es el de la factura y no el de la operación.
     *
     * La operación sale de `restoDeAnclaOpe()`, la misma expresión con que
     * `sqlSaldoSiesa()` clasifica la fila, y no de un parseo propio: hasta el
     * 23 de septiembre de 2026 aquí se exigía el literal `OPE: ` y se cortaba
     * por el primer espacio, de modo que 260 de las 31.284 filas de interés de
     * la compañía 7 quedaban fuera —254 escriben `OPE n`, `OPE-1-n` u `OPEn`, y
     * otras 6 traen el número pegado a texto, 'OPE: 569ESPE'—. Eran filas que
     * la clasificación veía como interés y el congelamiento no podía usar.
     *
     * El mes de corte admite `CORTE :`, `CORTE:` y `CORTE `, con separador '/'
     * o '-' y año de dos o cuatro dígitos. El año de dos dígitos exige el
     * estilo 3 y no el 103, que sobre `date` sólo convierte años de cuatro, por
     * eso el COALESCE de los dos. La cadena vacía se anula antes de convertir:
     * `TRY_CONVERT(date, '', 103)` no falla, devuelve 1900-01-01, y una nota con
     * 'CORTE' y sin fecha entraría como facturada en cualquier mes.
     *
     * Medido el 23 de septiembre de 2026 sobre esas 31.284 filas, el parseo
     * resuelve las dos piezas en **31.091** contra las 31.024 de antes. Las
     * variantes del corte son 31.030 `CORTE : dd/mm/aaaa`, 49 `CORTE:` con año
     * de dos o cuatro dígitos, 24 `CORTE ` con '/', 5 `CORTE : dd/mm/aa` y 3
     * `CORTE dd-mm-aaaa`.
     *
     * Las **193** que quedan fuera lo hacen por decisión de Contabilidad y no
     * se intenta cubrirlas: **158** rotulan el mes con letras ('FACTURA
     * AUTOMATICA JUNIO 30-2018 OPE-1-1107', 'INTERESES CORRIENTES CORTE NOV /
     * 16'), **20** traen mes y no operación ('FACTURA AUTOMATICA CORTE:
     * 30/11/2025') y **15** no traen ninguna de las dos ('ANULADO CON EL DOC
     * 100011505'). El descarte de esas 193 sigue siendo silencioso: si el
     * generador vuelve a escribir esas formas con saldo, el interés se
     * subestimaría sin que nada avise.
     *
     * Las 67 filas que el parseo compartido rescata son de 9 operaciones y
     * **ninguna está marcada**: las 242 marcas ya congeladas conservan su valor
     * y su suma de 349.472.166,00 al peso, medido marca a marca contra lo
     * guardado en `det_suspension_interes`. El rescate se verá cuando alguna de
     * esas 9 se suspenda; el saldo abierto que hoy suma es 16.930.379,00, casi
     * todo de la operación 569.
     *
     * El mes se compara con EOMONTH y no con el día del evento. La nota rotula
     * el corte con el último día **calendario** del mes ('CORTE : 30/04/2022')
     * mientras la factura se emite el último día **hábil**, que en varios casos
     * es exactamente la fecha del evento. Comparando por día se descartaba una
     * factura que a la fecha del evento ya estaba en el saldo abierto: medido,
     * eso dejaba 5 marcas como 'sin congelar' teniendo la `FAT` de su mes, y
     * devolvía cero en otras 3 que sí tenían interés.
     *
     * Se toma el saldo pendiente y no el total facturado porque D-06 congela el
     * interés que **permanece en el activo y en la base**; el interés que el
     * cliente ya pagó no está en el activo y no puede deteriorarse. Y se mide a
     * la fecha del evento, sumando sólo los movimientos anteriores o iguales a
     * ella, para que un abono posterior no cambie un valor que por definición
     * quedó congelado.
     *
     * Por qué se reemplazó la regla anterior. Hasta hoy el valor salía del
     * interés vencido que la operación tenía en el corte del propio módulo más
     * cercano anterior al evento, y eso dejaba sin congelar toda marca cuyo
     * evento fuera anterior al primer corte: 144 de las 260 marcas vigentes,
     * que se deterioraban con la base normal como si no estuvieran suspendidas
     * y que el control C-MARCAS venía reportando en rojo. La regla nueva no
     * depende de que el módulo tenga un corte previo, que es justo lo que le
     * faltaba.
     *
     * Devuelve nulos cuando la operación no tiene ninguna `FAT` hasta el mes del
     * evento: no se inventa un valor y la marca queda sin congelar, visible en
     * pantalla y en C-MARCAS. `id_corte_congelado` sale siempre nulo —ya no hay
     * corte de anclaje— y la columna se conserva por las marcas congeladas con
     * la regla anterior.
     */
    public static function interesALaFechaDelEvento($idOperacion, $fechaEvento)
    {
        $lote = self::interesALaFechaDelEventoEnLote([
            ['id_operacion' => $idOperacion, 'fecha_evento' => $fechaEvento],
        ]);

        return $lote[self::claveMarcaCongelada($idOperacion, $fechaEvento)];
    }

    /**
     * Llave con que viaja el resultado del lote: operación y fecha del evento.
     *
     * Acepta objeto de fecha además de cadena. Todos los llamadores de hoy
     * pasan 'Y-m-d', pero la vía anterior enlazaba la fecha directamente y
     * Laravel convertía un DateTimeInterface por su cuenta; sin esta rama, un
     * llamador nuevo que pasara un Carbon reventaría al convertir a cadena.
     */
    public static function claveMarcaCongelada($idOperacion, $fechaEvento)
    {
        if ($fechaEvento instanceof \DateTimeInterface) {
            $fechaEvento = $fechaEvento->format('Y-m-d');
        }

        return ((int) $idOperacion).'|'.substr((string) $fechaEvento, 0, 10);
    }

    /**
     * La misma regla de `interesALaFechaDelEvento()` resuelta para muchas marcas
     * en una sola consulta. Recibe una lista de ['id_operacion', 'fecha_evento']
     * y devuelve un mapa con la llave de `claveMarcaCongelada()`.
     *
     * Existe por el costo, no por comodidad. El número de operación es una
     * expresión sobre `f353_notas` y no hay índice que la sirva, así que cada
     * resolución recorre entero el conjunto de facturas de interés de la
     * compañía. Una por
     * marca son 0,5 segundos cada una y, sobre todo, una ráfaga de recorridos
     * completos contra `UNOEEARAR`, que es la base del ERP y no es nuestra: el
     * cargue inicial disparaba 260. Agrupadas, el recorrido es uno solo. Medido
     * el 17 de septiembre de 2026 sobre las 260 marcas vigentes: 94,14 segundos
     * una por una contra 0,53 agrupadas, **con resultado idéntico** —242 marcas
     * con valor y 332.616.052,00—, que es lo que permite tratarlo como puro
     * rendimiento y no como un cambio de regla.
     *
     * Admitir las `CC` y filtrar por cuenta no cambia ese orden de magnitud: el
     * JOIN nuevo es contra `t253_co_auxiliares`, que es pequeña. Medido el 18
     * de septiembre de 2026 sobre las mismas 259 marcas, el lote pasa de 0,57 a
     * 0,58-0,60 segundos (0,78 la primera pasada, en frío).
     *
     * `interesALaFechaDelEvento()` delega aquí con un solo elemento, a propósito:
     * la regla vive escrita una sola vez y las dos vías no pueden divergir.
     *
     * Se trocea porque SQL Server admite 2.100 parámetros por lote y cada marca
     * gasta dos.
     */
    public static function interesALaFechaDelEventoEnLote(array $marcas)
    {
        $resultado = [];
        $pendientes = [];

        foreach ($marcas as $m) {
            $clave = self::claveMarcaCongelada($m['id_operacion'], $m['fecha_evento']);
            // Sin facturas no hay de dónde: nulo, no cero. Un cero diría que el
            // interés estaba en cero al evento, que es una afirmación distinta y
            // haría que la marca se aplicara con base equivocada en vez de quedar
            // señalada. El saldo en cero de una FAT ya pagada sí es un cero real.
            $resultado[$clave] = ['interes_congelado' => null, 'id_corte_congelado' => null, 'facturas_fat' => 0];
            // La fecha se toma de la llave, ya normalizada a 'Y-m-d', para que
            // lo que se enlaza y lo que indexa el mapa no puedan discrepar.
            $pendientes[$clave] = [(int) $m['id_operacion'], substr($clave, strpos($clave, '|') + 1)];
        }

        if (!$pendientes) {
            return $resultado;
        }

        $siesa = self::origenSiesa();
        $tiposInteres = self::tiposDoctoInteresSiesa();
        $cuentaInteres = self::CUENTA_INTERES_SIESA;
        $restoNota = self::restoDeAnclaOpe('s.f353_notas');
        $operacionNota = self::operacionDeRestoOpe('a.resto');

        foreach (array_chunk($pendientes, 500) as $trozo) {
            $valores = implode(', ', array_fill(0, count($trozo), '(?, ?)'));
            $bindings = [config('services.siesa.id_cia')];
            foreach ($trozo as $par) {
                $bindings[] = $par[0];
                $bindings[] = $par[1];
            }

            $filas = DB::select(
                "DECLARE @cia smallint = ?;

                 WITH marcas AS (
                    SELECT operacion = CONVERT(int, v.op), evento = CONVERT(date, v.ev)
                    FROM (VALUES {$valores}) v(op, ev)
                 ),
                 facturas AS (
                    SELECT s.f353_rowid, b.operacion, c.mes_facturado
                    FROM {$siesa}t353_co_saldo_abierto s
                    LEFT JOIN {$siesa}t253_co_auxiliares aux
                           ON aux.f253_rowid = s.f353_rowid_auxiliar
                    CROSS APPLY (SELECT resto = {$restoNota},
                                        corte = LTRIM(REPLACE(SUBSTRING(s.f353_notas,
                                            NULLIF(CHARINDEX('CORTE', s.f353_notas), 0) + 5, 14), ':', ' '))) a
                    CROSS APPLY (SELECT operacion = {$operacionNota},
                                        fecha = NULLIF(LEFT(a.corte, CHARINDEX(' ', a.corte + ' ') - 1), '')) b
                    CROSS APPLY (SELECT mes_facturado = COALESCE(TRY_CONVERT(date, b.fecha, 103),
                                                                 TRY_CONVERT(date, b.fecha, 3))) c
                    WHERE s.f353_id_cia = @cia
                      AND s.f353_id_tipo_docto_cruce IN ({$tiposInteres})
                      AND aux.f253_id = '{$cuentaInteres}'
                      AND b.operacion IS NOT NULL
                      AND c.mes_facturado IS NOT NULL
                 )
                 SELECT k.operacion, evento = CONVERT(varchar(10), k.evento, 23),
                        facturas = COUNT(DISTINCT f.f353_rowid),
                        interes = ISNULL(SUM(ISNULL(m.f354_valor_db, 0) - ISNULL(m.f354_valor_cr, 0)), 0)
                 FROM marcas k
                 LEFT JOIN facturas f
                        ON f.operacion = k.operacion
                       AND f.mes_facturado <= EOMONTH(k.evento)
                 LEFT JOIN {$siesa}t354_co_mov_saldo_abierto m
                        ON m.f354_rowid_sa = f.f353_rowid
                       AND m.f354_fecha < DATEADD(day, 1, k.evento)
                 GROUP BY k.operacion, k.evento",
                $bindings);

            foreach ($filas as $fila) {
                if ((int) $fila->facturas === 0) {
                    continue;
                }
                $resultado[self::claveMarcaCongelada($fila->operacion, $fila->evento)] = [
                    'interes_congelado' => $fila->interes,
                    'id_corte_congelado' => null,
                    'facturas_fat' => (int) $fila->facturas,
                ];
            }
        }

        return $resultado;
    }

    /**
     * Fecha del último documento tipo FAT del cliente en SIESA (D-14). Sólo
     * lectura (D-13): ninguna escritura contra UNOEEARAR, por ningún motivo.
     * Devuelve null si el cliente no tiene factura FAT.
     */
    public static function fechaUltimaFacturaSiesa($cedula)
    {
        // Algunas cedulas del archivo vienen como NIT con digito de verificacion
        // ('900519994-1') y f200_nit lo guarda sin el. Se intenta primero tal
        // cual y luego sin el digito, en vez de normalizar a ciegas: un NIT que
        // legitimamente llevara guion se perderia.
        $candidatos = [(string) $cedula];
        if (strpos((string) $cedula, '-') !== false) {
            $candidatos[] = explode('-', (string) $cedula)[0];
        }
        foreach ($candidatos as $nit) {
            $fecha = self::fechaUltimaFacturaSiesaExacta($nit);
            if ($fecha) {
                return $fecha;
            }
        }
        return null;
    }

    /**
     * FAT es la facturacion automatica de intereses que genera Factoring: la
     * ultima FAT del cliente es el ultimo mes en que se le causaron intereses,
     * y por eso es la fecha del evento de suspension. FEX es otra cosa y no
     * cuenta, aunque sea mas numeroso en SIESA (confirmado el 2026-09-03).
     */
    private static function fechaUltimaFacturaSiesaExacta($nit)
    {
        $fila = DB::connection('unoeearar')->selectOne(
            "SELECT TOP 1 dc.f350_fecha
             FROM t350_co_docto_contable dc
             JOIN t200_mm_terceros t ON dc.f350_rowid_tercero = t.f200_rowid AND t.f200_id_cia = ?
             WHERE t.f200_nit = ? AND dc.f350_id_tipo_docto = 'FAT'
             ORDER BY dc.f350_rowid DESC",
            // (string) deliberado: f200_nit es varchar y enlazar un entero obliga
            // a SQL Server a convertir toda la columna, que desborda con NITs de
            // 11 digitos y ademas descarta el indice.
            [config('services.siesa.id_cia'), (string) $nit]
        );

        return $fila ? date('Y-m-d', strtotime($fila->f350_fecha)) : null;
    }

    /** True si el corte más reciente de la serie ya está calculado o cerrado. */
    private static function corteMasRecienteYaCalculado()
    {
        $corte = DB::table('det_corte')->orderByDesc('fecha_corte')->first();
        return $corte && $corte->estado !== self::ESTADO_ABIERTO;
    }

    /**
     * Tipos admitidos del soporte adjunto y su extensión en disco. El mapa es
     * de ida y vuelta: el nombre físico se arma con la extensión al guardar y
     * se reconstruye desde soporte_tipo al servir, de modo que no hace falta
     * guardar el nombre en disco ni buscarlo en la carpeta. Un tipo fuera del
     * mapa se rechaza: nada que venga del cliente decide dónde se escribe.
     *
     * Las extensiones son canónicas —jpeg se normaliza a jpg— para que el mapa
     * sea invertible: dos claves con el mismo tipo harían que el archivo se
     * escribiera con una extensión y se buscara con la otra.
     */
    const SOPORTE_SUSPENSION_TIPOS = [
        'pdf' => 'application/pdf',
        'jpg' => 'image/jpeg',
        'png' => 'image/png',
    ];

    /** Carpeta del soporte: fuera de public/ y bloqueada por .htaccess, igual que project/folios. */
    private static function carpetaSoportesSuspension()
    {
        return base_path('soportes-suspension');
    }

    private static function rutaSoporteSuspension($idSuspension, $extension)
    {
        return self::carpetaSoportesSuspension().DIRECTORY_SEPARATOR.$idSuspension.'.'.$extension;
    }

    /**
     * Soporte adjunto de una marca, para servirlo por la ruta autenticada.
     * Devuelve null cuando la marca no existe, no tiene adjunto —las del
     * cargue inicial guardan texto en `soporte` y no tienen soporte_nombre— o
     * el archivo no está en disco.
     */
    public static function soporteSuspension($idSuspension)
    {
        $marca = DB::table('det_suspension_interes')->where('id_suspension', $idSuspension)->first();
        if (!$marca || $marca->soporte_nombre === null) {
            return null;
        }

        $extension = array_search($marca->soporte_tipo, self::SOPORTE_SUSPENSION_TIPOS, true);
        if ($extension === false) {
            return null;
        }
        $ruta = self::rutaSoporteSuspension($idSuspension, $extension);
        if (!is_file($ruta)) {
            return null;
        }

        return ['ruta' => $ruta, 'nombre' => $marca->soporte_nombre, 'tipo' => $marca->soporte_tipo];
    }

    /**
     * Mueve el adjunto a la carpeta protegida con el nombre derivado del
     * identificador de la marca. Nunca pisa un archivo existente: el soporte es
     * evidencia contable y no se sobreescribe en silencio.
     */
    private static function guardarSoporteSuspension($idSuspension, $archivo)
    {
        $extension = strtolower($archivo->extension() ?: $archivo->getClientOriginalExtension());
        if ($extension === 'jpeg') {
            $extension = 'jpg';
        }
        if (!isset(self::SOPORTE_SUSPENSION_TIPOS[$extension])) {
            throw new \RuntimeException('El soporte debe ser PDF o imagen escaneada (jpg, jpeg, png).');
        }

        $carpeta = self::carpetaSoportesSuspension();
        if (!is_dir($carpeta)) {
            mkdir($carpeta, 0755, true);
        }

        $ruta = self::rutaSoporteSuspension($idSuspension, $extension);
        if (is_file($ruta)) {
            throw new \RuntimeException('Ya existe un soporte archivado para esa marca.');
        }
        $archivo->move($carpeta, basename($ruta));

        return ['ruta' => $ruta, 'tipo' => self::SOPORTE_SUSPENSION_TIPOS[$extension]];
    }

    /**
     * Registra una marca de suspensión (D-05). No toca ningún corte ya
     * calculado: el efecto se materializa cuando ese corte se recalcula.
     *
     * $archivoSoporte es el adjunto opcional (UploadedFile). La marca y su
     * soporte se escriben dentro de una transacción y el archivo se mueve
     * dentro de ella: si el movimiento falla, la marca no queda creada, y si
     * falla la escritura posterior se borra el archivo ya movido. Nunca queda
     * una marca sin el soporte que el usuario creyó adjuntar.
     */
    public static function marcarSuspension($idOperacion, $causal, $fechaEvento, $observacion, $archivoSoporte, $idUsuario)
    {
        $hoy = date('Y-m-d');
        $causalValida = DB::table('det_param_causal_suspension')
            ->where('codigo', $causal)->where('activa', 1)
            ->where('vigente_desde', '<=', $hoy)
            ->where(function ($q) use ($hoy) {
                $q->whereNull('vigente_hasta')->orWhere('vigente_hasta', '>=', $hoy);
            })->exists();
        if (!$causalValida) {
            return ['ok' => false, 'mensaje' => 'La causal no existe o no está activa hoy.'];
        }

        $yaActiva = DB::table('det_suspension_interes')
            ->where('id_operacion', $idOperacion)->whereNull('fecha_reactivacion')->exists();
        if ($yaActiva) {
            return ['ok' => false, 'mensaje' => 'La operación ya tiene una marca de suspensión activa: levántela antes de registrar una nueva.'];
        }

        $congelado = self::interesALaFechaDelEvento($idOperacion, $fechaEvento);

        $ultimoCorte = DB::table('det_corte')->orderByDesc('fecha_corte')->first();
        $existeEnFactoring = $ultimoCorte
            ? DB::table('det_deterioro_operacion')->where('id_corte', $ultimoCorte->id_corte)
                ->where('id_operacion', $idOperacion)->exists()
            : true;

        $movido = null;
        DB::beginTransaction();
        try {
            $idSuspension = DB::table('det_suspension_interes')->insertGetId([
                'id_operacion' => $idOperacion,
                'causal_codigo' => $causal,
                'fecha_evento' => $fechaEvento,
                'observacion' => $observacion,
                'origen' => 'MANUAL',
                'existe_en_factoring' => $existeEnFactoring,
                'interes_congelado' => $congelado['interes_congelado'],
                'id_corte_congelado' => $congelado['id_corte_congelado'],
                'id_usuario' => $idUsuario,
                'fecha_registro' => self::ahora(),
            ], 'id_suspension');

            if ($archivoSoporte) {
                $movido = self::guardarSoporteSuspension($idSuspension, $archivoSoporte);
                DB::table('det_suspension_interes')->where('id_suspension', $idSuspension)->update([
                    // Se guarda la ruta autenticada (no la física): la carpeta
                    // está bloqueada por .htaccess, igual que project/folios.
                    'soporte' => route('deterioro-ver-soporte', ['idSuspension' => $idSuspension]),
                    'soporte_nombre' => $archivoSoporte->getClientOriginalName(),
                    'soporte_tipo' => $movido['tipo'],
                ]);
            }

            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();
            if ($movido && is_file($movido['ruta'])) {
                unlink($movido['ruta']);
            }
            return ['ok' => false, 'mensaje' => 'No se pudo guardar la marca con su soporte: '.$e->getMessage()];
        }

        self::registrarBitacora('MARCAR_SUSPENSION', $congelado['id_corte_congelado'], $idOperacion, null,
            'causal ' . $causal . ', evento ' . $fechaEvento . ', congelado '
            . ($congelado['interes_congelado'] !== null
                ? $congelado['interes_congelado'] . ' desde ' . $congelado['facturas_fat'] . ' FAT'
                : 'sin congelar, sin FAT hasta el mes del evento')
            . ($archivoSoporte ? ', soporte ' . $archivoSoporte->getClientOriginalName() : ', sin soporte'), $idUsuario);

        return [
            'ok' => true,
            'idSuspension' => $idSuspension,
            'congelado' => $congelado['interes_congelado'] !== null,
            'interesCongelado' => $congelado['interes_congelado'],
            'facturasFat' => $congelado['facturas_fat'],
            'corteCalculado' => self::corteMasRecienteYaCalculado(),
        ];
    }

    /**
     * Upsert idempotente de una marca de suspensión del cargue inicial
     * (D-14). La llave es la operación con marca activa: si ya existe,
     * actualiza sus campos en sitio; si no, inserta una nueva con
     * origen = CARGUE_INICIAL. Nunca crea una segunda marca activa para la
     * misma operación, que además el índice único filtrado impediría.
     */
    public static function registrarSuspensionCargueInicial($datos)
    {
        $activa = DB::table('det_suspension_interes')
            ->where('id_operacion', $datos['id_operacion'])->whereNull('fecha_reactivacion')->first();

        $campos = [
            'causal_codigo' => $datos['causal'],
            'fecha_evento' => $datos['fecha_evento'],
            'observacion' => $datos['observacion'],
            'soporte' => $datos['soporte'],
            'origen' => 'CARGUE_INICIAL',
            'existe_en_factoring' => $datos['existe_en_factoring'],
            'interes_congelado' => $datos['interes_congelado'],
            'id_corte_congelado' => $datos['id_corte_congelado'],
        ];

        if ($activa) {
            // Un cargue masivo no destruye la evidencia de un acto manual: si la
            // marca ya tiene adjunto, `soporte` conserva su ruta autenticada y la
            // referencia del archivo no se pisa con el texto del CSV. Lo demás
            // -causal, fecha, observación- sí se actualiza. Escribir el texto
            // dejaría soporte_nombre poblado apuntando a algo que no es una URL y
            // el archivo huérfano en disco.
            if ($activa->soporte_nombre !== null) {
                unset($campos['soporte']);
            }
            DB::table('det_suspension_interes')->where('id_suspension', $activa->id_suspension)->update($campos);
            return 'actualizada';
        }

        // Marca nueva: texto libre en `soporte` y sin adjunto, que es lo que
        // trae el archivo de Contabilidad. Las dos columnas van explícitas para
        // que la invariante -soporte_nombre nulo si y solo si `soporte` no es
        // una referencia a archivo- quede escrita y no dependa del default.
        DB::table('det_suspension_interes')->insert($campos + [
            'id_operacion' => $datos['id_operacion'],
            'soporte_nombre' => null,
            'soporte_tipo' => null,
            'id_usuario' => 0,
            'fecha_registro' => self::ahora(),
        ]);
        return 'creada';
    }

    /**
     * Levanta una marca activa. No borra la fila: el histórico se conserva.
     */
    public static function levantarSuspension($idSuspension, $observacion, $idUsuario)
    {
        $marca = DB::table('det_suspension_interes')->where('id_suspension', $idSuspension)->first();
        if (!$marca) {
            return ['ok' => false, 'mensaje' => 'La marca de suspensión no existe.'];
        }
        if ($marca->fecha_reactivacion !== null) {
            return ['ok' => false, 'mensaje' => 'Esa marca ya está levantada.'];
        }

        DB::table('det_suspension_interes')->where('id_suspension', $idSuspension)->update([
            'fecha_reactivacion' => self::ahora(),
            'id_usuario_reactivacion' => $idUsuario,
            'observacion_reactivacion' => $observacion,
        ]);

        self::registrarBitacora('LEVANTAR_SUSPENSION', $marca->id_corte_congelado, $marca->id_operacion,
            'ACTIVA', $observacion, $idUsuario);

        return ['ok' => true, 'corteCalculado' => self::corteMasRecienteYaCalculado()];
    }

    /**
     * Operaciones del corte con mora alta (rangos E y F) sin marca de
     * suspensión activa, para revisión manual. No es una acción automática.
     */
    public static function candidatasSuspension($idCorte)
    {
        return DB::select(
            "SELECT o.id_operacion, o.id_cliente, o.cliente, o.producto, o.nom_operacion,
                    rango = o.calificacion_abc, o.dias_mora_operacion,
                    o.capital_vencido, o.interes_vencido, o.base_deterioro, o.deterioro_contable
             FROM det_deterioro_operacion o
             WHERE o.id_corte = ? AND o.rango_codigo IN ('E', 'F')
               AND NOT EXISTS (SELECT 1 FROM det_suspension_interes s
                               WHERE s.id_operacion = o.id_operacion AND s.fecha_reactivacion IS NULL)
             ORDER BY o.deterioro_contable DESC", [$idCorte]);
    }

    /* ---------------------------------------------------------------------
     | Exportables (§16) · asiento contable (§17) y hojas de transición
     |-------------------------------------------------------------------- */

    /**
     * Vocabulario del asiento. Es del módulo: lo que es política contable —qué
     * cuenta lleva cada concepto— vive en det_param_cuenta_contable, con
     * vigencias y copia congelada por corte.
     *
     * Son dos conceptos y no uno porque el asiento cambia de forma según el
     * signo: el aumento debita gasto y acredita la 1399; la liberación debita la
     * 1399 y acredita una recuperación, que normalmente es de ingreso.
     */
    const CONCEPTOS_ASIENTO = [
        'AUMENTO_DETERIORO' => 'Aumento del deterioro del período',
        'LIBERACION_DETERIORO' => 'Liberación del deterioro del período',
        'CASTIGO' => 'Castigo de cartera',
    ];

    /**
     * Cuentas del corte. Manda la copia congelada; sin ella —los cortes
     * calculados antes de esta fase— se cae a la paramétrica vigente a la fecha
     * del corte, igual que hizo la fase 7a con las causales de salida. Se usa la
     * fecha del corte y no la de hoy porque el asiento es del período del corte.
     *
     * Qué rama se toma lo decide `cuentas_congeladas` y NO que la copia tenga
     * filas. Mientras el PUC esté vacío —que es el estado de hoy y el de los
     * próximos meses— congelarParametros() copia cero filas, y preguntar por las
     * filas mandaría a la paramétrica viva a todos los cortes cerrados en ese
     * período: el día que Contabilidad sembrara las cuentas, un corte cerrado
     * hace seis meses empezaría a exportar un asiento que antes no existía, y
     * volvería a cambiar con cada corrección de vigencia. Es justo lo que la
     * copia congelada existe para impedir.
     *
     * Consecuencia querida: un corte que congeló cero cuentas no exporta asiento
     * nunca más; para incorporarlas hay que reabrirlo y recalcularlo, que es una
     * acción con permiso propio, motivo y bitácora.
     */
    public static function cuentasContables($idCorte, $corte = null)
    {
        $columnas = 'concepto, producto, cuenta_debito, cuenta_credito, descripcion';
        $corte = $corte ?: DB::table('det_corte')->where('id_corte', $idCorte)->first();
        if (!$corte) {
            return [];
        }

        if ($corte->cuentas_congeladas) {
            return DB::select(
                "SELECT $columnas FROM det_corte_param_cuenta_contable
                 WHERE id_corte = ? ORDER BY concepto, producto", [$idCorte]);
        }

        $fecha = date('Y-m-d', strtotime($corte->fecha_corte));

        return DB::select(
            "SELECT $columnas FROM det_param_cuenta_contable
             WHERE vigente_desde <= ? AND (vigente_hasta IS NULL OR vigente_hasta >= ?)
             ORDER BY concepto, producto", [$fecha, $fecha]);
    }

    /**
     * Resuelve la cuenta de un concepto: primero la fila del producto y, si no
     * existe, la general, que es la que lleva producto nulo. Devuelve null
     * cuando no hay ninguna de las dos, que es lo que hace fallar el exportable
     * en vez de dejar el asiento con cuentas en blanco.
     */
    public static function resolverCuenta($cuentas, $concepto, $producto = null)
    {
        $general = null;
        foreach ($cuentas as $c) {
            if ($c->concepto !== $concepto) {
                continue;
            }
            if ($producto !== null && $c->producto === $producto) {
                return $c;
            }
            if ($c->producto === null) {
                $general = $c;
            }
        }
        return $general;
    }

    /**
     * Ajuste del período por producto, que es el detalle del mayor auxiliar de
     * §17. Sale del comparativo contra el corte anterior, cuyo total es por
     * construcción el gasto del período de descomposicionMovimiento().
     */
    public static function lineasAjuste($idCorte)
    {
        $lineas = [];
        foreach (self::comparativoContraAnterior($idCorte, 'producto') as $p) {
            $valor = (float) $p->variacion;
            if ($valor == 0.0) {
                continue;
            }
            $lineas[] = [
                'producto' => $p->dimension,
                'concepto' => $valor > 0 ? 'AUMENTO_DETERIORO' : 'LIBERACION_DETERIORO',
                'valor' => abs($valor),
            ];
        }
        return $lineas;
    }

    /**
     * Bajas del período cuya causal cierra el acumulado fiscal (C-2). Se
     * identifican por cierra_fiscal de la paramétrica y no por un
     * `=== 'CASTIGO'` en el código, igual que hace clasificarSalida().
     */
    public static function lineasCastigo($idCorte)
    {
        $lineas = [];
        foreach (self::descomposicionBajas($idCorte) as $b) {
            if (!$b->cierra_fiscal || (float) $b->deterioro == 0.0) {
                continue;
            }
            $lineas[] = [
                'producto' => null,
                'concepto' => 'CASTIGO',
                'valor' => (float) $b->deterioro,
                'causal' => $b->causal,
                'clasificacion' => $b->clasificacion,
                'fiscal_cerrado' => (float) $b->fiscal_cerrado,
            ];
        }
        return $lineas;
    }

    /**
     * Qué conceptos y productos necesita el asiento de este corte, sin las
     * cifras. Es la pregunta que hacen las siete pantallas —«¿se puede
     * descargar el asiento?»— y se resuelve en una sola consulta.
     *
     * No reutiliza lineasAjuste() ni lineasCastigo() a propósito: ésas arman el
     * archivo y traen importes, rótulos y causales que la pantalla no necesita,
     * y descomposicionBajas() recorre además el corte anterior entero con un
     * NOT EXISTS correlacionado. Aquí se parte de det_salida_operacion, que
     * tiene una fila por baja clasificada.
     *
     * El signo del ajuste y el criterio del castigo son los mismos que usan
     * aquellos dos métodos: variación del deterioro por producto contra el corte
     * anterior, y cierra_fiscal de la causal —la congelada del corte y, si el
     * corte no la tiene, la viva—, nunca un `= 'CASTIGO'` escrito aquí.
     */
    public static function conceptosRequeridos($idCorte, $idCorteAnterior)
    {
        if (!$idCorteAnterior) {
            return [];
        }

        $agregado = "SELECT producto = ISNULL(producto, N'SIN PRODUCTO'), det = SUM(deterioro_contable)
                     FROM det_deterioro_operacion WHERE id_corte = %s
                     GROUP BY ISNULL(producto, N'SIN PRODUCTO')";

        return DB::select(
            "DECLARE @idCorte int = ?;
             DECLARE @anterior int = ?;

             SELECT concepto = CASE WHEN x.variacion > 0 THEN 'AUMENTO_DETERIORO'
                                    ELSE 'LIBERACION_DETERIORO' END,
                    producto = x.producto
             FROM (SELECT producto = ISNULL(t.producto, p.producto),
                          variacion = ISNULL(t.det, 0) - ISNULL(p.det, 0)
                   FROM (".sprintf($agregado, '@idCorte').") t
                   FULL OUTER JOIN (".sprintf($agregado, '@anterior').") p ON p.producto = t.producto) x
             WHERE x.variacion <> 0

             UNION ALL

             SELECT 'CASTIGO', CAST(NULL AS nvarchar(20))
             WHERE EXISTS (
                 SELECT 1
                 FROM det_salida_operacion s
                 INNER JOIN det_deterioro_operacion a
                         ON a.id_corte = @anterior AND a.id_operacion = s.id_operacion
                 WHERE s.id_corte = @idCorte
                   AND a.deterioro_contable <> 0
                   AND NOT EXISTS (SELECT 1 FROM det_deterioro_operacion o
                                   WHERE o.id_corte = @idCorte AND o.id_operacion = s.id_operacion)
                   AND ISNULL((SELECT MAX(CAST(c.cierra_fiscal AS int))
                               FROM det_corte_param_causal_salida c
                               WHERE c.id_corte = @idCorte AND c.codigo = s.clasificacion),
                              (SELECT MAX(CAST(v.cierra_fiscal AS int))
                               FROM det_param_causal_salida v
                               WHERE v.codigo = s.clasificacion)) = 1)",
            [$idCorte, $idCorteAnterior]);
    }

    /**
     * Conceptos del asiento de este corte que no tienen cuenta resuelta, con su
     * rótulo en castellano.
     */
    public static function conceptosSinCuenta($idCorte, $corte = null)
    {
        $corte = $corte ?: DB::table('det_corte')->where('id_corte', $idCorte)->first();
        if (!$corte) {
            return [];
        }

        $cuentas = self::cuentasContables($idCorte, $corte);
        $faltan = [];
        foreach (self::conceptosRequeridos($idCorte, $corte->id_corte_anterior) as $r) {
            if (self::resolverCuenta($cuentas, $r->concepto, $r->producto)) {
                continue;
            }
            $rotulo = self::CONCEPTOS_ASIENTO[$r->concepto];
            if ($r->producto !== null) {
                $rotulo .= ' · '.$r->producto;
            }
            $faltan[$rotulo] = true;
        }
        return array_keys($faltan);
    }

    /**
     * Si el asiento se puede generar y, si no, por qué. Es el contrato que
     * consumen las siete pantallas y el que repite el rechazo del endpoint: un
     * `faltanCuentas` vacío no alcanza, porque significaría a la vez «todo
     * resuelto, descargue» y «este corte no puede tener asiento nunca».
     *
     * `motivo` es SIN_CALCULAR, SIN_CORTE_ANTERIOR o SIN_CUENTAS, y es nulo
     * cuando el asiento está disponible. `faltanCuentas` sólo trae conceptos con
     * el motivo SIN_CUENTAS; con los otros dos el asiento es imposible por una
     * razón que ninguna cuenta arregla.
     */
    public static function disponibilidadAsiento($idCorte, $corte = null)
    {
        $vacio = ['disponible' => false, 'motivo' => null, 'mensaje' => null, 'faltanCuentas' => []];
        $corte = $corte ?: DB::table('det_corte')->where('id_corte', $idCorte)->first();
        if (!$corte) {
            return ['motivo' => 'SIN_CALCULAR', 'mensaje' => "El corte $idCorte no existe."] + $vacio;
        }

        if ($corte->estado === self::ESTADO_ABIERTO) {
            return ['motivo' => 'SIN_CALCULAR',
                'mensaje' => 'El corte todavía no está calculado: no hay asiento que generar.'] + $vacio;
        }

        if (!$corte->id_corte_anterior) {
            return ['motivo' => 'SIN_CORTE_ANTERIOR', 'mensaje' =>
                'El corte es el primero de la serie y no tiene con qué comparar: su deterioro es el saldo inicial, '
                . 'no el ajuste de un período, y registrarlo como gasto del mes sería falso.'] + $vacio;
        }

        $faltan = self::conceptosSinCuenta($idCorte, $corte);
        if ($faltan) {
            $mensaje = 'No hay cuenta contable definida para: '.implode('; ', $faltan)
                . '. Contabilidad tiene que definirlas en la paramétrica de cuentas';
            // Un corte que ya congeló sus cuentas no las vuelve a leer: sembrar
            // la paramétrica hoy no cambia lo que ese corte congeló, y decirlo
            // aquí evita que alguien siembre el PUC y espere que el asiento
            // aparezca solo.
            $mensaje .= $corte->cuentas_congeladas
                ? ', y el corte tiene que reabrirse y recalcularse para congelarlas.'
                : '.';

            return ['motivo' => 'SIN_CUENTAS', 'mensaje' => $mensaje, 'faltanCuentas' => $faltan] + $vacio;
        }

        return ['disponible' => true, 'motivo' => null, 'mensaje' => null, 'faltanCuentas' => []];
    }

    /**
     * Las cuatro salidas de §17 en un solo juego de filas planas: el ajuste del
     * período contra la 1399 con su contrapartida, el detalle por producto para
     * el mayor auxiliar, el anexo de deducción fiscal del año gravable y el
     * anexo de diferencias temporarias.
     *
     * Todas las cifras salen de consultas que ya existen. El bloque del asiento
     * es el auxiliar agrupado por concepto y par de cuentas, de modo que los dos
     * bloques suman lo mismo y ninguno se calcula por su cuenta.
     *
     * El bloque de castigos va aparte y rotulado como informativo: el deterioro
     * de una baja castigada ya está dentro del ajuste del período por producto,
     * y llevarlo al mismo comprobante lo descuadraría. Contra qué cuenta de
     * cartera se cierra, y si va en el mismo comprobante, es una definición que
     * Contabilidad no ha dado y que el módulo no supone.
     */
    public static function asientoContable($idCorte)
    {
        $corte = DB::table('det_corte')->where('id_corte', $idCorte)->first();
        $disponible = self::disponibilidadAsiento($idCorte, $corte);
        if (!$disponible['disponible']) {
            return ['ok' => false] + $disponible;
        }

        $cuentas = self::cuentasContables($idCorte, $corte);
        $movimiento = self::descomposicionMovimiento($idCorte);
        $anterior = DB::table('det_corte')->where('id_corte', $corte->id_corte_anterior)->value('fecha_corte');

        $filas = [];
        $fila = function ($seccion, $llave, $concepto, $cuenta, $naturaleza, $valor, $texto) use (&$filas) {
            $filas[] = ['seccion' => $seccion, 'llave' => $llave, 'concepto' => $concepto,
                'cuenta' => $cuenta, 'naturaleza' => $naturaleza, 'valor' => $valor, 'texto' => $texto];
        };

        $fila('CORTE', 'FECHA_CORTE', '', '', '', null, date('Y-m-d', strtotime($corte->fecha_corte)));
        $fila('CORTE', 'FECHA_ANTERIOR', '', '', '', null, date('Y-m-d', strtotime($anterior)));
        $fila('CORTE', 'ESTADO', '', '', '', null,
            $corte->estado.($corte->cerrado_con_salvedad ? ' CON SALVEDADES' : ''));
        $fila('CORTE', 'GASTO_PERIODO', '', '', '', (float) $movimiento->gasto, 'Gasto del período por deterioro');

        $ajuste = self::lineasAjuste($idCorte);
        $agrupado = [];
        foreach ($ajuste as $l) {
            $cuenta = self::resolverCuenta($cuentas, $l['concepto'], $l['producto']);
            $llave = $l['concepto'].'|'.$cuenta->cuenta_debito.'|'.$cuenta->cuenta_credito;
            if (!isset($agrupado[$llave])) {
                $agrupado[$llave] = ['concepto' => $l['concepto'], 'cuenta' => $cuenta, 'valor' => 0];
            }
            $agrupado[$llave]['valor'] += $l['valor'];

            $fila('AUXILIAR', $l['producto'], $l['concepto'], $cuenta->cuenta_debito, 'DEBITO',
                $l['valor'], $cuenta->descripcion);
            $fila('AUXILIAR', $l['producto'], $l['concepto'], $cuenta->cuenta_credito, 'CREDITO',
                $l['valor'], $cuenta->descripcion);
        }
        foreach ($agrupado as $g) {
            $fila('ASIENTO', 'TOTAL', $g['concepto'], $g['cuenta']->cuenta_debito, 'DEBITO',
                $g['valor'], self::CONCEPTOS_ASIENTO[$g['concepto']]);
            $fila('ASIENTO', 'TOTAL', $g['concepto'], $g['cuenta']->cuenta_credito, 'CREDITO',
                $g['valor'], self::CONCEPTOS_ASIENTO[$g['concepto']]);
        }

        foreach (self::lineasCastigo($idCorte) as $l) {
            $cuenta = self::resolverCuenta($cuentas, $l['concepto']);
            $texto = 'Informativo: ya incluido en el ajuste del período · '.$l['causal'];
            $fila('CASTIGO', $l['clasificacion'], $l['concepto'], $cuenta->cuenta_debito, 'DEBITO', $l['valor'], $texto);
            $fila('CASTIGO', $l['clasificacion'], $l['concepto'], $cuenta->cuenta_credito, 'CREDITO', $l['valor'], $texto);
            $fila('CASTIGO', $l['clasificacion'], 'FISCAL_ACUMULADO_CERRADO', '', '', $l['fiscal_cerrado'], $texto);
        }

        foreach (self::resumenFiscal($idCorte) as $r) {
            foreach (['BASE' => $r->base,
                      'FISCAL_INDIVIDUAL' => $r->deterioro_fiscal_individual,
                      'FISCAL_GENERAL' => $r->deterioro_fiscal_general,
                      'ACUMULADO_ANTERIOR' => $r->fiscal_acumulado_anterior,
                      'SALDO_TOPADO' => $r->saldo_topado,
                      'DEDUCCION_ANO' => $r->deduccion_fiscal_ano] as $concepto => $valor) {
                $fila('ANEXO_FISCAL', $r->rango, $concepto, '', '', (float) $valor,
                    'Año gravable '.date('Y', strtotime($corte->fecha_corte)));
            }
        }

        $tarifa = self::tarifaRenta($idCorte);
        foreach (self::resumenPorProductoRango($idCorte) as $r) {
            foreach (['DETERIORO_CONTABLE' => $r->deterioro,
                      'FISCAL_ACUMULADO' => $r->deterioro_fiscal_acumulado,
                      'DIFERENCIA_TEMPORARIA' => $r->diferencia_temporaria,
                      'DIFERIDO_ACTIVO' => $r->diferido_activo,
                      'DIFERIDO_PASIVO' => $r->diferido_pasivo] as $concepto => $valor) {
                $fila('ANEXO_DIFERIDO', $r->producto.'|'.$r->rango, $concepto, '', '', (float) $valor,
                    'Tarifa de renta '.$tarifa);
            }
        }

        return ['ok' => true, 'faltanCuentas' => [], 'mensaje' => null, 'corte' => $corte, 'filas' => $filas];
    }

    /**
     * Proyección de det_deterioro_operacion en el orden de columnas de la hoja
     * `DETERIORO` del libro (A a R), para la marcha en paralelo de la fase 8.
     * No calcula nada: sólo presenta lo que el motor ya dejó escrito.
     */
    public static function hojaDeterioro($idCorte)
    {
        return DB::select(
            "SELECT o.id_operacion, o.cliente, o.producto, o.nom_operacion,
                    capital_anterior = o.capital_mes_anterior,
                    capital = o.capital_corriente + o.capital_vencido,
                    o.capital_vencido, o.interes_vencido, o.base_deterioro,
                    o.dias_mora_operacion,
                    rango = ISNULL(pr.etiqueta, N'Corriente'),
                    clasif = CASE WHEN o.rango_codigo IS NULL THEN N'' ELSE CAST(o.rango_codigo AS nvarchar(4)) END,
                    o.deterioro_contable, o.deduccion_fiscal_ano,
                    o.deterioro_fiscal_individual, o.fiscal_acumulado_anterior,
                    o.saldo_siesa, o.saldo_topado
             FROM det_deterioro_operacion o
             LEFT JOIN det_corte_param_rango_mora pr
                    ON pr.id_corte = o.id_corte AND pr.codigo = o.rango_codigo
             WHERE o.id_corte = ?
             ORDER BY o.cliente, o.id_operacion", [$idCorte]);
    }

    /** Lo mismo para la hoja `Tabla final`, que es el consolidado por operación. */
    public static function hojaTablaFinal($idCorte)
    {
        return DB::select(
            "SELECT o.cliente, o.producto, o.nom_operacion,
                    rango = ISNULL(pr.etiqueta, N'Corriente'),
                    o.dias_mora_operacion, o.id_operacion,
                    saldo_capital = o.capital_corriente + o.capital_vencido,
                    o.capital_corriente, o.interes_corriente,
                    o.capital_vencido, o.interes_vencido, o.interes_mora,
                    o.capital_mes_anterior, o.variacion_capital
             FROM det_deterioro_operacion o
             LEFT JOIN det_corte_param_rango_mora pr
                    ON pr.id_corte = o.id_corte AND pr.codigo = o.rango_codigo
             WHERE o.id_corte = ?
             ORDER BY o.cliente, o.id_operacion", [$idCorte]);
    }

    /**
     * Hoja `1399`: la provisión fiscal acumulada de años anteriores por
     * operación, que es lo que en el libro son las hojas `1399 AÑO XXXX`.
     *
     * Se exporta det_fiscal_acumulado ENTERA y no el subconjunto que el motor
     * consumió. El acumulado de una operación que ya no está en el corte sigue
     * existiendo —la operación se recaudó, se castigó o se cerró— y las hojas
     * del libro lo traen; exportar sólo lo consumido dejaba fuera 39 filas y 117
     * millones que no son una diferencia, y quien concilie en la marcha en
     * paralelo perdería un día buscándolas.
     *
     * `en_corte` marca cuáles son: sin esa columna las dos poblaciones se
     * mezclan y el total de la hoja deja de cuadrar contra la columna P de
     * `DETERIORO`, que sí es sólo lo consumido.
     *
     * El nombre del cliente de una operación ausente se toma del corte más
     * reciente en que aparezca: no está en el acumulado y sin él la fila sería
     * un número suelto.
     */
    public static function hoja1399($idCorte)
    {
        return DB::select(
            "DECLARE @idCorte int = ?;
             DECLARE @ano int = (SELECT YEAR(fecha_corte) FROM det_corte WHERE id_corte = @idCorte);

             SELECT a.id_operacion,
                    cliente = ISNULL(o.cliente, u.cliente),
                    fiscal_acumulado_anterior = a.acumulado,
                    en_corte = CASE WHEN o.id_operacion IS NULL THEN 0 ELSE 1 END
             FROM (SELECT id_operacion, acumulado = SUM(valor_deducido)
                   FROM det_fiscal_acumulado WHERE ano_gravable < @ano
                   GROUP BY id_operacion) a
             LEFT JOIN det_deterioro_operacion o
                    ON o.id_corte = @idCorte AND o.id_operacion = a.id_operacion
             OUTER APPLY (SELECT TOP 1 d.cliente FROM det_deterioro_operacion d
                          WHERE d.id_operacion = a.id_operacion ORDER BY d.id_corte DESC) u
             ORDER BY ISNULL(o.cliente, u.cliente), a.id_operacion", [$idCorte]);
    }

    /** Rangos congelados del corte, para encabezar la matriz del resumen. */
    public static function rangosCorte($idCorte)
    {
        return DB::select(
            'SELECT codigo, etiqueta, pct_deterioro_contable, orden
             FROM det_corte_param_rango_mora WHERE id_corte = ? ORDER BY orden', [$idCorte]);
    }

    /* ---------------------------------------------------------------------
     | Utilidades
     |-------------------------------------------------------------------- */

    /** ISO 8601 con T, para que el servidor no lo lea como dd/mm/aaaa. */
    public static function ahora()
    {
        return date('Y-m-d') . 'T' . date('H:i:s');
    }

    private static function ms($desde)
    {
        return (int) round((microtime(true) - $desde) * 1000);
    }

    /**
     * Consulta de la bitácora (§18). La tabla y el registro existen desde la
     * fase 1; lo que faltaba era poder leerla.
     *
     * $filtros admite idCorte, accion, idUsuario, desde, hasta y busqueda sobre
     * la operación y los dos valores. El TOP es un tope de seguridad y no
     * paginación: la bitácora crece por corte, no por cuota, y con 500 eventos
     * ya cabe un mes completo de gestión. Se devuelve el total sin topar para
     * que la pantalla pueda decir que está viendo una parte.
     *
     * Las fechas se comparan contra el día siguiente en vez de con <=: fecha es
     * datetime y un evento de las 15:00 del día 'hasta' quedaría fuera.
     */
    public static function bitacora($filtros = [], $limite = 500)
    {
        $donde = ' WHERE 1 = 1';
        $bind = [];

        if (!empty($filtros['idCorte'])) {
            $donde .= ' AND b.id_corte = ?';
            $bind[] = (int) $filtros['idCorte'];
        }
        if (!empty($filtros['accion'])) {
            $donde .= ' AND b.accion = ?';
            $bind[] = $filtros['accion'];
        }
        if (!empty($filtros['idUsuario'])) {
            $donde .= ' AND b.id_usuario = ?';
            $bind[] = (int) $filtros['idUsuario'];
        }
        if (!empty($filtros['desde'])) {
            $donde .= ' AND b.fecha >= ?';
            $bind[] = date('Y-m-d', strtotime($filtros['desde'])).'T00:00:00';
        }
        if (!empty($filtros['hasta'])) {
            $donde .= ' AND b.fecha < ?';
            $bind[] = date('Y-m-d', strtotime($filtros['hasta'].' +1 day')).'T00:00:00';
        }
        if (!empty($filtros['busqueda'])) {
            $donde .= ' AND (CAST(b.id_operacion AS varchar(20)) LIKE ?'
                    . ' OR CAST(b.valor_anterior AS nvarchar(max)) LIKE ?'
                    . ' OR CAST(b.valor_nuevo AS nvarchar(max)) LIKE ?)';
            $like = '%'.$filtros['busqueda'].'%';
            $bind[] = $like; $bind[] = $like; $bind[] = $like;
        }

        $eventos = DB::select(
            "SELECT TOP (?) b.id_evento, b.accion, b.id_corte, fecha_corte = c.fecha_corte,
                    b.id_operacion, b.valor_anterior, b.valor_nuevo,
                    b.id_usuario, b.doc_usuario, usuario = u.nombreUsuario, b.ip, b.fecha
             FROM det_bitacora b
             LEFT JOIN det_corte c ON c.id_corte = b.id_corte
             LEFT JOIN users u ON u.idUsuario = b.id_usuario
             $donde
             ORDER BY b.fecha DESC, b.id_evento DESC",
            array_merge([(int) $limite], $bind));

        $total = DB::selectOne("SELECT eventos = COUNT(*) FROM det_bitacora b $donde", $bind);

        return ['eventos' => $eventos, 'total' => (int) $total->eventos, 'limite' => (int) $limite];
    }

    /**
     * Acciones y usuarios que la bitácora tiene de verdad, para poblar los
     * filtros. Sale del dato y no de un catálogo escrito en el cliente: cada
     * fase agrega acciones nuevas y una lista fija quedaría desactualizada sin
     * que nadie lo notara.
     */
    public static function filtrosBitacora()
    {
        return [
            'acciones' => DB::select(
                'SELECT accion, eventos = COUNT(*) FROM det_bitacora GROUP BY accion ORDER BY accion'),
            'usuarios' => DB::select(
                'SELECT b.id_usuario, usuario = MAX(u.nombreUsuario), eventos = COUNT(*)
                 FROM det_bitacora b
                 LEFT JOIN users u ON u.idUsuario = b.id_usuario
                 GROUP BY b.id_usuario ORDER BY MAX(u.nombreUsuario)'),
            'cortes' => DB::select(
                'SELECT c.id_corte, c.fecha_corte FROM det_corte c ORDER BY c.fecha_corte DESC'),
        ];
    }

    public static function registrarBitacora($accion, $idCorte, $idOperacion, $anterior, $nuevo, $idUsuario)
    {
        DB::table('det_bitacora')->insert([
            'accion' => $accion,
            'id_corte' => $idCorte,
            'id_operacion' => $idOperacion,
            'valor_anterior' => $anterior,
            'valor_nuevo' => $nuevo,
            'id_usuario' => $idUsuario,
            'doc_usuario' => DB::table('users')->where('idUsuario', $idUsuario)->value('documentoUsuario'),
            'ip' => request() ? request()->ip() : null,
            'fecha' => self::ahora(),
        ]);
    }
}
