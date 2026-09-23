{{--
    PDF del resumen del corte (§16). Es una salida del servidor, no una pantalla:
    la arma DeterioroController::exportarResumen con dompdf.

    Un corte calculado sale rotulado PRELIMINAR y uno cerrado con salvedades
    lleva la marca, el motivo y, en la última página, los requisitos congelados
    en la foto del cierre. Un cierre limpio lleva pie con fecha y usuario. Nada
    de eso se resuelve aquí contra la base: viene de corte() y de la foto, que
    congeló el nombre del usuario desde la fase 7a.
--}}
@php
    $numero = function ($valor, $decimales = 2) {
        return $valor === null ? '' : number_format((float) $valor, $decimales, ',', '.');
    };
    $preliminar = $corte->estado !== 'CERRADO';
@endphp
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <style>
        @page { margin: 22mm 12mm 16mm 12mm; }
        body { font-family: DejaVu Sans, sans-serif; font-size: 8px; color: #222; }
        h1 { font-size: 13px; margin: 0 0 2px 0; }
        h2 { font-size: 10px; margin: 14px 0 4px 0; border-bottom: 1px solid #999; padding-bottom: 2px; }
        table { width: 100%; border-collapse: collapse; }
        th, td { border: 1px solid #bbb; padding: 2px 3px; }
        th { background: #eee; text-align: center; }
        td.n { text-align: right; }
        .marca { padding: 4px 6px; margin: 6px 0; font-weight: bold; }
        .preliminar { background: #fff3cd; border: 1px solid #d3a000; }
        .salvedad { background: #f8d7da; border: 1px solid #b02a37; }
        .pie { margin-top: 10px; font-size: 7px; color: #555; }
        .falla { background: #f8d7da; }
        .na { color: #777; }
    </style>
</head>
<body>
    <h1>Deterioro de cartera · corte del {{ $corte->fecha_corte }}</h1>
    <div>Estado: {{ $corte->estado }} · generado el {{ date('Y-m-d H:i') }}</div>

    @if($preliminar)
        <div class="marca preliminar">PRELIMINAR · el corte no está cerrado y sus cifras pueden cambiar.</div>
    @elseif($corte->cerrado_con_salvedad)
        <div class="marca salvedad">
            CERRADO CON SALVEDADES · {{ $corte->motivo_salvedad }}
        </div>
    @endif

    <h2>Matriz producto × rango</h2>
    <table>
        <thead>
            <tr>
                <th>Producto</th><th>Rango</th><th>Operaciones</th>
                <th>Capital vencido</th><th>Interés vencido</th><th>Base</th>
                <th>%</th><th>Deterioro contable</th><th>Deducción fiscal año</th>
            </tr>
        </thead>
        <tbody>
        @php $tBase = 0; $tDet = 0; $tDed = 0; @endphp
        @foreach($resumen as $r)
            @php $tBase += $r->base; $tDet += $r->deterioro; $tDed += $r->deduccion_fiscal_ano; @endphp
            <tr>
                <td>{{ $r->producto }}</td>
                <td>{{ $r->rango }}</td>
                <td class="n">{{ $r->operaciones }}</td>
                <td class="n">{{ $numero($r->capital_vencido) }}</td>
                <td class="n">{{ $numero($r->interes_vencido) }}</td>
                <td class="n">{{ $numero($r->base) }}</td>
                <td class="n">{{ $numero($r->pct * 100) }}</td>
                <td class="n">{{ $numero($r->deterioro) }}</td>
                <td class="n">{{ $numero($r->deduccion_fiscal_ano) }}</td>
            </tr>
        @endforeach
            <tr>
                <th colspan="5">TOTAL</th>
                <th class="n">{{ $numero($tBase) }}</th>
                <th></th>
                <th class="n">{{ $numero($tDet) }}</th>
                <th class="n">{{ $numero($tDed) }}</th>
            </tr>
        </tbody>
    </table>

    <h2>Comparativo contra el mes anterior</h2>
    @if($movimiento->id_corte_anterior)
        <table>
            <tbody>
                <tr><td>Deterioro del corte anterior ({{ $movimiento->fecha_anterior }})</td>
                    <td class="n">{{ $numero($movimiento->anterior) }}</td></tr>
                <tr><td>Altas ({{ $movimiento->operaciones_alta }} operaciones)</td>
                    <td class="n">{{ $numero($movimiento->altas) }}</td></tr>
                <tr><td>Variación de las que continúan ({{ $movimiento->operaciones_variacion }} operaciones)</td>
                    <td class="n">{{ $numero($movimiento->variacion) }}</td></tr>
                <tr><td>Bajas ({{ $movimiento->operaciones_baja }} operaciones)</td>
                    <td class="n">-{{ $numero($movimiento->bajas) }}</td></tr>
                <tr><th>Deterioro de este corte</th>
                    <th class="n">{{ $numero($movimiento->actual) }}</th></tr>
                <tr><th>Gasto del período</th>
                    <th class="n">{{ $numero($movimiento->gasto) }}</th></tr>
            </tbody>
        </table>

        <table style="margin-top:6px;">
            <thead>
                <tr><th>Rango</th><th>Operaciones</th><th>Deterioro</th>
                    <th>Deterioro anterior</th><th>Variación</th></tr>
            </thead>
            <tbody>
            @foreach($comparativo as $c)
                <tr>
                    <td>{{ $c->dimension }}</td>
                    <td class="n">{{ $c->operaciones }}</td>
                    <td class="n">{{ $numero($c->deterioro) }}</td>
                    <td class="n">{{ $numero($c->deterioro_ant) }}</td>
                    <td class="n">{{ $numero($c->variacion) }}</td>
                </tr>
            @endforeach
            </tbody>
        </table>
    @else
        <div>Es el primer corte de la serie: no hay corte anterior con qué comparar.</div>
    @endif

    <h2>Controles de cuadre</h2>
    <table>
        <thead>
            <tr><th>Código</th><th>Control</th><th>Detalle</th><th>Resumen</th>
                <th>Diferencia</th><th>Estado</th></tr>
        </thead>
        <tbody>
        @foreach($cuadres as $q)
            <tr class="{{ $q->estado === 'FALLA' ? 'falla' : ($q->estado === 'N/A' ? 'na' : '') }}">
                <td>{{ $q->codigo }}</td>
                <td>{{ $q->descripcion }}{{ $q->motivo ? ' · '.$q->motivo : '' }}</td>
                <td class="n">{{ $numero($q->valor_detalle, 4) }}</td>
                <td class="n">{{ $numero($q->valor_resumen, 4) }}</td>
                <td class="n">{{ $numero($q->diferencia, 4) }}</td>
                <td>{{ $q->estado }}</td>
            </tr>
        @endforeach
        </tbody>
    </table>

    @if(!$preliminar && !$corte->cerrado_con_salvedad)
        <div class="pie">
            Corte cerrado el {{ $corte->fecha_cierre }} por
            {{ $corte->usuario_cierre ?: 'usuario '.$corte->id_usuario_cierre }}.
        </div>
    @endif

    @if($corte->cerrado_con_salvedad && $corte->salvedad)
        <div style="page-break-before: always;"></div>
        <h2>Requisitos pendientes al cerrar con salvedades</h2>
        <div>
            Cerrado el {{ $corte->salvedad['fecha'] }} por
            {{ $corte->salvedad['usuario'] ?: 'usuario '.$corte->salvedad['id_usuario'] }}.
            Motivo: {{ $corte->salvedad['motivo'] }}
        </div>
        <table style="margin-top:6px;">
            <thead>
                <tr><th>Concepto</th><th>Cantidad</th><th>Valor</th></tr>
            </thead>
            <tbody>
            @foreach($corte->salvedad['bloqueos'] as $b)
                <tr>
                    <td>{{ $b['concepto'] }}</td>
                    <td class="n">{{ $b['cantidad'] }} {{ $b['unidad'] }}</td>
                    <td class="n">{{ $numero($b['valor']) }}</td>
                </tr>
                @foreach($b['detalle'] as $d)
                <tr>
                    <td style="padding-left:14px;">
                        {{ isset($d['codigo']) ? $d['codigo'].' · '.$d['descripcion'] : $d['tipo'] }}
                    </td>
                    <td class="n">{{ isset($d['cantidad']) ? $d['cantidad'] : '' }}</td>
                    <td class="n">{{ $numero(isset($d['valor']) ? $d['valor'] : $d['diferencia']) }}</td>
                </tr>
                @endforeach
            @endforeach
            </tbody>
        </table>
    @endif
</body>
</html>
