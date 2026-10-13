{!! strtoupper($etiqueta) !!}
{!! $titulo !!}

Hola, {!! $saludo !!}:

{!! $mensaje !!}
{!! $consejo !!}

DETALLE DE LA LICENCIA
Licencia: {!! $codigo !!}
Empresa: {!! $empresa !!}
Plan: {!! $plan !!}
Vencimiento: {!! $fecha !!}
Estado: {!! $estadoTexto !!}

{!! $nota !!}

--
{!! $sistema !!} · Gestión y monitoreo de licencias
@if ($contactoCorreo)
    {!! $contactoCorreo !!}
@endif
@if ($contactoTelefono)
    {!! $contactoTelefono !!}
@endif
@if ($contactoWeb)
    {!! $contactoWeb !!}
@endif
Este es un aviso automático del sistema de licencias.
