<?php

namespace App\Livewire\Licencias;

use App\Models\Licencias\Licencias as Licencia;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\On;
use PowerComponents\LivewirePowerGrid\Column;
use PowerComponents\LivewirePowerGrid\Facades\Filter;
use PowerComponents\LivewirePowerGrid\Facades\PowerGrid;
use PowerComponents\LivewirePowerGrid\PowerGridComponent;
use PowerComponents\LivewirePowerGrid\PowerGridFields;

final class LicenciasTabla extends PowerGridComponent
{
    public string $tableName = 'licenciasTablaTable';

    // Orden por defecto: la última licencia creada primero (el id crece con cada alta).
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

            // Detalle desplegable de cada fila con los datos menos importantes
            // (ver resources/views/livewire/licencias/detalle.blade.php).
            // Sin flecha propia (showCollapseIcon): el detalle se abre con el botón "Ver detalles"
            // que va junto al menú de tres puntos (campo acciones_menu). Pueden quedar varias filas abiertas a la vez.
            PowerGrid::detail()
                ->view('livewire.licencias.detalle'),
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
                'pagos as renovaciones_count' => fn($q) => $q->whereNotNull('fecha_vencimiento_anterior'),
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
            ->add('codigo_licencia_lower', fn(Licencia $model) => strtolower(e($model->codigo_licencia)))

            // Nombre de empresa y plan, en vez del id crudo
            ->add('empresa_nombre', fn(Licencia $model) => e($model->empresa?->razon_social ?? '—'))
            ->add('plan_nombre', fn(Licencia $model) => e($model->plan?->nombre ?? '—'))

            ->add('fecha_inicio_formatted', fn(Licencia $model) => Carbon::parse($model->fecha_inicio)->format('d/m/Y'))
            ->add('fecha_vencimiento_formatted', fn(Licencia $model) => Carbon::parse($model->fecha_vencimiento)->format('d/m/Y'))

            // Periodicidad legible en vez del char crudo (M/A/P)
            ->add('periodicidad_formatted', fn(Licencia $model) => match ($model->periodicidad) {
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

            // Menú de tres puntos con las acciones de la fila (components/menu-acciones.blade.php).
            // Se dibuja con Blade::render igual que los badges, así no depende de la versión de PowerGrid.
            ->add('acciones_menu', function (Licencia $model) {
                // "Ver detalles" va siempre, a la izquierda del menú de tres puntos; el menú solo si hay acciones.
                return Blade::render(
                    '<x-menu-acciones :items="$items" :detalle="$detalle" />',
                    ['items' => $this->accionesDe($model), 'detalle' => "toggleDetail('{$model->id}')"]
                );
            })

            ->add('observaciones')
            ->add('created_at_formatted', fn(Licencia $model) => Carbon::parse($model->created_at)->format('d/m/Y H:i:s'));
    }

    /**
     * Las columnas delicadas (monto, descuento, observaciones) solo se
     * definen si el usuario tiene el permiso. Si la columna no existe,
     * PowerGrid no pinta el dato, no se puede ordenar por ella y tampoco
     * entra en la búsqueda.
     */
    public function columns(): array
    {
        return [
            // Column::make('Id', 'id'),
            Column::make('Codigo licencia', 'codigo_licencia')
                ->sortable()
                ->searchable(),

            Column::make('Empresa', 'empresa_nombre')
                ->sortable()
                ->searchable(),

            Column::make('Plan', 'plan_nombre')
                ->sortable()
                ->searchable(),


            // Fecha de inicio, periodicidad, descuento, observaciones, renovaciones y fecha de alta
            // viven en el detalle desplegable de cada fila: la tabla queda sin scroll horizontal.
            Column::make('Fecha vencimiento', 'fecha_vencimiento_formatted', 'fecha_vencimiento')
                ->sortable(),

            ...$this->cuando(Gate::allows('licenses.monto.ver'), [
                Column::make('Monto', 'monto')
                    ->sortable(),
            ]),

            // Estado como badge de color, en vez del char crudo (V/X/N/P/C)
            Column::make('Estado', 'estado_badge', 'estado')
                ->sortable(),

            // Se puede seguir buscando por observaciones aunque la columna no se vea.
            ...$this->cuando(Gate::allows('licenses.observaciones.ver'), [
                Column::make('Observaciones', 'observaciones')
                    ->searchable()
                    ->hidden(),
            ]),

            // Column::make('Created at', 'created_at_formatted', 'created_at')
            //     ->sortable(),

            // Una sola columna con el menú de tres puntos.
            Column::make('Acciones', 'acciones_menu'),
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

    #[On('ver-pagos')]
    public function verPagos($rowId): void
    {
        $this->dispatch('ver-pagos-licencia', id: $rowId);
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
     * Las acciones se deciden fila por fila: solo aparecen si el usuario tiene
     * el permiso Y el estado de la licencia admite esa acción. Todas van dentro
     * de un menú de tres puntos (components/menu-acciones.blade.php) para que la
     * columna sea angosta y no se desplace la tabla.
     *
     *   Vigente / Por vencer -> editar, renovar, dar de baja
     *   En proceso           -> editar, activar, dar de baja
     *   Cancelada            -> reactivar
     *   Vencida              -> nada (para seguir, se crea una licencia nueva)
     *
     * Devuelve los items del menú; fields() los dibuja en la columna 'acciones_menu'.
     *
     * @return array<int, array<string, mixed>>
     */
    private function accionesDe(Licencia $row): array
    {
        $items = [];

        $vigente = in_array($row->estado, ['V', 'X'], true);
        $enProceso = $row->estado === 'P';
        $cancelada = $row->estado === 'C';
        $id = (int) $row->id;

        // Ver pagos no depende del estado: hasta una licencia Vencida o
        // Cancelada puede necesitar consultarse su historial.
        if (Gate::allows('pagos.index')) {
            $items[] = ['wire' => "verPagos({$id})", 'etiqueta' => 'Ver historial de pagos', 'icono' => 'banknotes', 'clase' => 'btn-celeste'];
        }

        if (($vigente || $enProceso) && Gate::allows('licenses.edit')) {
            $items[] = ['wire' => "edit({$id})", 'etiqueta' => 'Editar licencia', 'icono' => 'pencil-square', 'clase' => 'btn-ambar'];
        }

        if ($vigente && Gate::allows('licenses.renovar')) {
            $items[] = ['wire' => "renovar({$id})", 'etiqueta' => 'Renovar licencia', 'icono' => 'arrow-path', 'clase' => 'btn-violeta'];
        }

        if (($enProceso || $cancelada) && Gate::allows('licenses.activar')) {
            $items[] = [
                'wire' => "activar({$id})",
                'etiqueta' => $cancelada ? 'Reactivar licencia' : 'Activar licencia',
                'icono' => 'check-circle',
                'clase' => 'btn-verde',
            ];
        }

        if (($vigente || $enProceso) && Gate::allows('licenses.baja')) {
            $items[] = ['wire' => "baja({$id})", 'etiqueta' => 'Dar de baja la licencia', 'icono' => 'trash', 'clase' => 'btn-rojo'];
        }

        return $items;
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
