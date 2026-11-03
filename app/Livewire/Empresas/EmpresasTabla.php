<?php

namespace App\Livewire\Empresas;

use App\Models\Empresa\Empresa;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\On;
use PowerComponents\LivewirePowerGrid\Column;
use PowerComponents\LivewirePowerGrid\Facades\Filter;
use PowerComponents\LivewirePowerGrid\Facades\PowerGrid;
use PowerComponents\LivewirePowerGrid\PowerGridComponent;
use PowerComponents\LivewirePowerGrid\PowerGridFields;

final class EmpresasTabla extends PowerGridComponent
{
    public string $tableName = 'empresasTablaTable';

    // Orden por defecto: la última empresa creada primero (el id crece con cada alta).
    // Si el usuario ordena por otra columna, esa elección manda hasta que recargue.
    public string $sortField = 'id';

    public string $sortDirection = 'desc';

    public function setUp(): array
    {
        // $this->showCheckBox();

        return [
            PowerGrid::header()
                ->showSearchInput(),
            PowerGrid::footer()
                ->showPerPage()
                ->showRecordCount(),

            // Detalle desplegable de cada fila (razón social, NIT, correo, teléfono, etc.), como en Licencias.
            // Se abre con el botón "Ver detalles" que va junto al menú de tres puntos (campo acciones_menu).
            // Ver resources/views/livewire/empresas/detalle.blade.php
            PowerGrid::detail()
                ->view('livewire.empresas.detalle'),
        ];
    }

    /**
     * Escucha el aviso que manda el componente Empresas al guardar o al
     * activar/desactivar. No necesita hacer nada adentro: que Livewire
     * ejecute este método ya fuerza que PowerGrid vuelva a consultar.
     */
    #[On('empresa-guardada')]
    public function refrescar(): void
    {
        //
    }

    public function datasource(): Builder
    {
        // Sin este permiso no se consulta nada (responde 403).
        Gate::authorize('empresas.index');

        return Empresa::query();
    }

    public function relationSearch(): array
    {
        return [];
    }

    public function fields(): PowerGridFields
    {
        return PowerGrid::fields()
            ->add('id')
            ->add('cod')
            ->add('razon_social')
            ->add('nombre_comercial')
            ->add('nit')
            ->add('slug')
            ->add('email')
            ->add('telefono')
            ->add('active')
            ->add('pais')
            ->add('direccion')

            // Badge de estado: verde si está activa, gris si está inactiva
            ->add('estado_badge', function (Empresa $model) {
                [$label, $color] = $model->active ? ['Activa', 'green'] : ['Inactiva', 'zinc'];

                return Blade::render(
                    '<flux:badge color="{{ $color }}" size="sm">{{ $label }}</flux:badge>',
                    ['color' => $color, 'label' => $label]
                );
            })

            // Tipo de cuenta: demo (con o sin la prueba vencida), con licencia, o sin licencia
            ->add('licencia_badge', function (Empresa $model) {
                [$label, $color] = match (true) {
                    $model->trial_ends_at !== null && $model->trial_ends_at->isPast() => ['Demo vencida', 'rose'],
                    $model->trial_ends_at !== null => ['Demo hasta ' . $model->trial_ends_at->format('d/m/Y'), 'violet'],
                    $model->con_licencia === '1' => ['Con licencia', 'green'],
                    default => ['Sin licencia', 'zinc'],
                };

                return Blade::render(
                    '<flux:badge color="{{ $color }}" size="sm">{{ $label }}</flux:badge>',
                    ['color' => $color, 'label' => $label]
                );
            })

            // Usuarios contratados: suma de los usuarios de sus licencias activas (vigentes o por vencer), de todos los
            // tipos. El desglose por tipo está en el detalle desplegable.
            ->add('usuarios_total', function (Empresa $model) {
                $total = (int) $model->licencias()
                    ->whereIn('estado', ['V', 'X'])
                    ->withSum('detalleUsuarios as usuarios', 'cantidad')
                    ->get()
                    ->sum('usuarios');

                return $total > 0 ? (string) $total : '—';
            })

            // Próximo pago = vencimiento de su licencia vigente (o por vencer).
            // Rojo con 7 días o menos, ámbar con 15 o menos, verde después.
            ->add('proximo_pago', function (Empresa $model) {
                $vencimiento = $model->licencias()
                    ->whereIn('estado', ['V', 'X'])
                    ->orderBy('fecha_vencimiento')
                    ->value('fecha_vencimiento');

                if (! $vencimiento) {
                    return '—';
                }

                $fecha = Carbon::parse($vencimiento);
                $dias = (int) now()->startOfDay()->diff($fecha->copy()->startOfDay())->format('%r%a');

                $color = match (true) {
                    $dias <= 7 => 'red',
                    $dias <= 15 => 'amber',
                    default => 'green',
                };

                return Blade::render(
                    '<flux:badge color="{{ $color }}" size="sm">{{ $texto }}</flux:badge>',
                    ['color' => $color, 'texto' => $fecha->format('d/m/Y')]
                );
            })

            // Menú de tres puntos con las acciones de la fila + botón "Ver detalles" a su izquierda
            // (components/menu-acciones.blade.php, el mismo de Licencias).
            ->add('acciones_menu', function (Empresa $model) {
                return Blade::render(
                    '<x-menu-acciones :items="$items" :detalle="$detalle" />',
                    ['items' => $this->accionesDe($model), 'detalle' => "toggleDetail('{$model->id}')"]
                );
            })

            ->add('created_at_formatted', fn(Empresa $model) => Carbon::parse($model->created_at)->format('d/m/Y H:i:s'));
    }

    /**
     * La tabla muestra lo esencial; el resto (razón social, NIT, correo, teléfono, país, último pago...)
     * está en el detalle desplegable. Las columnas ocultas siguen siendo buscables: el buscador de arriba
     * encuentra una empresa por su razón social, NIT, correo, teléfono o país aunque no se vean.
     */
    public function columns(): array
    {
        return [
            // Column::make('Id', 'id'),

            Column::make('Código', 'cod')
                ->sortable()
                ->searchable(),

            Column::make('Nombre comercial', 'nombre_comercial')
                ->sortable()
                ->searchable(),

            Column::make('Razón social', 'razon_social')->searchable()->hidden(),
            Column::make('NIT', 'nit')->searchable()->hidden(),
            Column::make('Email', 'email')->searchable()->hidden(),
            Column::make('Teléfono', 'telefono')->searchable()->hidden(),
            Column::make('País', 'pais')->searchable()->hidden(),

            Column::make('Licencia', 'licencia_badge'),

            Column::make('Usuarios', 'usuarios_total'),

            Column::make('Próximo pago', 'proximo_pago'),

            Column::make('Estado', 'estado_badge', 'active')
                ->sortable(),

            // Una sola columna con "Ver detalles" + el menú de tres puntos.
            Column::make('Acciones', 'acciones_menu'),
        ];
    }

    public function filters(): array
    {
        return [
            Filter::inputText('cod')->operators(['contains']),
            Filter::inputText('nombre_comercial')->operators(['contains']),
            Filter::boolean('active'),
        ];
    }

    // Los items del menú llaman a estos métodos; cada uno reenvía el aviso al componente Empresas (el del
    // modal), que vuelve a validar el permiso en el servidor.

    public function editarEmpresa(int $id): void
    {
        $this->dispatch('editar-empresa', id: $id);
    }

    public function estadoEmpresa(int $id): void
    {
        $this->dispatch('confirmar-estado-empresa', id: $id);
    }

    /**
     * Las acciones se deciden fila por fila y solo aparecen si el usuario tiene el permiso. Van dentro del
     * menú de tres puntos (los íconos de colores de siempre, cada uno con su tooltip).
     *
     * @return array<int, array<string, string>>
     */
    private function accionesDe(Empresa $row): array
    {
        $items = [];
        $id = (int) $row->id;

        if (Gate::allows('empresas.edit')) {
            $items[] = ['wire' => "editarEmpresa({$id})", 'etiqueta' => 'Editar empresa', 'icono' => 'pencil-square', 'clase' => 'btn-ambar'];
        }

        if (Gate::allows('empresas.desactivar')) {
            $items[] = $row->active
                ? ['wire' => "estadoEmpresa({$id})", 'etiqueta' => 'Desactivar empresa', 'icono' => 'no-symbol', 'clase' => 'btn-rojo']
                : ['wire' => "estadoEmpresa({$id})", 'etiqueta' => 'Reactivar empresa', 'icono' => 'check-circle', 'clase' => 'btn-verde'];
        }

        return $items;
    }
}
