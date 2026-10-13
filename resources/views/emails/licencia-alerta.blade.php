@php
    // El logo viaja DENTRO del correo (adjunto en línea): se ve aunque el cliente de correo no pueda abrir
    // la dirección del sistema. Si el archivo no está, el correo sale igual, con el nombre en texto.
    $rutaLogo = public_path('img/logo_s4t.png');
    $logoCid = is_file($rutaLogo) ? $message->embed($rutaLogo) : null;
@endphp
<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="color-scheme" content="light">
    <meta name="supported-color-schemes" content="light">
    <title>{{ $titulo }}</title>
</head>

<body
    style="margin:0; padding:0; background-color:#f3f0fa; font-family:'Segoe UI', Roboto, Helvetica, Arial, sans-serif; color:#1f1a33;">

    {{-- Texto de vista previa: lo que se lee en la lista de correos, junto al asunto --}}
    <div style="display:none; max-height:0; overflow:hidden; opacity:0; mso-hide:all;">{{ $preheader }}</div>

    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0"
        style="background-color:#f3f0fa;">
        <tr>
            <td align="center" style="padding:28px 12px;">

                <table role="presentation" width="600" cellpadding="0" cellspacing="0" border="0"
                    style="width:100%; max-width:600px; background-color:#ffffff; border:1px solid #ece7f6; border-radius:16px; border-collapse:separate; overflow:hidden;">

                    {{-- ===== ENCABEZADO ===== --}}
                    <tr>
                        <td height="6" style="height:6px; line-height:6px; font-size:0; background-color:#6d4aa8;">
                            &nbsp;</td>
                    </tr>
                    <tr>
                        <td align="center" style="padding:28px 32px 22px;">
                            @if ($logoCid)
                                <img src="{{ $logoCid }}" width="210" alt="{{ $sistema }}"
                                    style="display:block; border:0; outline:none; height:auto; margin:0 auto;">
                            @else
                                <span style="font-size:24px; font-weight:700; color:#4a3280;">{{ $sistema }}</span>
                            @endif
                        </td>
                    </tr>

                    {{-- ===== AVISO: qué está pasando, de un vistazo ===== --}}
                    <tr>
                        <td style="padding:0 32px;">
                            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0"
                                style="background-color:{{ $tonoFondo }}; border-left:5px solid {{ $tonoBorde }}; border-radius:8px;">
                                <tr>
                                    <td style="padding:16px 20px;">
                                        <div
                                            style="font-size:12px; font-weight:700; letter-spacing:0.08em; text-transform:uppercase; color:{{ $tonoTexto }};">
                                            {{ $etiqueta }}</div>
                                        <div
                                            style="font-size:24px; line-height:1.25; font-weight:700; color:#1f1a33; margin-top:4px;">
                                            {{ $titulo }}</div>
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>

                    {{-- ===== CUERPO ===== --}}
                    <tr>
                        <td style="padding:24px 32px 4px; font-size:16px; line-height:1.6; color:#1f1a33;">
                            <p style="margin:0 0 14px;">Hola, {{ $saludo }}:</p>
                            <p style="margin:0 0 14px;">{{ $mensaje }}</p>
                            <p style="margin:0;">{{ $consejo }}</p>
                        </td>
                    </tr>

                    <tr>
                        <td style="padding:20px 32px 4px;">
                            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0"
                                style="border:1px solid #ece7f6; border-radius:10px; border-collapse:separate;">
                                <tr>
                                    <td colspan="2"
                                        style="padding:12px 16px; background-color:#f6f3fc; border-radius:10px 10px 0 0; font-size:12px; font-weight:700; letter-spacing:0.06em; text-transform:uppercase; color:#4a3280;">
                                        Detalle de la licencia</td>
                                </tr>
                                <tr>
                                    <td width="38%"
                                        style="padding:11px 16px; border-top:1px solid #f0ecf8; font-size:14px; color:#6b6785;">
                                        Licencia</td>
                                    <td
                                        style="padding:11px 16px; border-top:1px solid #f0ecf8; font-size:14px; font-weight:600; color:#1f1a33;">
                                        {{ $codigo }}</td>
                                </tr>
                                <tr>
                                    <td width="38%"
                                        style="padding:11px 16px; border-top:1px solid #f0ecf8; font-size:14px; color:#6b6785;">
                                        Empresa</td>
                                    <td
                                        style="padding:11px 16px; border-top:1px solid #f0ecf8; font-size:14px; font-weight:600; color:#1f1a33;">
                                        {{ $empresa }}</td>
                                </tr>
                                <tr>
                                    <td width="38%"
                                        style="padding:11px 16px; border-top:1px solid #f0ecf8; font-size:14px; color:#6b6785;">
                                        Plan</td>
                                    <td
                                        style="padding:11px 16px; border-top:1px solid #f0ecf8; font-size:14px; font-weight:600; color:#1f1a33;">
                                        {{ $plan }}</td>
                                </tr>
                                <tr>
                                    <td width="38%"
                                        style="padding:11px 16px; border-top:1px solid #f0ecf8; font-size:14px; color:#6b6785;">
                                        Vencimiento</td>
                                    <td
                                        style="padding:11px 16px; border-top:1px solid #f0ecf8; font-size:14px; font-weight:600; color:#1f1a33;">
                                        {{ $fecha }}</td>
                                </tr>
                                <tr>
                                    <td width="38%"
                                        style="padding:11px 16px; border-top:1px solid #f0ecf8; font-size:14px; color:#6b6785;">
                                        Estado</td>
                                    <td
                                        style="padding:11px 16px; border-top:1px solid #f0ecf8; font-size:14px; font-weight:700; color:{{ $tonoTexto }};">
                                        {{ $estadoTexto }}</td>
                                </tr>
                            </table>
                        </td>
                    </tr>

                    @if ($mailto)
                        <tr>
                            <td align="center" style="padding:26px 32px 6px;">
                                <a href="{{ $mailto }}"
                                    style="display:inline-block; background-color:#6d4aa8; color:#ffffff; font-size:15px; font-weight:600; text-decoration:none; padding:13px 28px; border-radius:10px;">{{ $textoBoton }}</a>
                            </td>
                        </tr>
                    @endif

                    <tr>
                        <td align="center"
                            style="padding:16px 32px 28px; font-size:13px; line-height:1.5; color:#6b6785;">
                            {{ $nota }}</td>
                    </tr>

                    {{-- ===== PIE ===== --}}
                    <tr>
                        <td align="center"
                            style="padding:22px 32px; background-color:#faf8ff; border-top:1px solid #ece7f6; font-size:12px; line-height:1.7; color:#6b6785;">
                            <div style="font-size:13px; font-weight:700; color:#4a3280;">{{ $sistema }}</div>
                            <div>Gestión y monitoreo de licencias</div>
                            @if ($contactoCorreo)
                                <div>{{ $contactoCorreo }}</div>
                            @endif
                            @if ($contactoTelefono)
                                <div>{{ $contactoTelefono }}</div>
                            @endif
                            @if ($contactoWeb)
                                <div>{{ $contactoWeb }}</div>
                            @endif
                            <div style="margin-top:10px; color:#8a85a3;">Este es un aviso automático del sistema de
                                licencias.</div>
                            <div style="color:#8a85a3;">© {{ $anio }} {{ $sistema }}</div>
                        </td>
                    </tr>
                </table>

            </td>
        </tr>
    </table>
</body>

</html>
