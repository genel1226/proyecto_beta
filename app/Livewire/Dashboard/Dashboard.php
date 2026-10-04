<?php

namespace App\Livewire\Dashboard;

use App\Models\Empresa\Empresa;
use App\Models\Licencias\LicenciaPago;
use App\Models\Licencias\Licencias as ModelsLicencias;
use Illuminate\Support\Facades\Gate;
use Livewire\Component;

/**
 * Panel de monitoreo. No tiene un permiso propio: cualquier usuario con
 * sesión puede abrirlo, y cada bloque aparece solo si tiene el permiso del
 * módulo que muestra (licenses.index, empresas.index, pagos.index). Todo lo
 * que sea dinero exige además reportes.montos.
 *
 * Los datos se calculan en render() y se pasan a la vista; no son
 * propiedades públicas, así que nunca viajan al navegador.
 */
class Dashboard extends Component
{
    public function render()
    {
        $verLicencias = Gate::allows('licenses.index');
        $verEmpresas = Gate::allows('empresas.index');
        $verPagos = Gate::allows('pagos.index');
        $verMontos = Gate::allows('reportes.montos');

        $estados = $verLicencias ? $this->conteoPorEstado() : [];

        return view('livewire.dashboard.dashboard', [
            'verLicencias' => $verLicencias,
            'verEmpresas' => $verEmpresas,
            'verPagos' => $verPagos,
            'verMontos' => $verMontos,
            'hayAlgo' => $verLicencias || $verEmpresas || $verPagos || $verMontos,

            'estados' => $estados,
            'porVencer' => $verLicencias ? $this->licenciasPorVencer() : 0,
            'vencimientos' => $verLicencias ? $this->proximosVencimientos() : [],

            'empresasActivas' => $verEmpresas ? Empresa::where('active', 1)->count() : 0,
            'demos' => $verEmpresas ? $this->demos() : [],
            'demosEnCurso' => $verEmpresas
                ? Empresa::whereNotNull('trial_ends_at')->where('trial_ends_at', '>=', now())->count()
                : 0,

            'pagosRecientes' => $verPagos ? $this->pagosRecientes($verMontos) : [],

            'facturadoMes' => $verMontos
                ? (float) LicenciaPago::where('fecha_pago', '>=', now()->startOfMonth())->sum('monto')
                : 0.0,
            'facturacionMeses' => $verMontos ? $this->facturacionSeisMeses() : [],
        ]);
    }

    /**
     * Conteo por estado, con los 5 estados siempre presentes (en 0 si no hay).
     *
     * @return array<string, int>
     */
    private function conteoPorEstado(): array
    {
        $porEstado = ModelsLicencias::selectRaw('estado, COUNT(*) as cantidad')
            ->groupBy('estado')
            ->pluck('cantidad', 'estado');

        $conteo = [];

        foreach (['V', 'X', 'N', 'P', 'C'] as $codigo) {
            $conteo[$codigo] = (int) ($porEstado[$codigo] ?? 0);
        }

        return $conteo;
    }

    /**
     * Licencias vigentes que vencen en 15 días o menos. La cuenta de días la
     * hace MySQL (DATEDIFF/CURDATE), igual que el cron de alertas, para que
     * ambos coincidan siempre en qué día es "hoy".
     */
    private function licenciasPorVencer(): int
    {
        return ModelsLicencias::whereIn('estado', ['V', 'X'])
            ->whereRaw('DATEDIFF(fecha_vencimiento, CURDATE()) BETWEEN 0 AND 15')
            ->count();
    }

    /**
     * Las 8 licencias vigentes que vencen primero, con su color de urgencia
     * (mismos umbrales que el badge de la tabla de Licencias).
     *
     * @return array<int, array{empresa:string, codigo:string, plan:string, fecha:string, color:string, texto:string}>
     */
    private function proximosVencimientos(): array
    {
        return ModelsLicencias::with(['empresa', 'plan'])
            ->whereIn('estado', ['V', 'X'])
            ->orderBy('fecha_vencimiento')
            ->limit(8)
            ->get()
            ->map(function ($licencia) {
                $dias = (int) now()->startOfDay()
                    ->diff($licencia->fecha_vencimiento->copy()->startOfDay())
                    ->format('%r%a');

                [$color, $texto] = match (true) {
                    $dias < 0 => ['rose', 'Vencida'],
                    $dias === 0 => ['red', 'Vence hoy'],
                    $dias === 1 => ['red', 'Mañana'],
                    $dias <= 7 => ['red', "{$dias} días"],
                    $dias <= 15 => ['amber', "{$dias} días"],
                    default => ['green', "{$dias} días"],
                };

                return [
                    'empresa' => $licencia->empresa?->nombre_comercial ?? '—',
                    'codigo' => (string) $licencia->codigo_licencia,
                    'plan' => $licencia->plan?->nombre ?? '—',
                    'fecha' => $licencia->fecha_vencimiento->format('d/m/Y'),
                    'color' => $color,
                    'texto' => $texto,
                ];
            })
            ->all();
    }

    /**
     * Cuentas demo ordenadas por fin de prueba: las que están por terminar
     * (o ya terminaron) primero, para saber a quién darle seguimiento.
     *
     * @return array<int, array{cod:string, nombre:string, fecha:string, color:string, texto:string}>
     */
    private function demos(): array
    {
        return Empresa::whereNotNull('trial_ends_at')
            ->orderBy('trial_ends_at')
            ->limit(5)
            ->get()
            ->map(function ($empresa) {
                $dias = (int) now()->startOfDay()
                    ->diff($empresa->trial_ends_at->copy()->startOfDay())
                    ->format('%r%a');

                [$color, $texto] = match (true) {
                    $dias < 0 => ['rose', 'Prueba vencida'],
                    $dias === 0 => ['amber', 'Termina hoy'],
                    $dias <= 3 => ['amber', "{$dias} día(s)"],
                    default => ['violet', "{$dias} días"],
                };

                return [
                    'cod' => (string) $empresa->cod,
                    'nombre' => (string) $empresa->nombre_comercial,
                    'fecha' => $empresa->trial_ends_at->format('d/m/Y'),
                    'color' => $color,
                    'texto' => $texto,
                ];
            })
            ->all();
    }

    /**
     * Los últimos 6 pagos. El monto va en null si el usuario no puede ver montos.
     *
     * @return array<int, array{empresa:string, codigo:string, tipo:string, fecha:string, monto:?float}>
     */
    private function pagosRecientes(bool $verMontos): array
    {
        return LicenciaPago::with('licencia.empresa')
            ->orderByDesc('fecha_pago')
            ->limit(6)
            ->get()
            ->map(fn($pago) => [
                'empresa' => $pago->licencia?->empresa?->nombre_comercial ?? '—',
                'codigo' => (string) ($pago->licencia?->codigo_licencia ?? '—'),
                // Sin fecha_vencimiento_anterior fue la venta inicial; con ella, una renovación.
                'tipo' => $pago->fecha_vencimiento_anterior ? 'Renovación' : 'Venta / activación',
                'fecha' => $pago->fecha_pago->format('d/m/Y H:i'),
                'monto' => $verMontos ? (float) $pago->monto : null,
            ])
            ->all();
    }

    /**
     * Facturación de los últimos 6 meses (el actual incluido), con el mes sin
     * pagos en 0 para que el gráfico siempre tenga 6 barras. 'altura' es el
     * porcentaje respecto al mes más alto.
     *
     * @return array<int, array{etiqueta:string, total:float, altura:int}>
     */
    private function facturacionSeisMeses(): array
    {
        $totales = LicenciaPago::query()
            ->selectRaw("DATE_FORMAT(fecha_pago, '%Y-%m') as mes, SUM(monto) as total")
            ->where('fecha_pago', '>=', now()->startOfMonth()->subMonths(5))
            ->groupBy('mes')
            ->pluck('total', 'mes');

        $meses = collect(range(5, 0))->map(function (int $atras) use ($totales) {
            $mes = now()->startOfMonth()->subMonths($atras);

            return [
                'etiqueta' => $mes->translatedFormat('M'),
                'total' => (float) ($totales[$mes->format('Y-m')] ?? 0),
            ];
        });

        $maximo = max(1.0, (float) $meses->max('total'));

        return $meses
            ->map(fn(array $m) => $m + [
                'altura' => $m['total'] > 0 ? max(6, (int) round($m['total'] / $maximo * 100)) : 2,
            ])
            ->all();
    }
}
