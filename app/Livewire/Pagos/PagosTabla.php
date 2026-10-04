<?php

namespace App\Livewire\Pagos;

use App\Models\Licencias\LicenciaPago;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Gate;
use PowerComponents\LivewirePowerGrid\Column;
use PowerComponents\LivewirePowerGrid\Facades\Filter;
use PowerComponents\LivewirePowerGrid\Facades\PowerGrid;
use PowerComponents\LivewirePowerGrid\PowerGridComponent;
use PowerComponents\LivewirePowerGrid\PowerGridFields;

/**
 * Pantalla general de pagos: TODOS los pagos de TODAS las licencias.
 * Es la misma tabla que ve el historial dentro de una licencia (Licencias.php
 * ->verPagos()), solo que aquí sin filtrar por licencia_id, para responder
 * reportes tipo "cuánto facturamos este mes/por esta empresa".
 */
final class PagosTabla extends PowerGridComponent
{
    public string $tableName = 'pagosTablaTable';

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

    public function datasource(): Builder
    {
        Gate::authorize('pagos.index');

        // with(): evita N+1 (licencia -> empresa, licencia -> plan, quien registró).
        return LicenciaPago::query()->with(['licencia.empresa', 'licencia.plan', 'registradoPor']);
    }

    public function relationSearch(): array
    {
        return [];
    }

    public function fields(): PowerGridFields
    {
        return PowerGrid::fields()
            ->add('id')
            ->add('fecha_pago')
            ->add('fecha_pago_formatted', fn (LicenciaPago $p) => $p->fecha_pago->format('d/m/Y H:i'))

            ->add('empresa_nombre', fn (LicenciaPago $p) => e($p->licencia?->empresa?->nombre_comercial ?? '—'))
            ->add('codigo_licencia', fn (LicenciaPago $p) => e($p->licencia?->codigo_licencia ?? '—'))
            ->add('plan_nombre', fn (LicenciaPago $p) => e($p->licencia?->plan?->nombre ?? '—'))

            ->add('monto')

            // Sin fecha_vencimiento_anterior = fue el pago de la venta
            // inicial (o activación); con ella, fue una renovación.
            ->add('tipo_badge', function (LicenciaPago $p) {
                $esRenovacion = $p->fecha_vencimiento_anterior !== null;

                return Blade::render(
                    '<flux:badge color="{{ $color }}" size="sm">{{ $label }}</flux:badge>',
                    $esRenovacion
                        ? ['color' => 'violet', 'label' => 'Renovación']
                        : ['color' => 'green', 'label' => 'Venta / activación']
                );
            })

            ->add('registrado_por_nombre', fn (LicenciaPago $p) => e($p->registradoPor?->name ?? '—'))
            ->add('fecha_vencimiento_nueva_formatted', fn (LicenciaPago $p) => optional($p->fecha_vencimiento_nueva)->format('d/m/Y') ?? '—');
    }

    public function columns(): array
    {
        return [
            Column::make('Fecha de pago', 'fecha_pago_formatted', 'fecha_pago')
                ->sortable(),

            Column::make('Empresa', 'empresa_nombre')
                ->sortable()
                ->searchable(),

            Column::make('Licencia', 'codigo_licencia')
                ->sortable()
                ->searchable(),

            Column::make('Plan', 'plan_nombre')
                ->sortable()
                ->searchable(),

            Column::make('Tipo', 'tipo_badge'),

            Column::make('Monto', 'monto')
                ->sortable(),

            Column::make('Vence ahora', 'fecha_vencimiento_nueva_formatted', 'fecha_vencimiento_nueva')
                ->sortable(),

            Column::make('Registrado por', 'registrado_por_nombre')
                ->sortable(),
        ];
    }

    public function filters(): array
    {
        return [
            Filter::datepicker('fecha_pago'),
            Filter::datepicker('fecha_vencimiento_nueva'),
        ];
    }
}