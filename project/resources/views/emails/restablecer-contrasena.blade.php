<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Restablecer contraseña - Arar Financiera</title>
</head>
<body style="margin:0;padding:0;background-color:#F6F8FB;font-family:'Nunito','Segoe UI',Arial,sans-serif;">
<div style="display:none;max-height:0;overflow:hidden;opacity:0;font-size:1px;line-height:1px;color:#F6F8FB;">Enlace válido por {{ $minutos }} minutos para restablecer tu contraseña.</div>
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="background-color:#F6F8FB;font-family:'Nunito','Segoe UI',Arial,sans-serif;">
<tr>
<td align="center" style="padding:24px 12px;">
<table role="presentation" width="600" cellpadding="0" cellspacing="0" border="0" style="width:100%;max-width:600px;margin:0 auto;background-color:#FFFFFF;border:1px solid #E3E7EE;border-radius:12px;border-collapse:separate;overflow:hidden;">
<tr>
<td style="height:4px;line-height:4px;font-size:0;background-color:#416EC3;border-radius:12px 12px 0 0;">&nbsp;</td>
</tr>
<tr>
<td align="center" style="padding:32px 40px 8px;">
<img src="{{ asset('images/LogoArar.png') }}" width="144" alt="Arar Financiera" style="display:block;border:0;height:auto;margin:0 auto;">
</td>
</tr>
<tr>
<td style="padding:24px 40px;font-family:'Nunito','Segoe UI',Arial,sans-serif;">
<h1 style="margin:0 0 12px;font-size:22px;font-weight:700;line-height:30px;color:#1F2D3D;font-family:'Nunito','Segoe UI',Arial,sans-serif;">Restablece tu contraseña</h1>
<p style="margin:0 0 12px;font-size:15px;line-height:24px;color:#4A5A70;">Hola {{ $nombre }},</p>
<p style="margin:0;font-size:15px;line-height:24px;color:#4A5A70;">Recibimos una solicitud para restablecer la contraseña de tu cuenta en Arar Financiera.</p>
<table role="presentation" cellpadding="0" cellspacing="0" border="0" align="center" style="margin:28px auto;">
<tr>
<td align="center" style="background-color:#416EC3;border-radius:8px;">
<a href="{{ $url }}" target="_blank" style="display:inline-block;padding:14px 32px;color:#FFFFFF;font-size:16px;font-weight:700;line-height:20px;text-decoration:none;font-family:'Nunito','Segoe UI',Arial,sans-serif;border-radius:8px;">Restablecer contraseña</a>
</td>
</tr>
</table>
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="margin:0 0 20px;">
<tr>
<td style="background-color:#FDF8E6;border:1px solid #F0E2B4;border-radius:8px;padding:12px 16px 14px;font-size:14px;line-height:22px;color:#6B5A20;">Este enlace vence en {{ $minutos }} minutos.</td>
</tr>
</table>
<p style="margin:0 0 16px;font-size:14px;line-height:22px;color:#6B7A90;">Si no solicitaste este cambio, ignora este correo; tu contraseña no se modificará.</p>
<p style="margin:0 0 4px;font-size:14px;line-height:22px;color:#6B7A90;">Si el botón no funciona, copia este enlace:</p>
<p style="margin:0;font-size:12px;line-height:18px;color:#2D55A5;word-break:break-all;"><a href="{{ $url }}" target="_blank" style="color:#2D55A5;text-decoration:underline;word-break:break-all;">{{ $url }}</a></p>
</td>
</tr>
<tr>
<td align="center" style="border-top:1px solid #EEF1F6;padding:20px 40px 12px;font-size:12px;line-height:18px;color:#8593A7;text-align:center;font-family:'Nunito','Segoe UI',Arial,sans-serif;">&copy; {{ date('Y') }} Arar Financiera · Este es un mensaje automático, no respondas a este correo.</td>
</tr>
</table>
</td>
</tr>
</table>
</body>
</html>
