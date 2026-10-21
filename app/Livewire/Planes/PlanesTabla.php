<?php

namespace App\Livewire\Planes;

use App\Models\Plan\Plan;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\On;
use PowerComponents\LivewirePowerGrid\Button;
use PowerComponents\LivewirePowerGrid\Column;
use PowerComponents\LivewirePowerGrid\Facades\Filter;
use PowerComponents\LivewirePowerGrid\Facades\PowerGrid;
use PowerComponents\LivewirePowerGrid\PowerGridComponent;
use PowerComponents\LivewirePowerGrid\PowerGridFields;

final class PlanesTabla extends PowerGridComponent
{
    public string $tableName = 'planesTablaTable';

    public function setUp(): array
    {
        return [
            PowerGrid::header()
                ->showSearchInput(),
            PowerGrid::footer()
                ->showPerPage()
                ->showRecordCount(),
        ];
    }

    /** Escucha el aviso del componente Planes al guardar o eliminar: forzar que se vuelva a consultar. */
    #[On('plan-guardado')]
    public function refrescar(): void
    {
        //
    }

    public function datasource(): Builder
    {
        Gate::authorize('planes.index');

        return Plan::query()->withCount('licencias');
    }

    public function relationSearch(): array
    {
        return [];
    }

    public function fields(): PowerGridFields
    {
        return PowerGrid::fields()
            ->add('id')
            ->add('nombre')
            ->add('sigla')
            ->add('monto')
            ->add('monto_formateado', fn(Plan $p) => '$' . number_format((float) $p->monto, 2))
            ->add('lapso_label', fn(Plan $p) => match ($p->lapso) {
                'M' => 'Mensual',
                'A' => 'Anual',
                default => $p->lapso ?: '—',
            })
            ->add('tipo')
            ->add('tipo_badge', function (Plan $p) {
                [$label, $color] = match ((string) $p->tipo) {
                    '1' => ['Primario', 'green'],
                    '2' => ['Personal', 'blue'],
                    default => ['Otro', 'zinc'],
                };

                return Blade::render(
                    '<flux:badge color="{{ $color }}" size="sm">{{ $label }}</flux:badge>',
                    ['color' => $color, 'label' => $label]
                );
            })
            ->add('minimo', fn(Plan $p) => $p->cantidad_min ? (int) $p->cantidad_min . ' usuarios' : '—')
            ->add('licencias_en_uso', fn(Plan $p) => (int) $p->licencias_count);
    }

    public function columns(): array
    {
        return [
            Column::make('Nombre', 'nombre')
                ->sortable()
                ->searchable(),

            Column::make('Sigla', 'sigla')
                ->sortable()
                ->searchable(),

            Column::make('Monto base', 'monto_formateado', 'monto')
                ->sortable(),

            Column::make('Periodo', 'lapso_label', 'lapso')
                ->sortable(),

            Column::make('Tipo', 'tipo_badge', 'tipo')
                ->sortable(),

            Column::make('Mínimo', 'minimo', 'cantidad_min')
                ->sortable(),

            Column::make('Licencias', 'licencias_en_uso'),

            Column::action('Acciones'),
        ];
    }

    public function filters(): array
    {
        return [
            Filter::inputText('nombre')->operators(['contains']),
        ];
    }

    /** Los botones dependen del permiso. Eliminar solo aparece si ninguna licencia usa el plan. */
    public function actions(Plan $row): array
    {
        $botones = [];

        if (Gate::allows('planes.edit')) {
            $botones[] = Button::add('edit')
                ->slot(Blade::render('<flux:icon.pencil-square class="size-4" />'))
                ->id()
                ->class('btn-accion btn-ambar')
                ->tooltip('Editar plan')
                ->dispatch('editar-plan', ['id' => $row->id]);
        }

        if (Gate::allows('planes.eliminar') && (int) $row->licencias_count === 0) {
            $botones[] = Button::add('eliminar')
                ->slot(Blade::render('<flux:icon.trash class="size-4" />'))
                ->id()
                ->class('btn-accion btn-rojo')
                ->tooltip('Eliminar plan')
                ->dispatch('confirmar-eliminar-plan', ['id' => $row->id]);
        }

        return $botones;
    }
}
