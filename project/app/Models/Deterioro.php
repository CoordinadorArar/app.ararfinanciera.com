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

    /** Tabla origen del corte y tabla del corte de comparación, según el ambiente. */
    public static function origenCorte()
    {
        return config('database.faico').'.dbo.ResumenVigentesClientes';
    }

    public static function origenComparacion()
    {
        return config('database.faico').'.dbo.ResumenVigentesClientes1';
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

    /** Deja el corte como recién creado, para poder recalcularlo. */
    public static function limpiarCorte($idCorte)
    {
        foreach (['det_deterioro_operacion', 'det_corte_cuadre', 'det_corte_detalle_cuota',
                  'det_corte_param_rango_mora', 'det_corte_param_producto',
                  'det_corte_param_interes', 'det_corte_param_convencion',
                  'det_corte_param_fiscal', 'det_corte_param_fiscal_rango'] as $tabla) {
            DB::table($tabla)->where('id_corte', $idCorte)->delete();
        }
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
                  'det_corte_param_fiscal_rango' => ['metodo', 'rango_codigo']] as $tabla => $orden) {
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
     * Excluye administración e interés de mora.
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
                    WHERE id_corte = ?
                    GROUP BY id_corte, id_operacion
                ) a";
    }

    public static function consolidarPorOperacion($idCorte)
    {
        DB::insert(self::sqlConsolidacion(), [$idCorte]);
        return DB::table('det_deterioro_operacion')->where('id_corte', $idCorte)->count();
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
     */
    public static function sqlDeterioroContable()
    {
        return "UPDATE o SET
                    pct_contable = ISNULL(pr.pct_deterioro_contable, 0),
                    deterioro_contable = o.base_deterioro * ISNULL(pr.pct_deterioro_contable, 0)
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
     * R · saldo topado: `SI(Q>=I; I; 0) + SI(Q<I; Q; 0)` es el menor entre el
     * saldo de SIESA y la base. Mientras saldo_siesa siga nulo —lo puebla la
     * fase 6— R toma la base, que es el comportamiento neutro; al poblarse, la
     * misma expresión empieza a topar sin tocar el motor.
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
                                    THEN o.base_deterioro * ISNULL(pf.pct_anual, 0) ELSE 0 END,
                           gen = o.base_deterioro * ISNULL(pg.pct, 0),
                           p = a.acumulado,
                           r = CASE
                                    WHEN o.saldo_siesa IS NULL OR o.saldo_siesa >= o.base_deterioro
                                    THEN o.base_deterioro ELSE o.saldo_siesa END) v
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
     * año sale nulo, que es el resultado honesto. ISNULL(saldo_topado,
     * base_deterioro) repite el comportamiento neutro documentado arriba
     * mientras saldo_siesa siga sin poblarse.
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
                    ano_reversion_fiscal       = v.ano
                FROM det_deterioro_operacion o
                INNER JOIN det_corte_param_convencion pc ON pc.id_corte = o.id_corte
                LEFT JOIN det_corte_param_fiscal pf ON pf.id_corte = o.id_corte AND pf.activo = 1
                CROSS APPLY (SELECT fiscal = ISNULL(o.fiscal_acumulado_anterior,0) + ISNULL(o.deduccion_fiscal_ano,0)) f
                CROSS APPLY (SELECT anual = o.base_deterioro * ISNULL(pf.pct_anual,0),
                                    pendiente = ISNULL(o.saldo_topado, o.base_deterioro) - f.fiscal,
                                    espera = CASE WHEN o.dias_mora_operacion >= pf.dias_minimos_mora THEN 0
                                                  ELSE CEILING((pf.dias_minimos_mora - o.dias_mora_operacion) / 360.0) END) c
                CROSS APPLY (SELECT ano = CASE
                        WHEN o.base_deterioro <= 0 OR c.anual <= 0 THEN NULL
                        WHEN c.pendiente <= 0 THEN YEAR(@corte)
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
     | Paso 9 · Cuadres
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
     */
    public static function sqlResumenFiscalIndependiente()
    {
        return "SELECT individual = ISNULL(SUM(o.base_deterioro * pf.pct_anual), 0)
                FROM det_deterioro_operacion o
                INNER JOIN det_corte_param_fiscal pf
                        ON pf.id_corte = o.id_corte AND pf.activo = 1
                INNER JOIN det_corte_param_rango_mora pr
                        ON pr.id_corte = o.id_corte AND pr.codigo = o.rango_codigo
                       AND pr.dias_desde >= pf.dias_minimos_mora
                WHERE o.id_corte = ?";
    }

    /**
     * Controles de RN-12 que no dependen de SIESA. Los que la requieren
     * (AC25 y la conciliación) entran en la fase 6.
     */
    public static function verificarCuadres($idCorte)
    {
        $fechaCorte = DB::table('det_corte')->where('id_corte', $idCorte)->value('fecha_corte');

        $origen = DB::selectOne(
            'SELECT filas = COUNT(*), capital = SUM(saldo_capital), interes = SUM(saldo_intereses),
                    corriente = SUM(capital_corriente), vencido = SUM(capital_vencido),
                    icorriente = SUM(interes_corriente), ivencido = SUM(interes_vencido)
             FROM det_corte_detalle_cuota WHERE id_corte = ?', [$idCorte]);

        $oper = DB::selectOne(
            'SELECT operaciones = COUNT(*), cuotas = SUM(cuotas),
                    capital = SUM(capital_corriente + capital_vencido),
                    interes = SUM(interes_corriente + interes_vencido),
                    base = SUM(base_deterioro), deterioro = SUM(deterioro_contable)
             FROM det_deterioro_operacion WHERE id_corte = ?', [$idCorte]);

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
                    sin_proyeccion = SUM(CASE WHEN base_deterioro > 0 AND ano_reversion_fiscal IS NULL THEN 1 ELSE 0 END)
             FROM det_deterioro_operacion WHERE id_corte = ?', [$idCorte]);

        $tarifa = (float) DB::table('det_corte_param_convencion')
            ->where('id_corte', $idCorte)->value('tarifa_renta');

        $controles = [
            ['C-CUOTAS', 'Cuotas del detalle contra la suma consolidada por operación',
                $origen->filas, $oper->cuotas],
            ['C-CAPITAL', 'Capital del detalle contra el consolidado por operación',
                $origen->capital, $oper->capital],
            ['C-INTERES', 'Interés del detalle contra el consolidado por operación',
                $origen->interes, $oper->interes],
            ['C-PARTIC', 'Capital corriente más vencido contra el capital total',
                $origen->capital, $origen->corriente + $origen->vencido],
            ['C-BASE', 'Base de deterioro contra capital vencido más interés vencido',
                $oper->base, $origen->vencido + $origen->ivencido],
            ['C-FISCAL', 'Deterioro fiscal individual del detalle contra la base de los rangos que deducen por el porcentaje anual',
                $fiscal->individual, $fiscalResumen->individual],
            ['C-FISCAL-ACUM', 'Acumulado fiscal del detalle contra los años anteriores del acumulado',
                $fiscal->acumulado, $fiscalFuente->acumulado],
            ['C-FISCAL-TOPE', 'Deducción del año por encima del tope disponible',
                $fiscal->exceso, 0],
            // SUM ignora los nulos y el ISNULL fila a fila no: si alguna
            // operación quedó sin comparativo, los dos lados se separan.
            ['C-DIF-TEMP', 'Diferencia temporaria del detalle contra contable menos fiscal acumulado',
                $diferido->temporaria, $diferido->contable - $diferido->fiscal],
            // El motor multiplica y luego suma; el control suma y luego
            // multiplica: son dos caminos, no la misma cuenta escrita dos veces.
            ['C-DIFERIDO', 'Impuesto diferido del detalle contra la diferencia temporaria por la tarifa de renta',
                $diferido->impuesto, $diferido->temporaria * $tarifa],
            ['C-REVERSION', 'Operaciones con base de deterioro y sin año de reversión proyectado',
                $diferido->sin_proyeccion, 0],
        ];

        DB::table('det_corte_cuadre')->where('id_corte', $idCorte)->delete();
        $resultado = [];
        foreach ($controles as $c) {
            $diferencia = round((float) $c[2] - (float) $c[3], 4);
            $fila = [
                'id_corte' => $idCorte,
                'codigo' => $c[0],
                'descripcion' => $c[1],
                'valor_detalle' => $c[2],
                'valor_resumen' => $c[3],
                'diferencia' => $diferencia,
                'tolerancia' => 1,
                'estado' => abs($diferencia) <= 1 ? 'OK' : 'FALLA',
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

            $t = microtime(true);
            self::calcularDerivadas($idCorte, $corte->fecha_corte);
            $pasos[] = ['paso' => 'Derivadas por cuota', 'filas' => $filas, 'ms' => self::ms($t)];

            $t = microtime(true);
            $enlazadas = self::enlazarMesAnterior($idCorte, $corte->id_corte_anterior);
            $pasos[] = ['paso' => 'Enlace con el corte anterior', 'filas' => $enlazadas, 'ms' => self::ms($t)];

            $t = microtime(true);
            $operaciones = self::consolidarPorOperacion($idCorte);
            $pasos[] = ['paso' => 'Consolidación por operación', 'filas' => $operaciones, 'ms' => self::ms($t)];

            $t = microtime(true);
            self::aplicarDeterioroContable($idCorte);
            $pasos[] = ['paso' => 'Deterioro contable', 'filas' => $operaciones, 'ms' => self::ms($t)];

            $t = microtime(true);
            self::aplicarDeterioroFiscal($idCorte, $corte->fecha_corte);
            $pasos[] = ['paso' => 'Deterioro fiscal y tope', 'filas' => $operaciones, 'ms' => self::ms($t)];

            $t = microtime(true);
            self::aplicarImpuestoDiferido($idCorte, $corte->fecha_corte);
            $pasos[] = ['paso' => 'Impuesto diferido', 'filas' => $operaciones, 'ms' => self::ms($t)];

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
     | Consultas de presentación
     |-------------------------------------------------------------------- */

    public static function listarCortes()
    {
        return DB::select(
            "SELECT c.*,
                    anterior = a.fecha_corte,
                    operaciones = (SELECT COUNT(*) FROM det_deterioro_operacion o WHERE o.id_corte = c.id_corte),
                    deterioro = (SELECT SUM(deterioro_contable) FROM det_deterioro_operacion o WHERE o.id_corte = c.id_corte),
                    cuadres_total = (SELECT COUNT(*) FROM det_corte_cuadre q WHERE q.id_corte = c.id_corte),
                    cuadres_falla = (SELECT COUNT(*) FROM det_corte_cuadre q WHERE q.id_corte = c.id_corte AND q.estado <> 'OK')
             FROM det_corte c
             LEFT JOIN det_corte a ON a.id_corte = c.id_corte_anterior
             ORDER BY c.fecha_corte DESC");
    }

    public static function corte($idCorte)
    {
        return DB::selectOne('SELECT * FROM det_corte WHERE id_corte = ?', [$idCorte]);
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

    /** Serie de la diferencia temporaria por corte, hasta la fecha del corte pedido. */
    public static function evolucionDiferenciaTemporaria($idCorte, $cortes = 24)
    {
        return DB::select(
            "SELECT TOP (?) c.id_corte, c.fecha_corte,
                    operaciones = COUNT(o.id_operacion),
                    contable = SUM(o.deterioro_contable),
                    fiscal = SUM(o.deterioro_fiscal_acumulado),
                    temporaria = SUM(o.diferencia_temporaria),
                    diferido = SUM(o.impuesto_diferido_activo)
             FROM det_corte c
             INNER JOIN det_deterioro_operacion o ON o.id_corte = c.id_corte
             WHERE c.fecha_corte <= (SELECT fecha_corte FROM det_corte WHERE id_corte = ?)
             GROUP BY c.id_corte, c.fecha_corte
             ORDER BY c.fecha_corte DESC", [(int) $cortes, $idCorte]);
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
                        WHEN o.ano_reversion_fiscal IS NULL THEN 'SIN_PROYECCION'
                        WHEN o.ano_reversion_fiscal <= YEAR(c.fecha_corte) THEN 'ANO_CORRIENTE'
                        WHEN o.ano_reversion_fiscal = YEAR(c.fecha_corte) + 1 THEN 'ANO_SIGUIENTE'
                        ELSE 'POSTERIOR' END
             ORDER BY o.ano_reversion_fiscal", [$idCorte]);
    }

    public static function cuadres($idCorte)
    {
        return DB::select('SELECT * FROM det_corte_cuadre WHERE id_corte = ? ORDER BY codigo', [$idCorte]);
    }

    /** Detalle por operación. Devuelve datos, no HTML: son ~2.100 filas. */
    public static function detalleOperaciones($idCorte, $filtros = [])
    {
        $sql = "SELECT id_operacion, id_cliente, cliente, producto, nom_operacion,
                       fec_inicial_mora, cuotas, dias_mora_operacion,
                       rango = ISNULL(calificacion_abc, 'Corriente'),
                       capital_corriente, capital_vencido, interes_corriente, interes_vencido,
                       interes_mora, base_deterioro, pct_contable, deterioro_contable,
                       deterioro_fiscal_individual, deterioro_fiscal_general,
                       fiscal_acumulado_anterior, saldo_topado, deduccion_fiscal_ano,
                       deterioro_fiscal_acumulado, diferencia_temporaria,
                       impuesto_diferido_activo, ano_reversion_fiscal,
                       capital_mes_anterior, variacion_capital
                FROM det_deterioro_operacion WHERE id_corte = ?";
        $bind = [$idCorte];

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
                    interes_mora, capital_mes_anterior
             FROM det_corte_detalle_cuota
             WHERE id_corte = ? AND id_operacion = ?
             ORDER BY id_cuota', [$idCorte, $idOperacion]);
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
