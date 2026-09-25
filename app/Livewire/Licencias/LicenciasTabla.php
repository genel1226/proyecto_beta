<?php

namespace App\Livewire\Licencias;

use App\Models\Licencias\Licencias;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Blade;
use Livewire\Attributes\On;
use PowerComponents\LivewirePowerGrid\Button;
use PowerComponents\LivewirePowerGrid\Column;
use PowerComponents\LivewirePowerGrid\Facades\Filter;
use PowerComponents\LivewirePowerGrid\Facades\PowerGrid;
use PowerComponents\LivewirePowerGrid\PowerGridComponent;
use PowerComponents\LivewirePowerGrid\PowerGridFields;

final class LicenciasTabla extends PowerGridComponent
{
    public string $tableName = 'licenciasTablaTable';

    public function setUp(): array
    {
        // $this->showCheckBox();

        return [
            PowerGrid::header()
                ->showSearchInput(),
            PowerGrid::footer()
                ->showPerPage()
                ->showRecordCount(),
        ];
    }

    /**
     * Escucha el aviso que manda el formulario de "Nueva licencia" al
     * guardar. No necesita hacer nada adentro: que Livewire ejecute este
     * método ya fuerza que PowerGrid vuelva a consultar datasource().
     */
    #[On('licencia-creada')]
    public function refrescar(): void
    {
        //
    }

    public function datasource(): Builder
    {
        // with() evita el problema de N+1 consultas: sin esto, por cada
        // fila se dispararía una consulta aparte para traer el nombre
        // de la empresa y otra para el plan.
        return Licencias::query()->with(['empresa', 'plan']);
    }

    public function relationSearch(): array
    {
        // Si más adelante quieres que el buscador de arriba también
        // encuentre licencias por razón social o nombre de plan, aquí
        // se declara la relación para que PowerGrid arme el JOIN:
        // return [
        //     'empresa' => ['razon_social'],
        //     'plan' => ['nombre'],
        // ];
        return [];
    }

    public function fields(): PowerGridFields
    {
        return PowerGrid::fields()
            ->add('id')
            ->add('empresa_id')
            ->add('plan_id')
            ->add('codigo_licencia')
            ->add('codigo_licencia_lower', fn (Licencias $model) => strtolower(e($model->codigo_licencias)))

            // Nombre de empresa y plan, en vez del id crudo
            ->add('empresa_nombre', fn (Licencias $model) => e($model->empresa?->razon_social ?? '—'))
            ->add('plan_nombre', fn (Licencias $model) => e($model->plan?->nombre ?? '—'))

            ->add('fecha_inicio_formatted', fn (Licencias $model) => Carbon::parse($model->fecha_inicio)->format('d/m/Y'))
            ->add('fecha_vencimiento_formatted', fn (Licencias $model) => Carbon::parse($model->fecha_vencimiento)->format('d/m/Y'))

            // Periodicidad legible en vez del char crudo (M/A/P)
            ->add('periodicidad_formatted', fn (Licencias $model) => match ($model->periodicidad) {
                'M' => 'Mensual',
                'A' => 'Anual',
                'P' => 'Personalizada',
                default => $model->periodicidad,
            })

            ->add('monto')
            ->add('descuento')
            ->add('moneda')
            ->add('estado')

            // Badge de estado, coloreado según días restantes hasta el
            // vencimiento (no solo según el código de estado guardado,
            // para que se vea la urgencia incluso un poco antes de que
            // el cron diario actualice estado a 'X' o 'N').
            ->add('estado_badge', function (Licencias $model) {
                $dias = (int) now()->startOfDay()->diff(
                    Carbon::parse($model->fecha_vencimiento)->startOfDay()
                )->format('%r%a');

                [$label, $color] = match (true) {
                    $model->estado === 'C' => ['Cancelada', 'zinc'],
                    $model->estado === 'P' => ['En proceso', 'blue'],
                    $model->estado === 'N' || $dias < 0 => ['Vencida', 'rose'],
                    $dias <= 7 => ['Por vencer', 'red'],
                    $dias <= 15 => ['Por vencer', 'amber'],
                    default => ['Vigente', 'green'],
                };

                return Blade::render(
                    '<flux:badge color="{{ $color }}" size="sm">{{ $label }}</flux:badge>',
                    ['color' => $color, 'label' => $label]
                );
            })

            ->add('observaciones')
            ->add('created_at_formatted', fn (Licencias $model) => Carbon::parse($model->created_at)->format('d/m/Y H:i:s'));
    }

    public function columns(): array
    {
        return [
            // Column::make('Id', 'id'),

            Column::make('Empresa', 'empresa_nombre')
                ->sortable()
                ->searchable(),

            Column::make('Plan', 'plan_nombre')
                ->sortable()
                ->searchable(),

            Column::make('Codigo licencia', 'codigo_licencia')
                ->sortable()
                ->searchable(),

            Column::make('Fecha inicio', 'fecha_inicio_formatted', 'fecha_inicio')
                ->sortable(),

            Column::make('Fecha vencimiento', 'fecha_vencimiento_formatted', 'fecha_vencimiento')
                ->sortable(),

            Column::make('Periodicidad', 'periodicidad_formatted', 'periodicidad')
                ->sortable(),

            Column::make('Monto', 'monto')
                ->sortable(),

            Column::make('Descuento', 'descuento')
                ->sortable(),

            // Column::make('Moneda', 'moneda')
            //     ->sortable(),

            // Estado como badge de color, en vez del char crudo (V/X/N/P/C)
            Column::make('Estado', 'estado_badge', 'estado')
                ->sortable(),

            Column::make('Observaciones', 'observaciones')
                ->sortable()
                ->searchable(),

            // Column::make('Created at', 'created_at_formatted', 'created_at')
            //     ->sortable(),

            Column::action('Action'),
        ];
    }

    public function filters(): array
    {
        return [
            Filter::inputText('codigo_licencia')->operators(['contains']),
            Filter::datepicker('fecha_inicio'),
            Filter::datepicker('fecha_vencimiento'),
            Filter::inputText('periodicidad')->operators(['contains']),
            Filter::inputText('moneda')->operators(['contains']),
            Filter::inputText('estado')->operators(['contains']),
            Filter::inputText('observaciones')->operators(['contains']),
            Filter::datetimepicker('created_at'),
        ];
    }

    #[On('edit')]
    public function edit($rowId): void
    {
        $this->js('alert('.$rowId.')');
    }

    public function actions(Licencias $row): array
    {
        return [
            Button::add('edit')
                ->slot('Edit: '.$row->id)
                ->id()
                ->class('pg-btn-white dark:ring-pg-primary-600 dark:border-pg-primary-600 dark:hover:bg-pg-primary-700 dark:ring-offset-pg-primary-800 dark:text-pg-primary-300 dark:bg-pg-primary-700')
                ->dispatch('edit', ['rowId' => $row->id]),
        ];
    }

    /*
    public function actionRules($row): array
    {
       return [
            // Hide button edit for ID 1
            Rule::button('edit')
                ->when(fn($row) => $row->id === 1)
                ->hide(),
        ];
    }
    */
}