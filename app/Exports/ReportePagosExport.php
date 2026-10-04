<?php

namespace App\Exports;

use App\Models\Licencias\LicenciaPago;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

/**
 * Exporta el DETALLE de pagos, línea por línea (a diferencia del PDF, que
 * exporta el resumen agregado). Se recibe la colección ya filtrada por el
 * rango de fechas y la empresa elegidos en Reportes.php.
 */
class ReportePagosExport implements FromCollection, WithHeadings, WithMapping, ShouldAutoSize
{
    public function __construct(private Collection $pagos) {}

    public function collection(): Collection
    {
        return $this->pagos;
    }

    public function headings(): array
    {
        return ['Fecha de pago', 'Empresa', 'Licencia', 'Plan', 'Tipo', 'Monto (USD)'];
    }

    public function map($pago): array
    {
        /** @var LicenciaPago $pago */
        return [
            $pago->fecha_pago->format('d/m/Y H:i'),
            $pago->licencia?->empresa?->nombre_comercial ?? '—',
            $pago->licencia?->codigo_licencia ?? '—',
            $pago->licencia?->plan?->nombre ?? '—',
            $pago->fecha_vencimiento_anterior ? 'Renovación' : 'Venta / activación',
            (float) $pago->monto,
        ];
    }
}