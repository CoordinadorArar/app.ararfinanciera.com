<div style="font-family:Arial,Helvetica,sans-serif;color:#333;max-width:600px;margin:0 auto;">
    <h2 style="color:{{ $aprobado ? '#1e7e34' : '#c82333' }};font-size:20px;margin:0 0 16px;">
        {{ $aprobado ? 'Crédito aprobado' : 'Crédito rechazado' }}
    </h2>
    <p style="margin:0 0 12px;">Buen día,</p>
    <p style="margin:0 0 16px;">
        La solicitud de crédito de libranza No. {{ $proceso->IdProceso }} a nombre de <strong>{{ $nombre }}</strong>,
        identificado(a) con documento {{ $proceso->DocumentoTercero }}, pagaduría {{ $proceso->NombrePagaduria ?? 'sin pagaduría' }},
        fue {{ $aprobado ? 'aprobada' : 'rechazada' }}.
    </p>
    @if($aprobado)
        <table style="border-collapse:collapse;width:100%;margin:0 0 16px;font-size:14px;">
            <tr>
                <th style="text-align:left;padding:8px;border:1px solid #ddd;background:#f5f7fa;">Valor aprobado</th>
                <td style="padding:8px;border:1px solid #ddd;">$ {{ number_format((float) $proceso->ValorCreditoSolicitado,0,',','.') }}</td>
            </tr>
            <tr>
                <th style="text-align:left;padding:8px;border:1px solid #ddd;background:#f5f7fa;">Plazo</th>
                <td style="padding:8px;border:1px solid #ddd;">{{ $proceso->NumeroCuotas }} cuotas</td>
            </tr>
            <tr>
                <th style="text-align:left;padding:8px;border:1px solid #ddd;background:#f5f7fa;">Tasa mensual</th>
                <td style="padding:8px;border:1px solid #ddd;">{{ $proceso->TasaInteres }} %</td>
            </tr>
            <tr>
                <th style="text-align:left;padding:8px;border:1px solid #ddd;background:#f5f7fa;">Cuota mensual</th>
                <td style="padding:8px;border:1px solid #ddd;">$ {{ number_format((float) $proceso->ValorCuota,0,',','.') }}</td>
            </tr>
        </table>
    @elseif($motivo)
        <p style="margin:0 0 16px;"><strong>Motivo:</strong> {{ $motivo }}</p>
    @endif
    @if($texto)
        <p style="margin:0 0 16px;padding:12px;background:#f5f7fa;border-left:3px solid #416ec3;">{!! nl2br(e($texto)) !!}</p>
    @endif
    <p style="margin:0;font-size:12px;color:#777;">Este es un mensaje automático de Arar Financiera.</p>
</div>
