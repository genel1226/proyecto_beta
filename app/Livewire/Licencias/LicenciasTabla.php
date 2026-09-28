<?php

namespace App\Livewire\Licencias;

use App\Models\Licencias\Licencias as Licencia;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Gate;
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
        // Sin este permiso no se consulta nada (responde 403), aunque
        // alguien logre montar el componente por otra vía.
        Gate::authorize('licenses.index');

        // with() evita el problema de N+1 consultas: sin esto, por cada
        // fila se dispararía una consulta aparte para traer el nombre
        // de la empresa y otra para el plan.
        //
        // renovaciones_count: cuántos pagos de renovación tiene la licencia
        // (los que traen fecha_vencimiento_anterior). De ahí sale "Renovada".
        return Licencia::query()
            ->with(['empresa', 'plan'])
            ->withCount([
                'pagos as renovaciones_count' => fn ($q) => $q->whereNotNull('fecha_vencimiento_anterior'),
            ]);
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
            ->add('codigo_licencia_lower', fn (Licencia $model) => strtolower(e($model->codigo_licencia)))

            // Nombre de empresa y plan, en vez del id crudo
            ->add('empresa_nombre', fn (Licencia $model) => e($model->empresa?->razon_social ?? '—'))
            ->add('plan_nombre', fn (Licencia $model) => e($model->plan?->nombre ?? '—'))

            ->add('fecha_inicio_formatted', fn (Licencia $model) => Carbon::parse($model->fecha_inicio)->format('d/m/Y'))
            ->add('fecha_vencimiento_formatted', fn (Licencia $model) => Carbon::parse($model->fecha_vencimiento)->format('d/m/Y'))

            // Periodicidad legible en vez del char crudo (M/A/P)
            ->add('periodicidad_formatted', fn (Licencia $model) => match ($model->periodicidad) {
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
            ->add('estado_badge', function (Licencia $model) {
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

            // "Renovada ×2": cuántas veces se pagó antes de vencer.
            ->add('renovada_badge', function (Licencia $model) {
                $veces = (int) ($model->renovaciones_count ?? 0);

                if ($veces === 0) {
                    return '—';
                }

                return Blade::render(
                    '<flux:badge color="violet" size="sm">Renovada ×{{ $veces }}</flux:badge>',
                    ['veces' => $veces]
                );
            })

            ->add('observaciones')
            ->add('created_at_formatted', fn (Licencia $model) => Carbon::parse($model->created_at)->format('d/m/Y H:i:s'));
    }

    /**
     * Las columnas delicadas (monto, descuento, observaciones) solo se
     * definen si el usuario tiene el permiso. Si la columna no existe,
     * PowerGrid no pinta el dato, no se puede ordenar por ella y tampoco
     * entra en la búsqueda.
     */
    public function columns(): array
    {
        $hayAcciones = Gate::allows('licenses.edit')
            || Gate::allows('licenses.renovar')
            || Gate::allows('licenses.activar')
            || Gate::allows('licenses.baja');

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

            ...$this->cuando(Gate::allows('licenses.monto.ver'), [
                Column::make('Monto', 'monto')
                    ->sortable(),
            ]),

            ...$this->cuando(Gate::allows('licenses.descuento.ver'), [
                Column::make('Descuento', 'descuento')
                    ->sortable(),
            ]),

            // Column::make('Moneda', 'moneda')
            //     ->sortable(),

            // Estado como badge de color, en vez del char crudo (V/X/N/P/C)
            Column::make('Estado', 'estado_badge', 'estado')
                ->sortable(),

            Column::make('Renovada', 'renovada_badge'),

            ...$this->cuando(Gate::allows('licenses.observaciones.ver'), [
                Column::make('Observaciones', 'observaciones')
                    ->sortable()
                    ->searchable(),
            ]),

            // Column::make('Created at', 'created_at_formatted', 'created_at')
            //     ->sortable(),

            ...$this->cuando($hayAcciones, [
                Column::action('Action'),
            ]),
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

            ...$this->cuando(Gate::allows('licenses.observaciones.ver'), [
                Filter::inputText('observaciones')->operators(['contains']),
            ]),

            Filter::datetimepicker('created_at'),
        ];
    }

    // Cada botón dispara un evento que esta tabla reenvía al componente
    // Licencias (el de los modales), que es quien vuelve a validar el permiso.

    #[On('edit')]
    public function edit($rowId): void
    {
        $this->dispatch('editar-licencia', id: $rowId);
    }

    #[On('renovar')]
    public function renovar($rowId): void
    {
        $this->dispatch('confirmar-renovacion', id: $rowId);
    }

    #[On('activar')]
    public function activar($rowId): void
    {
        $this->dispatch('confirmar-activacion', id: $rowId);
    }

    #[On('baja')]
    public function baja($rowId): void
    {
        $this->dispatch('confirmar-baja', id: $rowId);
    }

    /**
     * Los botones se deciden fila por fila: solo aparecen si el usuario tiene
     * el permiso Y el estado de la licencia admite esa acción.
     *
     *   Vigente / Por vencer -> editar, renovar, dar de baja
     *   En proceso           -> editar, activar, dar de baja
     *   Cancelada            -> reactivar
     *   Vencida              -> nada (para seguir, se crea una licencia nueva)
     */
    public function actions(Licencia $row): array
    {
        $botones = [];

        $vigente = in_array($row->estado, ['V', 'X'], true);
        $enProceso = $row->estado === 'P';
        $cancelada = $row->estado === 'C';

        if (($vigente || $enProceso) && Gate::allows('licenses.edit')) {
            $botones[] = Button::add('edit')
                ->slot(Blade::render('<span title="Editar"><flux:icon.pencil-square class="size-4" /></span>'))
                ->id()
                ->class('pg-btn-white dark:ring-pg-primary-600 dark:border-pg-primary-600 dark:hover:bg-pg-primary-700 dark:ring-offset-pg-primary-800 dark:text-pg-primary-300 dark:bg-pg-primary-700')
                ->dispatch('edit', ['rowId' => $row->id]);
        }

        if ($vigente && Gate::allows('licenses.renovar')) {
            $botones[] = Button::add('renovar')
                ->slot(Blade::render('<span title="Renovar"><flux:icon.arrow-path class="size-4" /></span>'))
                ->id()
                ->class('pg-btn-white text-blue-600 border-blue-300 hover:bg-blue-50 dark:text-blue-400 dark:border-blue-800 dark:hover:bg-blue-950')
                ->dispatch('renovar', ['rowId' => $row->id]);
        }

        if (($enProceso || $cancelada) && Gate::allows('licenses.activar')) {
            $titulo = $cancelada ? 'Reactivar' : 'Activar';

            $botones[] = Button::add('activar')
                ->slot(Blade::render('<span title="'.$titulo.'"><flux:icon.check-circle class="size-4" /></span>'))
                ->id()
                ->class('pg-btn-white text-emerald-600 border-emerald-300 hover:bg-emerald-50 dark:text-emerald-400 dark:border-emerald-800 dark:hover:bg-emerald-950')
                ->dispatch('activar', ['rowId' => $row->id]);
        }

        if (($vigente || $enProceso) && Gate::allows('licenses.baja')) {
            $botones[] = Button::add('baja')
                ->slot(Blade::render('<span title="Dar de baja"><flux:icon.trash class="size-4" /></span>'))
                ->id()
                ->class('pg-btn-white text-red-600 border-red-300 hover:bg-red-50 dark:text-red-400 dark:border-red-800 dark:hover:bg-red-950')
                ->dispatch('baja', ['rowId' => $row->id]);
        }

        return $botones;
    }

    /**
     * Devuelve $items si se cumple la condición; si no, un arreglo vacío.
     * Se usa con "..." para meter columnas o filtros de forma condicional.
     *
     * @param  array<int, mixed>  $items
     * @return array<int, mixed>
     */
    private function cuando(bool $condicion, array $items): array
    {
        return $condicion ? $items : [];
    }
}
