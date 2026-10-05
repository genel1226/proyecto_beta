<x-layouts::app.sidebar :title="$title ?? null">
    {{-- inset: la página no hace scroll completa, solo se desplaza el contenido. Así el menú lateral y el
         encabezado quedan FIJOS. (Un position: sticky en el encabezado no serviría: vive en una fila de la
         cuadrícula que mide justo lo que él mide, y no tiene espacio para "pegarse".) --}}
    <flux:main inset>
        {{ $slot }}
    </flux:main>
</x-layouts::app.sidebar>
