<?php

namespace App\Support;

use App\Models\Deterioro;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Exportables del módulo de deterioro (§16).
 *
 * Sólo lectura sobre un corte ya calculado: arma archivos a partir de lo que el
 * motor dejó escrito y no calcula ninguna cifra propia. Un corte cerrado exporta
 * lo mismo dentro de seis meses porque todo lo que se lee —incluidas las cuentas
 * del asiento— sale de lo congelado dentro del corte.
 *
 * Los importes se escriben con la precisión con que están guardados, decimal(19,4).
 * Ningún exportable redondea.
 */
class DeterioroExportador
{
    /** Filas por lote al volcar una grilla, para no armar el arreglo completo. */
    const LOTE = 500;

    /**
     * `deterioro_<salida>_<AAAA-MM-DD><sufijos>.<ext>`, en minúsculas y sin
     * tildes ni espacios. La fecha es la del corte y nunca la de descarga: así
     * el archivo ordena igual alfabética que cronológicamente.
     *
     * El estado va en el nombre para que el archivo declare su calidad sin
     * abrirlo: un corte calculado es preliminar y uno cerrado con salvedades lo
     * dice; un cierre limpio no agrega nada.
     */
    public static function nombreArchivo($salida, $corte, $extension, $sufijos = [])
    {
        $partes = ['deterioro', $salida, date('Y-m-d', strtotime($corte->fecha_corte))];

        if ($corte->estado === Deterioro::ESTADO_CERRADO) {
            if ($corte->cerrado_con_salvedad) {
                $partes[] = 'con-salvedades';
            }
        } else {
            $partes[] = 'preliminar';
        }

        foreach ($sufijos as $s) {
            $partes[] = $s;
        }

        return implode('_', $partes).'.'.$extension;
    }

    /**
     * Sufijos del detalle: los filtros aplicados con valores legibles, y a
     * partir de tres colapsados en uno solo. El texto libre de búsqueda nunca
     * entra al nombre del archivo.
     */
    public static function sufijosDetalle($filtros)
    {
        $sufijos = [];
        if (!empty($filtros['producto'])) {
            $sufijos[] = self::normalizar($filtros['producto']);
        }
        if (!empty($filtros['rango'])) {
            $sufijos[] = 'rango-'.self::normalizar($filtros['rango']);
        }
        foreach (['soloDeterioro' => 'con-deterioro', 'soloDeduccion' => 'con-deduccion',
                  'soloTopadas' => 'topadas', 'soloPasivo' => 'pasivo',
                  'soloDuplicadas' => 'con-repetidas',
                  'soloProrroga' => 'con-prorroga'] as $filtro => $etiqueta) {
            if (!empty($filtros[$filtro])) {
                $sufijos[] = $etiqueta;
            }
        }

        return count($sufijos) > 2 ? ['filtrado'] : $sufijos;
    }

    private static function normalizar($texto)
    {
        $texto = strtr((string) $texto, [
            'á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u', 'ñ' => 'n', 'ü' => 'u',
            'Á' => 'a', 'É' => 'e', 'Í' => 'i', 'Ó' => 'o', 'Ú' => 'u', 'Ñ' => 'n', 'Ü' => 'u',
        ]);
        $texto = preg_replace('/[^A-Za-z0-9]+/', '-', $texto);

        return strtolower(trim($texto, '-'));
    }

    /** Descarga el libro sin materializarlo en disco ni precalcular fórmulas. */
    public static function descargar(Spreadsheet $libro, $nombre)
    {
        $escritor = new Xlsx($libro);
        $escritor->setPreCalculateFormulas(false);

        return new StreamedResponse(function () use ($escritor, $libro) {
            $escritor->save('php://output');
            $libro->disconnectWorksheets();
        }, 200, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Content-Disposition' => 'attachment; filename="'.$nombre.'"',
            'Cache-Control' => 'no-store, no-cache',
        ]);
    }

    /**
     * Vuelca una grilla en la hoja, escribiendo de a lotes en vez de armar un
     * arreglo con todas las filas ya traducidas.
     *
     * No convierte el exportable en un volcado en streaming y no hay que
     * pretender que sí: el recordset llega completo desde el modelo y
     * PhpSpreadsheet mantiene el libro en memoria hasta que lo escribe. Sobre el
     * volumen real —2.106 operaciones— eso son 86 MB y seis segundos.
     */
    private static function volcar($hoja, $filas, $columnas, $desde)
    {
        $lote = [];
        foreach ($filas as $f) {
            $linea = [];
            foreach ($columnas as $columna) {
                $linea[] = is_callable($columna) ? $columna($f) : $f->{$columna};
            }
            $lote[] = $linea;
            if (count($lote) >= self::LOTE) {
                $hoja->fromArray($lote, null, 'A'.$desde);
                $desde += count($lote);
                $lote = [];
            }
        }
        if ($lote) {
            $hoja->fromArray($lote, null, 'A'.$desde);
            $desde += count($lote);
        }

        return $desde;
    }

    /**
     * Excel de transición: las hojas de RESULTADO del libro —`DETERIORO`,
     * `Tabla final`, `DIFERENCIAS` y `1399`— con sus columnas y en su orden, para
     * que la marcha en paralelo de la fase 8 se pueda contrastar sin transcribir
     * a mano. No replica `BD` ni `Mes_anterior`, que son insumo y no resultado.
     */
    public static function libroTransicion($idCorte)
    {
        $corte = Deterioro::corte($idCorte);
        $libro = new Spreadsheet();
        $libro->getProperties()->setTitle('Deterioro de cartera '.$corte->fecha_corte);

        self::hojaDeterioro($libro->getActiveSheet(), $idCorte, $corte);
        self::hojaTablaFinal($libro->createSheet(), $idCorte);
        self::hojaDiferencias($libro->createSheet(), $idCorte);
        self::hoja1399($libro->createSheet(), $idCorte);
        $libro->setActiveSheetIndex(0);

        return $libro;
    }

    /**
     * Hoja `DETERIORO`: el detalle en las columnas A a R del libro y, a partir
     * de la columna T, el bloque resumen que allí ocupa `T3:AC35`.
     *
     * La matriz lleva las seis columnas de rango del libro y no la de Corriente,
     * igual que allí: las operaciones corrientes no entran a ningún `SUMIFS` del
     * bloque porque su clasificación no es una de las seis letras.
     *
     * DESDE AGOSTO DE 2026 LA COLUMNA I NO ES G + H, y no es un descuadre. La
     * columna I —«VALORES EN MORA CAPITAL/INTERES»— es `base_deterioro`, que
     * ahora incluye la prórroga vencida de SIESA, mientras G y H siguen siendo
     * el capital y el interés vencidos que entregó factoring. La matriz
     * `CARTERA <producto>` del bloque T tampoco la lleva: suma
     * `capital_vencido + interes_vencido`. Son 82.170.273 en cinco operaciones
     * —2133, 2316, 1276, 1238 y 2136— en el corte de agosto de 2026.
     *
     * La hoja se deja así a propósito: es réplica fiel del libro heredado en las
     * columnas A a R, el libro no tiene columna de prórroga y meterle una
     * desplazaría el mapa de totales con el que se hace la marcha en paralelo.
     * Quien necesite la cifra desglosada la tiene en el exportable del detalle,
     * que sí lleva la columna «PRORROGA VENCIDA SIESA», y en el control
     * C-SIESA-PRORROGA.
     */
    private static function hojaDeterioro($hoja, $idCorte, $corte)
    {
        $hoja->setTitle('DETERIORO');
        $fecha = date('d/m/Y', strtotime($corte->fecha_corte));

        $rotulo = 'DETERIORO DE CARTERA A '.$fecha;
        if ($corte->estado !== Deterioro::ESTADO_CERRADO) {
            $rotulo .= ' · PRELIMINAR';
        } elseif ($corte->cerrado_con_salvedad) {
            $rotulo .= ' · CERRADO CON SALVEDADES';
        }
        $hoja->setCellValue('T2', $rotulo);

        $movimiento = Deterioro::descomposicionMovimiento($idCorte);

        $hoja->fromArray([['OPERACIÓN', 'CLIENTE', 'PRODUCTO', 'SUBPRODUCTO',
            'SALDO CAPITAL '.self::mes($movimiento->fecha_anterior),
            'SALDO CAPITAL '.self::mes($corte->fecha_corte),
            'CAPITAL VENCIDO', 'INTERES VENCIDO',
            'VALORES EN MORA CAPITAL/INTERES', 'DIAS EN MORA', 'RANGOS', 'CLASIF.',
            'DET. X CLIENTE CONT.', 'DEDUCCION FISCAL AÑO', 'PROV. X CLIENTE FISCAL',
            'FISCAL 1399', 'CARTERA SIESA', 'SALDO TOPADO']], null, 'A5');

        $fin = self::volcar($hoja, Deterioro::hojaDeterioro($idCorte), [
            'id_operacion', 'cliente', 'producto', 'nom_operacion',
            'capital_anterior', 'capital', 'capital_vencido', 'interes_vencido',
            'base_deterioro', 'dias_mora_operacion', 'rango',
            function ($f) { return trim((string) $f->clasif); },
            'deterioro_contable', 'deduccion_fiscal_ano', 'deterioro_fiscal_individual',
            'fiscal_acumulado_anterior', 'saldo_siesa', 'saldo_topado',
        ], 6);

        self::resumenTransicion($hoja, $idCorte, $fecha, $fin, $movimiento);
    }

    /** Rótulo del mes, como lo titula el libro sus columnas de saldo. */
    private static function mes($fecha)
    {
        if (!$fecha) {
            return 'MES ANTERIOR';
        }
        $meses = ['', 'ENERO', 'FEBRERO', 'MARZO', 'ABRIL', 'MAYO', 'JUNIO',
            'JULIO', 'AGOSTO', 'SEPTIEMBRE', 'OCTUBRE', 'NOVIEMBRE', 'DICIEMBRE'];

        return $meses[(int) date('n', strtotime($fecha))].' '.date('Y', strtotime($fecha));
    }

    /** Bloque `T3:AC35` del libro, armado con las consultas del módulo. */
    private static function resumenTransicion($hoja, $idCorte, $fecha, $filaTotales, $movimiento)
    {
        $rangos = Deterioro::rangosCorte($idCorte);
        $resumen = Deterioro::resumenPorProductoRango($idCorte);
        $fiscal = Deterioro::resumenFiscal($idCorte);
        // Las seis columnas de rango del libro. Si la paramétrica creciera, el
        // bloque dejaría de caber y «TOTAL VALOR A PROVISIONAR» no igualaría el
        // corte: se detiene con el motivo en vez de omitir columnas en silencio.
        $columnas = ['V', 'W', 'X', 'Y', 'Z', 'AA'];
        if (count($rangos) > count($columnas)) {
            throw new \RuntimeException(
                'El corte tiene '.count($rangos).' rangos de mora y la hoja DETERIORO del libro sólo tiene '
                . count($columnas).' columnas de rango. El Excel de transición replica la estructura del libro '
                . 'y no se puede generar hasta que se decida cómo presentar los rangos adicionales.');
        }

        $matriz = [];
        $productos = [];
        foreach ($resumen as $r) {
            $productos[$r->producto] = true;
            $matriz[$r->producto][$r->rango] = $r;
        }
        $productos = array_keys($productos);

        $hoja->setCellValue('T3', 'DIAS DE MORA');
        $hoja->setCellValue('T4', '% DE DETERIORO');
        $hoja->setCellValue('AB4', 'TOTAL');
        foreach ($rangos as $i => $rango) {
            if (!isset($columnas[$i])) {
                continue;
            }
            $hoja->setCellValue($columnas[$i].'3', $rango->etiqueta);
            $hoja->setCellValue($columnas[$i].'4', (float) $rango->pct_deterioro_contable);
        }

        // Provisión por producto y rango, totales, mes anterior y ajuste del mes
        // (T5:AB10 del libro).
        $fila = 5;
        $totales = array_fill_keys(array_keys($columnas), 0);
        foreach ($productos as $producto) {
            $hoja->setCellValue('T'.$fila, 'VALOR PROVISION '.$producto);
            $suma = 0;
            foreach ($rangos as $i => $rango) {
                if (!isset($columnas[$i])) {
                    continue;
                }
                $valor = isset($matriz[$producto][$rango->codigo])
                    ? (float) $matriz[$producto][$rango->codigo]->deterioro : 0;
                $hoja->setCellValue($columnas[$i].$fila, $valor);
                $totales[$i] += $valor;
                $suma += $valor;
            }
            $hoja->setCellValue('AB'.$fila, $suma);
            $fila++;
        }
        $hoja->setCellValue('T'.$fila, 'TOTAL VALOR A PROVISIONAR');
        foreach ($rangos as $i => $rango) {
            if (isset($columnas[$i])) {
                $hoja->setCellValue($columnas[$i].$fila, $totales[$i]);
            }
        }
        $hoja->setCellValue('AB'.$fila, array_sum($totales));
        $fila++;

        // Sin corte anterior las dos filas quedan en blanco, con el motivo en el
        // rótulo. Llenarlas sería afirmar que el mes anterior fue cero y que el
        // saldo inicial completo es el ajuste del mes, que es exactamente la
        // afirmación que asientoContable() rechaza por falsa.
        $sinAnterior = !$movimiento->id_corte_anterior;
        $anterior = [];
        if (!$sinAnterior) {
            foreach (Deterioro::comparativoContraAnterior($idCorte, 'rango') as $c) {
                $anterior[$c->dimension] = (float) $c->deterioro_ant;
            }
        }

        $hoja->setCellValue('T'.$fila, 'VALOR MES ANTERIOR'
            .($sinAnterior ? ' (no hay corte anterior con qué comparar)' : ''));
        if (!$sinAnterior) {
            foreach ($rangos as $i => $rango) {
                if (isset($columnas[$i])) {
                    $hoja->setCellValue($columnas[$i].$fila,
                        isset($anterior[$rango->codigo]) ? $anterior[$rango->codigo] : 0);
                }
            }
            $hoja->setCellValue('AB'.$fila, (float) $movimiento->anterior);
        }
        $fila++;

        $hoja->setCellValue('T'.$fila, 'VALOR AJUSTE DEL MES'
            .($sinAnterior ? ' (no hay corte anterior con qué comparar)' : ''));
        if (!$sinAnterior) {
            foreach ($rangos as $i => $rango) {
                if (isset($columnas[$i])) {
                    $previo = isset($anterior[$rango->codigo]) ? $anterior[$rango->codigo] : 0;
                    $hoja->setCellValue($columnas[$i].$fila, $totales[$i] - $previo);
                }
            }
            $hoja->setCellValue('AB'.$fila, (float) $movimiento->gasto);
        }

        // Matriz de cartera por producto y rango (T14:AB26 del libro). Con los
        // tres productos del libro arranca en la fila 14, como allí; con más
        // productos el primer bloque crece y ésta se corre para no pisarlo.
        $fila = max(14, $fila + 3);
        $hoja->setCellValue('T'.$fila, 'DIAS DE MORA');
        foreach ($rangos as $i => $rango) {
            if (isset($columnas[$i])) {
                $hoja->setCellValue($columnas[$i].$fila, $rango->etiqueta);
            }
        }
        $fila++;
        $hoja->setCellValue('T'.$fila, 'PRODUCTO');
        foreach ($rangos as $i => $rango) {
            if (isset($columnas[$i])) {
                $hoja->setCellValue($columnas[$i].$fila, $rango->codigo);
            }
        }
        $hoja->setCellValue('AB'.$fila, 'TOTAL');
        $fila++;

        $granTotal = ['capital' => array_fill_keys(array_keys($columnas), 0),
                      'interes' => array_fill_keys(array_keys($columnas), 0)];
        foreach ($productos as $producto) {
            $hoja->setCellValue('T'.$fila, $producto);
            foreach (['capital' => 'Capital', 'interes' => 'Interés'] as $clave => $etiqueta) {
                $hoja->setCellValue('U'.$fila, $etiqueta);
                $suma = 0;
                foreach ($rangos as $i => $rango) {
                    if (!isset($columnas[$i])) {
                        continue;
                    }
                    $r = isset($matriz[$producto][$rango->codigo]) ? $matriz[$producto][$rango->codigo] : null;
                    $valor = $r ? (float) ($clave === 'capital' ? $r->capital_vencido : $r->interes_vencido) : 0;
                    $hoja->setCellValue($columnas[$i].$fila, $valor);
                    $granTotal[$clave][$i] += $valor;
                    $suma += $valor;
                }
                $hoja->setCellValue('AB'.$fila, $suma);
                $fila++;
            }
            $hoja->setCellValue('T'.$fila, 'CARTERA '.$producto);
            $suma = 0;
            foreach ($rangos as $i => $rango) {
                if (!isset($columnas[$i])) {
                    continue;
                }
                $r = isset($matriz[$producto][$rango->codigo]) ? $matriz[$producto][$rango->codigo] : null;
                $valor = $r ? (float) $r->capital_vencido + (float) $r->interes_vencido : 0;
                $hoja->setCellValue($columnas[$i].$fila, $valor);
                $suma += $valor;
            }
            $hoja->setCellValue('AB'.$fila, $suma);
            $fila++;
        }

        $hoja->setCellValue('T'.$fila, 'TOTALES');
        foreach (['capital' => 'Capital', 'interes' => 'Intereses'] as $clave => $etiqueta) {
            $hoja->setCellValue('U'.$fila, $etiqueta);
            foreach ($rangos as $i => $rango) {
                if (isset($columnas[$i])) {
                    $hoja->setCellValue($columnas[$i].$fila, $granTotal[$clave][$i]);
                }
            }
            $hoja->setCellValue('AB'.$fila, array_sum($granTotal[$clave]));
            $fila++;
        }

        // Bloque fiscal y las dos cifras de cierre (AB29:AC35 del libro).
        $individual = 0;
        $general = 0;
        $deduccion = 0;
        $pctFiscal = 0;
        foreach ($fiscal as $f) {
            $individual += (float) $f->deterioro_fiscal_individual;
            $general += (float) $f->deterioro_fiscal_general;
            $deduccion += (float) $f->deduccion_fiscal_ano;
        }
        foreach ($resumen as $r) {
            $pctFiscal = max($pctFiscal, (float) $r->pct_fiscal);
        }
        $fila += 2;
        $hoja->setCellValue('Z'.$fila, 'PROVISION DE CARTERA FISCAL A '.$fecha);
        $hoja->setCellValue('AB'.$fila, $pctFiscal);
        $hoja->setCellValue('AC'.$fila, 'INDIVIDUAL');
        $fila++;
        $hoja->setCellValue('AB'.$fila, $individual);
        $hoja->setCellValue('AC'.$fila, $individual);
        $fila++;
        $hoja->setCellValue('AC'.$fila, 'GENERAL');
        $fila++;
        $hoja->setCellValue('AB'.$fila, $general);
        $hoja->setCellValue('AC'.$fila, $general);
        $fila += 2;
        $hoja->setCellValue('Z'.$fila, 'GASTO CONTABLE POR DETERIORO A '.$fecha);
        $hoja->setCellValue('AC'.$fila, $movimiento->gasto === null ? null : (float) $movimiento->gasto);
        $fila++;
        $hoja->setCellValue('Z'.$fila, 'DEDUCCIÓN FISCAL A '.$fecha);
        $hoja->setCellValue('AC'.$fila, $deduccion);

        // Fila de totales del detalle, que en el libro es la 2112. Se deja como
        // fórmula y no como cifra calculada aquí para que quien revise el
        // archivo pueda cambiar una fila y ver el efecto, que es justo lo que la
        // marcha en paralelo necesita.
        if ($filaTotales <= 6) {
            return;
        }
        $hoja->setCellValue('D'.$filaTotales, 'TOTALES');
        foreach (['E' => 'capital_anterior', 'F' => 'capital', 'G' => 'capital_vencido',
                  'H' => 'interes_vencido', 'I' => 'base_deterioro', 'M' => 'deterioro_contable',
                  'N' => 'deduccion_fiscal_ano', 'O' => 'deterioro_fiscal_individual',
                  'P' => 'fiscal_acumulado_anterior', 'Q' => 'saldo_siesa',
                  'R' => 'saldo_topado'] as $columna => $campo) {
            $hoja->setCellValue($columna.$filaTotales,
                '=SUM('.$columna.'6:'.$columna.($filaTotales - 1).')');
        }
    }

    private static function hojaTablaFinal($hoja, $idCorte)
    {
        $hoja->setTitle('Tabla final');
        $hoja->fromArray([['Cliente', 'Producto', 'NomModOperacion', 'CalificacionABC',
            'dias_mora_cliente', 'IdOperacion', 'Saldo_Capital', 'Capital corriente',
            'Interes Corriente', 'Capital Vencido', 'Interes Vencido', 'Interes Mora',
            '', '', '', 'Capital Mes Anterior', 'Variacion', 'Comentarios']], null, 'A4');

        self::volcar($hoja, Deterioro::hojaTablaFinal($idCorte), [
            'cliente', 'producto', 'nom_operacion', 'rango', 'dias_mora_operacion',
            'id_operacion', 'saldo_capital', 'capital_corriente', 'interes_corriente',
            'capital_vencido', 'interes_vencido', 'interes_mora',
            function () { return null; }, function () { return null; }, function () { return null; },
            'capital_mes_anterior', 'variacion_capital', function () { return null; },
        ], 5);
    }

    /**
     * Hoja `DIFERENCIAS`. El libro concilia por cliente y con los saldos pegados
     * a mano; el módulo concilia por operación contra el snapshot de SIESA, así
     * que la operación va en la columna A —vacía en el libro— y el tipo y el
     * estado de la partida después de la nota. Las columnas del medio conservan
     * su posición y su rótulo.
     */
    private static function hojaDiferencias($hoja, $idCorte)
    {
        $hoja->setTitle('DIFERENCIAS');
        $hoja->fromArray([['OPERACIÓN', 'CLIENTE', 'NOMBRE', 'SALDO SIESA', 'SALDO FACT',
            'DIFERENCIAS', 'PRORROGAS X COBRAR / RESERVAS', 'DIFERENCIAS', 'Nota',
            'TIPO', 'ESTADO']], null, 'A7');

        self::volcar($hoja, Deterioro::conciliacionSiesa($idCorte), [
            'numero_operacion', 'nit', 'cliente', 'saldo_siesa', 'saldo_factoring',
            'diferencia',
            // El módulo no captura prórrogas ni reservas: esa columna del libro
            // es una entrada manual que la fase 6b sustituye por ajustes y notas.
            function () { return null; },
            'diferencia', 'explicacion', 'tipo', 'estado',
        ], 8);
    }

    /**
     * Hoja `1399`. Trae el acumulado fiscal completo, incluidas las operaciones
     * que ya no están en el corte, y las marca: son acumulado que existe y que
     * el libro también trae, no una diferencia contra el módulo. La columna P de
     * `DETERIORO` sólo suma las que sí están en el corte.
     */
    private static function hoja1399($hoja, $idCorte)
    {
        $hoja->setTitle('1399');
        $hoja->fromArray([['OPERACIÓN', 'CLIENTE', 'ACUMULADO FISCAL AÑOS ANTERIORES',
            'EN EL CORTE']], null, 'A1');

        self::volcar($hoja, Deterioro::hoja1399($idCorte),
            ['id_operacion', 'cliente', 'fiscal_acumulado_anterior',
                function ($f) {
                    return $f->en_corte ? 'SÍ' : 'NO · la operación ya no está en el corte';
                }], 2);
    }

    /** Detalle por operación, con los mismos filtros que acepta la grilla. */
    public static function libroDetalle($idCorte, $filtros)
    {
        $libro = new Spreadsheet();
        $hoja = $libro->getActiveSheet();
        $hoja->setTitle('Detalle');

        $hoja->fromArray([['OPERACIÓN', 'DOCUMENTO', 'CLIENTE', 'PRODUCTO', 'SUBPRODUCTO',
            'FECHA INICIAL MORA', 'CUOTAS', 'DIAS MORA', 'RANGO',
            'CAPITAL CORRIENTE FACTORING', 'CAPITAL VENCIDO', 'INTERES CORRIENTE FACTORING', 'INTERES VENCIDO',
            'INTERES MORA FACTORING', 'PRORROGA VENCIDA SIESA',
            'BASE DETERIORO', '% CONTABLE', 'DETERIORO CONTABLE',
            'FISCAL INDIVIDUAL', 'FISCAL GENERAL', 'ACUMULADO FISCAL ANTERIOR',
            'SALDO TOPADO', 'DEDUCCION FISCAL AÑO', 'FISCAL ACUMULADO',
            'DIFERENCIA TEMPORARIA', 'IMPUESTO DIFERIDO', 'AÑO REVERSION', 'ESTADO REVERSION',
            'CAPITAL MES ANTERIOR FACTORING', 'VARIACION CAPITAL FACTORING', 'CUOTAS REPETIDAS',
            'FUENTE VENCIDOS', 'CAPITAL VENCIDO FACTORING', 'INTERES VENCIDO FACTORING',
            'SUSPENDIDA', 'ORIGEN BASE', 'BASE SIN SUSPENDER',
            'CAPITAL TOTAL FACTORING', 'CAPITAL SIESA', 'DIFERENCIA CAPITAL (SIESA - FACTORING)',
            'INTERES SIESA', 'DIFERENCIA INTERES (SIESA - VENCIDO FACTORING)',
            'CAPITAL VENCIDO SIESA', 'INTERES VENCIDO SIESA',
            'DIFERENCIA INTERES VENCIDO (SIESA - FACTORING)']], null, 'A1');

        self::volcar($hoja, Deterioro::detalleOperaciones($idCorte, $filtros), [
            'id_operacion', 'id_cliente', 'cliente', 'producto', 'nom_operacion',
            'fec_inicial_mora', 'cuotas', 'dias_mora_operacion', 'rango',
            'capital_corriente',
            function ($f) {
                return $f->capital_vencido_siesa !== null ? $f->capital_vencido_siesa : $f->capital_vencido;
            },
            'interes_corriente',
            function ($f) {
                return $f->interes_vencido_siesa !== null ? $f->interes_vencido_siesa : $f->interes_vencido;
            },
            // Vacía, y no cero, en los cortes calculados antes de que la
            // prórroga vencida entrara en la base: ahí no se midió.
            'interes_mora', 'interes_prorroga_siesa',
            function ($f) {
                return $f->base_congelada !== null ? $f->base_congelada : $f->base_deterioro;
            },
            'pct_contable', 'deterioro_contable',
            'deterioro_fiscal_individual', 'deterioro_fiscal_general', 'fiscal_acumulado_anterior',
            'saldo_topado', 'deduccion_fiscal_ano', 'deterioro_fiscal_acumulado',
            'diferencia_temporaria', 'impuesto_diferido_activo', 'ano_reversion_fiscal',
            // El código interno no sale al Excel del usuario. Aquí cabe el
            // nombre completo del panel, «Deducción al 100 %»; la columna del
            // detalle lo abrevia a «Al 100 %» porque es angosta, y su
            // encabezado lo advierte.
            function ($f) {
                $estados = [
                    'PROYECTADA' => 'Proyectada',
                    'AGOTADA' => 'Deducción al 100 %',
                    'SIN_PROYECCION' => 'Sin proyección',
                ];
                return isset($estados[$f->estado_reversion]) ? $estados[$f->estado_reversion] : null;
            },
            'capital_mes_anterior', 'variacion_capital',
            // Vacía, y no cero, en los cortes calculados antes de que existiera
            // la marca: ahí no se sabe si hubo cuotas repetidas.
            function ($f) {
                return $f->duplicadas_evaluadas ? $f->cuotas_duplicadas : null;
            },
            function ($f) {
                $fuentes = [
                    'TERCERO' => 'SIESA · saldo del tercero',
                    'OPE' => 'SIESA · operación SIESA',
                    'NOTA' => 'SIESA · nota contable',
                ];
                if ($f->capital_vencido_siesa === null && $f->interes_vencido_siesa === null) {
                    return 'FACTORING';
                }
                return isset($fuentes[$f->origen_saldo_siesa]) ? $fuentes[$f->origen_saldo_siesa] : 'SIESA';
            },
            'capital_vencido', 'interes_vencido',
            function ($f) {
                return $f->suspendida ? 'SÍ' : 'NO';
            },
            function ($f) {
                return $f->suspendida ? $f->origen_base : null;
            },
            'base_deterioro',
            function ($f) {
                return (string) ($f->capital_corriente + $f->capital_vencido);
            },
            'saldo_siesa',
            function ($f) {
                return (string) round(($f->saldo_siesa !== null ? $f->saldo_siesa : 0) - $f->capital_corriente - $f->capital_vencido, 4);
            },
            'interes_siesa',
            function ($f) {
                return (string) round(($f->interes_siesa !== null ? $f->interes_siesa : 0) - $f->interes_vencido, 4);
            },
            'capital_vencido_siesa', 'interes_vencido_siesa',
            function ($f) {
                return (string) round(($f->interes_vencido_siesa !== null ? $f->interes_vencido_siesa : 0) - $f->interes_vencido, 4);
            },
        ], 2);

        return $libro;
    }

    /**
     * Archivo plano del asiento (§17). Formato largo, delimitado por punto y
     * coma: una fila por cifra, con su sección, su llave, su concepto, la cuenta
     * y la naturaleza cuando las tiene. Es el formato que admite en el mismo
     * archivo el asiento, el auxiliar por producto y los dos anexos, que no
     * tienen la misma forma.
     *
     * Los importes van con cuatro decimales y punto decimal, sin separador de
     * miles: es la precisión con que están guardados y el asiento no redondea.
     */
    public static function planoAsiento($filas)
    {
        // Todos los campos de texto se sanean, no sólo el último: la llave lleva
        // el nombre del producto y el concepto y la cuenta vienen de la
        // paramétrica, y un punto y coma en cualquiera de ellos partiría la fila
        // en silencio.
        $texto = function ($valor) {
            return str_replace([';', "\r", "\n"], [',', ' ', ' '], (string) $valor);
        };
        $lineas = ['seccion;llave;concepto;cuenta;naturaleza;valor;texto'];
        foreach ($filas as $f) {
            $lineas[] = implode(';', [
                $texto($f['seccion']), $texto($f['llave']), $texto($f['concepto']),
                $texto($f['cuenta']), $texto($f['naturaleza']),
                $f['valor'] === null ? '' : number_format((float) $f['valor'], 4, '.', ''),
                $texto($f['texto']),
            ]);
        }

        return "\xEF\xBB\xBF".implode("\r\n", $lineas)."\r\n";
    }
}
