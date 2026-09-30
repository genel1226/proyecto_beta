<?php

namespace App\Livewire\Reportes;

use App\Exports\ReportePagosExport;
use App\Models\Empresa\Empresa;
use App\Models\Licencias\LicenciaPago;
use App\Models\Licencias\Licencias as ModelsLicencias;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Gate;
use Livewire\Component;
use Maatwebsite\Excel\Facades\Excel;

class Reportes extends Component
{
    public string $desde;

    public string $hasta;

    public ?int $empresa_id = null;

    public function mount(): void
    {
        Gate::authorize('reportes.index');

        // Rango por defecto: lo que va del mes actual.
        $this->desde = now()->startOfMonth()->format('Y-m-d');
        $this->hasta = now()->format('Y-m-d');
    }

    /**
     * Base de la consulta de pagos, según los filtros activos. Se usa
     * clonada en cada reporte para no arrastrar el where de uno al otro.
     */
    private function consultaPagos(): Builder
    {
        return LicenciaPago::query()
            ->whereDate('fecha_pago', '>=', $this->desde)
            ->whereDate('fecha_pago', '<=', $this->hasta)
            ->when(
                $this->empresa_id,
                fn ($q) => $q->whereHas('licencia', fn ($qq) => $qq->where('empresa_id', $this->empresa_id))
            );
    }

    /**
     * Los KPIs de facturación son del rango elegido; los de licencias y
     * empresas son una "foto" de ahora mismo, no del rango (no tendría
     * sentido decir "empresas activas en marzo" si ya es octubre).
     */
    public function getKpisProperty(): array
    {
        $pagos = (clone $this->consultaPagos())->get();

        return [
            'total_facturado' => (float) $pagos->sum('monto'),
            'cantidad_pagos' => $pagos->count(),
            'licencias_activas' => ModelsLicencias::whereIn('estado', ['V', 'X'])->count(),
            'por_vencer_15' => ModelsLicencias::whereIn('estado', ['V', 'X'])
                ->whereRaw('DATEDIFF(fecha_vencimiento, CURDATE()) BETWEEN 0 AND 15')
                ->count(),
            'empresas_activas' => Empresa::where('active', 1)->count(),
        ];
    }

    public function getFacturacionPorMesProperty()
    {
        return (clone $this->consultaPagos())
            ->selectRaw("DATE_FORMAT(fecha_pago, '%Y-%m') as mes, SUM(monto) as total, COUNT(*) as cantidad")
            ->groupBy('mes')
            ->orderBy('mes')
            ->get();
    }

    public function getFacturacionPorEmpresaProperty()
    {
        return (clone $this->consultaPagos())
            ->join('licencias', 'licencias.id', '=', 'licencia_pagos.licencia_id')
            ->join('empresas', 'empresas.id', '=', 'licencias.empresa_id')
            ->selectRaw('empresas.nombre_comercial, SUM(licencia_pagos.monto) as total, COUNT(*) as cantidad')
            ->groupBy('empresas.id', 'empresas.nombre_comercial')
            ->orderByDesc('total')
            ->limit(10)
            ->get();
    }

    /**
     * Conteo de licencias por estado — foto de ahora, no del rango.
     */
    public function getLicenciasPorEstadoProperty()
    {
        return ModelsLicencias::selectRaw('estado, COUNT(*) as cantidad')
            ->groupBy('estado')
            ->pluck('cantidad', 'estado');
    }

    public function exportarExcel()
    {
        if (! Gate::allows('reportes.export')) {
            return;
        }

        $pagos = (clone $this->consultaPagos())
            ->with(['licencia.empresa', 'licencia.plan'])
            ->orderBy('fecha_pago')
            ->get();

        $nombre = "reporte-pagos-{$this->desde}-a-{$this->hasta}.xlsx";

        return Excel::download(new ReportePagosExport($pagos), $nombre);
    }

    public function exportarPdf()
    {
        if (! Gate::allows('reportes.export')) {
            return;
        }

        $pdf = Pdf::loadView('pdf.reporte-ventas', [
            'desde' => $this->desde,
            'hasta' => $this->hasta,
            'kpis' => $this->kpis,
            'porMes' => $this->facturacionPorMes,
            'porEmpresa' => $this->facturacionPorEmpresa,
        ]);

        $nombre = "reporte-ventas-{$this->desde}-a-{$this->hasta}.pdf";

        // Pdf::download() devuelve una respuesta genérica que Livewire no
        // reconoce como "archivo para descargar", y termina intentando
        // meter el binario del PDF en su respuesta JSON normal (de ahí el
        // error de "Malformed UTF-8 characters"). Guardarlo primero y
        // usar response()->download() sí genera un BinaryFileResponse,
        // que es el mismo tipo de respuesta con el que ya funciona el Excel.
        $carpetaTemporal = storage_path('app/temp');

        if (! is_dir($carpetaTemporal)) {
            mkdir($carpetaTemporal, recursive: true);
        }

        $rutaTemporal = $carpetaTemporal.'/'.$nombre;
        $pdf->save($rutaTemporal);

        return response()->download($rutaTemporal, $nombre)->deleteFileAfterSend();
    }

    public function render()
    {
        return view('livewire.reportes.reportes', [
            'empresas' => Empresa::orderBy('razon_social')->get(),
        ]);
    }
}