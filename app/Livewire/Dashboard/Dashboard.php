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
 * módulo que muestra (licenses.index, empresas.index). Todo lo
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
        $verMontos = Gate::allows('reportes.montos');

        $estados = $verLicencias ? $this->conteoPorEstado() : [];
        $vencimientos = $verLicencias ? $this->proximosVencimientos() : [];
        $demos = $verEmpresas ? $this->demos() : [];

        return view('livewire.dashboard.dashboard', [
            'verLicencias' => $verLicencias,
            'verEmpresas' => $verEmpresas,
            'verMontos' => $verMontos,
            'hayAlgo' => $verLicencias || $verEmpresas || $verMontos,

            'estados' => $estados,
            'porVencer' => $verLicencias ? $this->licenciasPorVencer() : 0,
            'vencimientos' => $vencimientos,
            // Escala de la línea de tiempo: al menos 7 días, o hasta el vencimiento más lejano mostrado.
            'spanVencimientos' => max(7, (int) collect($vencimientos)->max('dias')),

            'empresasActivas' => $verEmpresas ? Empresa::where('active', 1)->count() : 0,
            'demos' => $demos,
            // Las pruebas suelen durar 15 días; si alguna dura más, la escala se ajusta.
            'spanDemos' => max(15, (int) collect($demos)->max('dias')),
            'demosEnCurso' => $verEmpresas
                ? Empresa::whereNotNull('trial_ends_at')->where('trial_ends_at', '>=', now())->count()
                : 0,

            'facturadoMes' => $verMontos
                ? (float) LicenciaPago::where('fecha_pago', '>=', now()->startOfMonth())->sum('monto')
                : 0.0,
            'facturacionMeses' => $verMontos ? $this->facturacionSeisMeses() : [],
            'topEmpresas' => $verMontos ? $this->topEmpresas() : [],
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
     * @return array<int, array{dias:int, empresa:string, codigo:string, plan:string, fecha:string, color:string, texto:string}>
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
                    'dias' => $dias,
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
     * @return array<int, array{dias:int, cod:string, nombre:string, fecha:string, color:string, texto:string}>
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
                    'dias' => $dias,
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
     * Las 5 empresas que más ingresos han generado (suma histórica de todos sus
     * pagos, de todas sus licencias). 'porcentaje' es su parte del total facturado
     * y 'ancho' el largo de la barra respecto a la primera. Solo se pide con
     * reportes.montos.
     *
     * @return array<int, array{empresa:string, total:float, porcentaje:int, ancho:int}>
     */
    private function topEmpresas(): array
    {
        $porLicencia = LicenciaPago::query()
            ->selectRaw('licencia_id, SUM(monto) as total')
            ->groupBy('licencia_id')
            ->pluck('total', 'licencia_id');

        if ($porLicencia->isEmpty()) {
            return [];
        }

        $licencias = ModelsLicencias::with('empresa')
            ->whereKey($porLicencia->keys()->all())
            ->get()
            ->keyBy(fn($licencia) => $licencia->getKey());

        // Varias licencias pueden ser de la misma empresa: se suman por empresa.
        $porEmpresa = $porLicencia
            ->groupBy(fn($total, $licenciaId) => $licencias[$licenciaId]?->empresa_id ?? 0, preserveKeys: true)
            ->map(function ($grupo, $empresaId) use ($licencias) {
                $primera = $licencias[$grupo->keys()->first()] ?? null;

                return [
                    'empresa' => $primera?->empresa?->nombre_comercial ?? '—',
                    'total' => (float) $grupo->sum(),
                ];
            })
            ->sortByDesc('total')
            ->values();

        $granTotal = max(1.0, (float) $porEmpresa->sum('total'));
        $maximo = max(1.0, (float) $porEmpresa->max('total'));

        return $porEmpresa
            ->take(5)
            ->map(fn(array $e) => $e + [
                'porcentaje' => (int) round($e['total'] / $granTotal * 100),
                'ancho' => max(3, (int) round($e['total'] / $maximo * 100)),
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
