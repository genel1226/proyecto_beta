<meta charset="utf-8" />
<meta name="viewport" content="width=device-width, initial-scale=1.0" />

<title>
    {{ filled($title ?? null) ? $title.' - '.config('app.name', 'Laravel') : config('app.name', 'Laravel') }}
</title>

<link rel="icon" href="/favicon.ico" sizes="any">
<link rel="icon" href="/favicon.svg" type="image/svg+xml">
<link rel="apple-touch-icon" href="/apple-touch-icon.png">

@fonts

@vite(['resources/css/app.css', 'resources/js/app.js'])
{{-- Modo CLARO por defecto, solo la primera vez que se abre el sistema en este navegador. Si después se
     elige «Sistema» u «Oscuro» (Configuración → Apariencia, o el botón de la luna), se respeta esa elección. --}}
<script>
    if (!window.localStorage.getItem('tema.inicializado')) {
        window.localStorage.setItem('tema.inicializado', '1');

        if (!window.localStorage.getItem('flux.appearance')) {
            window.localStorage.setItem('flux.appearance', 'light');
        }
    }
</script>
@fluxAppearance
