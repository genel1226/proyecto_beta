<?php

namespace App\Livewire\Decisiones;

use App\Exports\DecisionesExport;
use App\Models\Licencias\Licencias;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Url;
use Livewire\Component;
use Maatwebsite\Excel\Facades\Excel;

/**
 * Reportes para TOMAR DECISIONES (a diferencia de "Reportes", que solo lista
 * lo facturado). Cada pestaña responde una pregunta concreta del negocio.
 *
 * Todos los reportes devuelven la MISMA estructura (armarReporte), así que una
 * sola vista pinta KPIs + gráfico + tabla, y el mismo arreglo alimenta el
 * Excel y el PDF. Ningún resultado es propiedad pública: se calcula en
 * render() y no viaja al navegador (solo viajan los filtros).
 *
 * Reglas de negocio usadas (también se muestran en pantalla como "nota"):
 *  - Sin reporte de vendedores: hoy todas las licencias las registra una sola persona.
 *  - Ingresos: por licencia_pagos.fecha_pago.
 *  - Renovación: pago con fecha_vencimiento_anterior; venta inicial: sin ella.
 *  - Retenida: renovada por pago, o reemplazada por otra licencia
 *    (reemplaza_a_licencia_id). Perdida: Vencida/Cancelada sin ninguna de las dos.
 */
class Decisiones extends Component
{
    public const TABS = [
        'riesgo' => ['Renovaciones en riesgo', 'exclamation-triangle', false],
        'retencion' => ['Retención', 'arrow-path', false],
        'ingresos' => ['Ingresos por plan', 'banknotes', true],
        'descuentos' => ['Descuentos', 'receipt-percent', true],
        'usuarios' => ['Tipos de usuario', 'users', false],
        'paises' => ['Países', 'globe-americas', false],
    ];

    private const COLORES = ['#3b82f6', '#10b981', '#f59e0b', '#8b5cf6', '#f43f5e', '#14b8a6', '#64748b', '#ec4899'];

    #[Url(as: 'tab')]
    public string $tab = 'riesgo';

    public string $desde = '';

    public string $hasta = '';

    /** Solo para "Renovaciones en riesgo": cuántos días hacia adelante mirar. */
    public int $horizonte = 30;

    public function mount(): void
    {
        Gate::authorize('decisiones.index');

        $this->desde = now()->startOfMonth()->subMonths(5)->format('Y-m-d');
        $this->hasta = now()->format('Y-m-d');
    }

    protected function rules(): array
    {
        return [
            'tab' => ['required', 'in:'.implode(',', array_keys(self::TABS))],
            'desde' => ['required', 'date'],
            'hasta' => ['required', 'date', 'after_or_equal:desde'],
            'horizonte' => ['required', 'in:15,30,60,90'],
        ];
    }

    public function updated($property): void
    {
        $this->validateOnly($property);
    }

    public function cambiarTab(string $tab): void
    {
        if (array_key_exists($tab, $this->tabsPermitidos())) {
            $this->tab = $tab;
        }
    }

    /** Pestañas visibles para este usuario (las de dinero exigen reportes.montos). */
    private function tabsPermitidos(): array
    {
        $montos = Gate::allows('reportes.montos');

        return array_filter(self::TABS, fn ($t) => ! $t[2] || $montos);
    }

    /**
     * Rango efectivo. Si el usuario escribió algo inválido, se usa el valor por
     * defecto para no romper las consultas (el error se muestra en el campo).
     * Máximo 36 meses.
     *
     * @return array{0:CarbonInterface,1:CarbonInterface}
     */
    private function rango(): array
    {
        try {
            $d = Carbon::parse($this->desde)->startOfDay();
            $h = Carbon::parse($this->hasta)->endOfDay();
        } catch (\Throwable) {
            $d = now()->startOfMonth()->subMonths(5)->startOfDay();
            $h = now()->endOfDay();
        }

        if ($h->lt($d)) {
            $h = $d->copy()->endOfDay();
        }

        if ($d->diffInMonths($h) > 36) {
            $d = $h->copy()->subMonths(36)->startOfMonth()->startOfDay();
        }

        return [$d, $h];
    }

    /** @return array<int, string> claves 'Y-m' de cada mes del rango */
    private function meses(CarbonInterface $d, CarbonInterface $h): array
    {
        $meses = [];
        $m = $d->copy()->startOfMonth();

        while ($m->lte($h)) {
            $meses[] = $m->format('Y-m');
            $m = $m->addMonth(); // ojo: con fechas inmutables addMonth() devuelve una copia
        }

        return $meses;
    }

    private function etiquetaMes(string $ym): string
    {
        return Carbon::createFromFormat('Y-m-d', $ym.'-01')->translatedFormat('M Y');
    }

    // =====================================================================
    // Formato (lo usan la vista, el PDF y los KPIs)
    // =====================================================================

    public static function fmt(mixed $valor, string $tipo): string
    {
        if ($valor === null || $valor === '') {
            return '—';
        }

        return match ($tipo) {
            'dinero' => '$'.number_format((float) $valor, 2),
            'pct' => number_format((float) $valor, 1).' %',
            'num' => number_format((float) $valor, 0),
            'badge' => (string) ($valor['texto'] ?? ''),
            default => (string) $valor,
        };
    }

    // =====================================================================
    // Armado general
    // =====================================================================

    /**
     * @return array{
     *   titulo:string, pregunta:string, nota:string,
     *   kpis:array<int, array{etiqueta:string, valor:string, tono:string, icono:string, dinero:bool}>,
     *   grafico:?array, columnas:array<int, array{k:string, etiqueta:string, tipo:string, dinero:bool}>,
     *   filas:array<int, array<string, mixed>>
     * }
     */
    private function armarReporte(): array
    {
        $tabs = $this->tabsPermitidos();
        $tab = array_key_exists($this->tab, $tabs) ? $this->tab : array_key_first($tabs);
        $m = Gate::allows('reportes.montos');
        [$d, $h] = $this->rango();

        $rep = match ($tab) {
            'riesgo' => $this->repRiesgo($m),
            'retencion' => $this->repRetencion($m, $d, $h),
            'ingresos' => $this->repIngresos($d, $h),
            'descuentos' => $this->repDescuentos($d, $h),
            'usuarios' => $this->repUsuarios($m),
            'paises' => $this->repPaises($m, $d, $h),
        };

        // Lo que es dinero se quita por completo si no tiene reportes.montos.
        if (! $m) {
            $rep['kpis'] = array_values(array_filter($rep['kpis'], fn ($k) => ! $k['dinero']));
            $rep['columnas'] = array_values(array_filter($rep['columnas'], fn ($c) => ! $c['dinero']));
        }

        $rep['tab'] = $tab;

        return $rep;
    }

    private function kpi(string $etiqueta, mixed $valor, string $tipo, string $tono, string $icono, bool $dinero = false): array
    {
        return [
            'etiqueta' => $etiqueta,
            'valor' => self::fmt($valor, $tipo),
            'tono' => $tono,
            'icono' => $icono,
            'dinero' => $dinero,
        ];
    }

    private function col(string $k, string $etiqueta, string $tipo = 'texto', bool $dinero = false): array
    {
        return ['k' => $k, 'etiqueta' => $etiqueta, 'tipo' => $tipo, 'dinero' => $dinero];
    }

    private function pct(float|int $parte, float|int $total): ?float
    {
        return $total > 0 ? round($parte / $total * 100, 1) : null;
    }

    private function diasHasta(CarbonInterface $fecha): int
    {
        return (int) now()->startOfDay()->diff($fecha->copy()->startOfDay())->format('%r%a');
    }

    // =====================================================================
    // 1. Renovaciones en riesgo
    // =====================================================================

    private function repRiesgo(bool $m): array
    {
        $hoy = now()->startOfDay();

        $reemplazadas = Licencias::query()
            ->whereNotNull('reemplaza_a_licencia_id')
            ->select('reemplaza_a_licencia_id');

        $licencias = Licencias::with(['empresa:id,nombre_comercial,pais', 'plan:id,nombre'])
            ->withCount(['pagos as renovaciones' => fn ($q) => $q->whereNotNull('fecha_vencimiento_anterior')])
            ->withSum('detalleUsuarios as usuarios', 'cantidad')
            ->where(function ($q) use ($hoy, $reemplazadas) {
                // Vigentes / por vencer que vencen dentro del horizonte...
                $q->where(fn ($w) => $w->whereIn('estado', ['V', 'X'])
                    ->whereDate('fecha_vencimiento', '<=', $hoy->copy()->addDays($this->horizonte)))
                    // ...y vencidas en los últimos 30 días que nadie renovó ni reemplazó.
                    ->orWhere(fn ($w) => $w->where('estado', 'N')
                        ->whereDate('fecha_vencimiento', '>=', $hoy->copy()->subDays(30))
                        ->whereNotIn('id', $reemplazadas));
            })
            ->orderBy('fecha_vencimiento')
            ->get();

        $cubos = ['Vencidas' => 0, '0–7 días' => 0, '8–15 días' => 0, '16–30 días' => 0, '31–60 días' => 0, '61–90 días' => 0];
        $alto = 0;
        $vencidas = 0;
        $monto = 0.0;
        $filas = [];

        foreach ($licencias as $l) {
            $dias = $this->diasHasta($l->fecha_vencimiento);
            $nunca = (int) $l->renovaciones === 0;

            [$nivel, $color] = match (true) {
                $l->estado === 'N' => ['Alto', 'red'],
                $dias <= 7 && $nunca => ['Alto', 'red'],
                $dias <= 15 || $nunca => ['Medio', 'amber'],
                default => ['Bajo', 'green'],
            };

            $cubo = match (true) {
                $dias < 0 => 'Vencidas',
                $dias <= 7 => '0–7 días',
                $dias <= 15 => '8–15 días',
                $dias <= 30 => '16–30 días',
                $dias <= 60 => '31–60 días',
                default => '61–90 días',
            };
            $cubos[$cubo]++;

            $alto += $nivel === 'Alto' ? 1 : 0;
            $vencidas += $l->estado === 'N' ? 1 : 0;
            $monto += (float) $l->monto;

            $filas[] = [
                'empresa' => $l->empresa?->nombre_comercial ?? '—',
                'pais' => $l->empresa?->pais ?: '—',
                'codigo' => (string) $l->codigo_licencia,
                'plan' => $l->plan?->nombre ?? '—',
                'vence' => $l->fecha_vencimiento->format('d/m/Y'),
                'dias' => $dias,
                'usuarios' => (int) $l->usuarios,
                'renovaciones' => (int) $l->renovaciones,
                'monto' => (float) $l->monto,
                'riesgo' => ['texto' => $nivel, 'color' => $color],
            ];
        }

        // Más urgentes primero: Alto, Medio, Bajo y, dentro de cada nivel, por días.
        $orden = ['Alto' => 0, 'Medio' => 1, 'Bajo' => 2];
        usort($filas, fn ($a, $b) => [$orden[$a['riesgo']['texto']], $a['dias']] <=> [$orden[$b['riesgo']['texto']], $b['dias']]);

        return [
            'titulo' => 'Renovaciones en riesgo',
            'pregunta' => '¿A quién tengo que llamar hoy para no perder la renovación?',
            'nota' => 'Riesgo ALTO: ya venció, o vence en 7 días o menos y nunca renovó. MEDIO: vence en 15 días o menos, o nunca renovó. BAJO: el resto. Se incluyen vencidas de los últimos 30 días que nadie renovó ni reemplazó.',
            'kpis' => [
                $this->kpi('Riesgo alto', $alto, 'num', 'ambar', 'exclamation-triangle'),
                $this->kpi("Vencen en {$this->horizonte} días", count($filas) - $vencidas, 'num', 'azul', 'clock'),
                $this->kpi('Vencidas sin renovar', $vencidas, 'num', 'violeta', 'x-circle'),
                $this->kpi('Monto en juego', $monto, 'dinero', 'verde', 'banknotes', true),
            ],
            'grafico' => [
                'tipo' => 'bar',
                'titulo' => 'Licencias por tiempo restante',
                'etiquetas' => array_keys($cubos),
                'series' => [['nombre' => 'Licencias', 'valores' => array_values($cubos), 'color' => ['#f43f5e', '#f59e0b', '#f59e0b', '#3b82f6', '#3b82f6', '#10b981']]],
                'formato' => 'num',
                'apilado' => false,
            ],
            'columnas' => [
                $this->col('empresa', 'Empresa'),
                $this->col('pais', 'País'),
                $this->col('codigo', 'Licencia'),
                $this->col('plan', 'Plan'),
                $this->col('vence', 'Vence'),
                $this->col('dias', 'Días', 'num'),
                $this->col('usuarios', 'Usuarios', 'num'),
                $this->col('renovaciones', 'Renov. previas', 'num'),
                $this->col('monto', 'Monto', 'dinero', true),
                $this->col('riesgo', 'Riesgo', 'badge'),
            ],
            'filas' => $filas,
        ];
    }

    // =====================================================================
    // 2. Retención y renovaciones
    // =====================================================================

    private function repRetencion(bool $m, CarbonInterface $d, CarbonInterface $h): array
    {
        $ini = $d->format('Y-m-d');
        $fin = $h->format('Y-m-d');

        // Renovadas: pagos cuyo vencimiento anterior cayó en el rango (la fecha en que "tocaba" renovar).
        $renovadas = DB::table('licencia_pagos')
            ->whereNotNull('fecha_vencimiento_anterior')
            ->whereBetween('fecha_vencimiento_anterior', [$ini, $fin])
            ->selectRaw("DATE_FORMAT(fecha_vencimiento_anterior, '%Y-%m') as mes, COUNT(*) as n")
            ->groupBy('mes')->pluck('n', 'mes');

        // Reemplazadas: la licencia vieja se cambió por otra (cambio de plan, por ejemplo).
        $reemplazadas = DB::table('licencias as nueva')
            ->join('licencias as vieja', 'vieja.id', '=', 'nueva.reemplaza_a_licencia_id')
            ->whereBetween('vieja.fecha_vencimiento', [$ini, $fin])
            ->selectRaw("DATE_FORMAT(vieja.fecha_vencimiento, '%Y-%m') as mes, COUNT(*) as n")
            ->groupBy('mes')->pluck('n', 'mes');

        // Perdidas: Vencida o Cancelada, sin reemplazo.
        $perdidas = DB::table('licencias')
            ->whereIn('estado', ['N', 'C'])
            ->whereBetween('fecha_vencimiento', [$ini, $fin])
            ->whereNotIn('id', fn ($q) => $q->from('licencias')->whereNotNull('reemplaza_a_licencia_id')->select('reemplaza_a_licencia_id'))
            ->selectRaw("DATE_FORMAT(fecha_vencimiento, '%Y-%m') as mes, COUNT(*) as n")
            ->groupBy('mes')->pluck('n', 'mes');

        $ingresoRenov = (float) DB::table('licencia_pagos')
            ->whereNotNull('fecha_vencimiento_anterior')
            ->whereBetween('fecha_pago', [$d->format('Y-m-d H:i:s'), $h->format('Y-m-d H:i:s')])
            ->sum('monto');

        $filas = [];
        $etq = [];
        $serieOk = [];
        $seriePerd = [];
        $tRen = $tRep = $tPer = 0;

        foreach ($this->meses($d, $h) as $ym) {
            $r = (int) ($renovadas[$ym] ?? 0);
            $e = (int) ($reemplazadas[$ym] ?? 0);
            $p = (int) ($perdidas[$ym] ?? 0);
            $tot = $r + $e + $p;

            $tRen += $r;
            $tRep += $e;
            $tPer += $p;

            $etq[] = $this->etiquetaMes($ym);
            $serieOk[] = $r + $e;
            $seriePerd[] = $p;

            $filas[] = [
                'mes' => $this->etiquetaMes($ym),
                'resueltas' => $tot,
                'renovadas' => $r,
                'reemplazadas' => $e,
                'perdidas' => $p,
                'retencion' => $this->pct($r + $e, $tot),
            ];
        }

        $total = $tRen + $tRep + $tPer;

        return [
            'titulo' => 'Retención y renovaciones',
            'pregunta' => '¿Estoy conservando a mis clientes o se me están yendo?',
            'nota' => 'Cada mes cuenta las licencias cuyo vencimiento cayó en ese mes. Retenida = renovada con pago o reemplazada por otra licencia. Perdida = Vencida o Cancelada sin renovar ni reemplazar. Retención = retenidas ÷ (retenidas + perdidas).',
            'kpis' => [
                $this->kpi('Retención del periodo', $this->pct($tRen + $tRep, $total), 'pct', 'verde', 'arrow-path'),
                $this->kpi('Renovadas', $tRen, 'num', 'azul', 'check-circle'),
                $this->kpi('Reemplazadas', $tRep, 'num', 'turquesa', 'arrows-right-left'),
                $this->kpi('Perdidas', $tPer, 'num', 'ambar', 'x-circle'),
                $this->kpi('Cobrado por renovaciones', $ingresoRenov, 'dinero', 'violeta', 'banknotes', true),
            ],
            'grafico' => [
                'tipo' => 'bar',
                'titulo' => 'Retenidas vs perdidas por mes',
                'etiquetas' => $etq,
                'series' => [
                    ['nombre' => 'Retenidas', 'valores' => $serieOk, 'color' => '#10b981'],
                    ['nombre' => 'Perdidas', 'valores' => $seriePerd, 'color' => '#f43f5e'],
                ],
                'formato' => 'num',
                'apilado' => true,
            ],
            'columnas' => [
                $this->col('mes', 'Mes'),
                $this->col('resueltas', 'Vencían', 'num'),
                $this->col('renovadas', 'Renovadas', 'num'),
                $this->col('reemplazadas', 'Reemplazadas', 'num'),
                $this->col('perdidas', 'Perdidas', 'num'),
                $this->col('retencion', 'Retención', 'pct'),
            ],
            'filas' => $filas,
        ];
    }

    // =====================================================================
    // 3. Ingresos por plan y tendencia (solo con reportes.montos)
    // =====================================================================

    private function repIngresos(CarbonInterface $d, CarbonInterface $h): array
    {
        $desde = $d->format('Y-m-d H:i:s');
        $hasta = $h->format('Y-m-d H:i:s');

        $datos = DB::table('licencia_pagos as pg')
            ->join('licencias as l', 'l.id', '=', 'pg.licencia_id')
            ->join('plans as pl', 'pl.id', '=', 'l.plan_id')
            ->whereBetween('pg.fecha_pago', [$desde, $hasta])
            ->selectRaw("DATE_FORMAT(pg.fecha_pago, '%Y-%m') as mes, pl.nombre as plan, SUM(pg.monto) as total, COUNT(*) as pagos,
                SUM(pg.fecha_vencimiento_anterior IS NULL) as nuevos,
                SUM(pg.fecha_vencimiento_anterior IS NOT NULL) as renov,
                SUM(CASE WHEN pg.fecha_vencimiento_anterior IS NOT NULL THEN pg.monto ELSE 0 END) as total_renov")
            ->groupBy('mes', 'pl.id', 'pl.nombre')
            ->get();

        $porPlan = [];
        $matriz = [];
        $totalGeneral = 0.0;
        $totalRenov = 0.0;
        $pagosGeneral = 0;

        foreach ($datos as $f) {
            $p = &$porPlan[$f->plan];
            $p ??= ['plan' => $f->plan, 'total' => 0.0, 'pagos' => 0, 'nuevos' => 0, 'renov' => 0];
            $p['total'] += (float) $f->total;
            $p['pagos'] += (int) $f->pagos;
            $p['nuevos'] += (int) $f->nuevos;
            $p['renov'] += (int) $f->renov;
            unset($p);

            $matriz[$f->plan][$f->mes] = (float) $f->total;
            $totalGeneral += (float) $f->total;
            $totalRenov += (float) $f->total_renov;
            $pagosGeneral += (int) $f->pagos;
        }

        uasort($porPlan, fn ($a, $b) => $b['total'] <=> $a['total']);

        // Periodo anterior de la misma duración, para saber si se crece o se cae.
        $dias = (int) $d->copy()->startOfDay()->diffInDays($h->copy()->startOfDay()) + 1;
        $antes = (float) DB::table('licencia_pagos')
            ->whereBetween('fecha_pago', [
                $d->copy()->subDays($dias)->format('Y-m-d H:i:s'),
                $d->copy()->subSecond()->format('Y-m-d H:i:s'),
            ])->sum('monto');
        $variacion = $antes > 0 ? round(($totalGeneral - $antes) / $antes * 100, 1) : null;

        $meses = $this->meses($d, $h);
        $series = [];
        $i = 0;
        foreach (array_keys($porPlan) as $plan) {
            $series[] = [
                'nombre' => $plan,
                'valores' => array_map(fn ($ym) => round($matriz[$plan][$ym] ?? 0, 2), $meses),
                'color' => self::COLORES[$i++ % count(self::COLORES)],
            ];
        }

        $filas = array_values(array_map(fn ($p) => [
            'plan' => $p['plan'],
            'pagos' => $p['pagos'],
            'nuevos' => $p['nuevos'],
            'renov' => $p['renov'],
            'total' => $p['total'],
            'participacion' => $this->pct($p['total'], $totalGeneral),
            'ticket' => $p['pagos'] > 0 ? round($p['total'] / $p['pagos'], 2) : null,
        ], $porPlan));

        return [
            'titulo' => 'Ingresos por plan y tendencia',
            'pregunta' => '¿Qué plan me da más dinero y estoy creciendo?',
            'nota' => 'Dinero cobrado según la fecha de pago. "Nuevos" son ventas iniciales o activaciones; "Renov." son renovaciones. La variación compara contra el periodo inmediatamente anterior de la misma duración.',
            'kpis' => [
                $this->kpi('Ingresos del periodo', $totalGeneral, 'dinero', 'verde', 'banknotes', true),
                $this->kpi('Vs periodo anterior', $variacion, 'pct', $variacion !== null && $variacion < 0 ? 'ambar' : 'azul', 'arrow-trending-up', true),
                $this->kpi('Ticket promedio', $pagosGeneral > 0 ? $totalGeneral / $pagosGeneral : null, 'dinero', 'violeta', 'receipt-percent', true),
                $this->kpi('Ingreso por renovaciones', $this->pct($totalRenov, $totalGeneral), 'pct', 'turquesa', 'arrow-path', true),
            ],
            'grafico' => [
                'tipo' => 'bar',
                'titulo' => 'Ingresos por mes y plan',
                'etiquetas' => array_map(fn ($ym) => $this->etiquetaMes($ym), $meses),
                'series' => $series,
                'formato' => 'dinero',
                'apilado' => true,
            ],
            'columnas' => [
                $this->col('plan', 'Plan'),
                $this->col('pagos', 'Pagos', 'num'),
                $this->col('nuevos', 'Nuevos', 'num'),
                $this->col('renov', 'Renov.', 'num'),
                $this->col('total', 'Total', 'dinero', true),
                $this->col('participacion', '% del total', 'pct', true),
                $this->col('ticket', 'Ticket prom.', 'dinero', true),
            ],
            'filas' => $filas,
        ];
    }

    // =====================================================================
    // 4. Efecto de los descuentos (solo con reportes.montos)
    // =====================================================================

    private function repDescuentos(CarbonInterface $d, CarbonInterface $h): array
    {
        $licencias = Licencias::query()
            ->withCount(['pagos as renovaciones' => fn ($q) => $q->whereNotNull('fecha_vencimiento_anterior')])
            ->whereBetween('fecha_inicio', [$d->format('Y-m-d'), $h->format('Y-m-d')])
            ->get(['id', 'monto', 'descuento', 'estado']);

        $tramos = [
            'Sin descuento' => [],
            '1 – 10 %' => [],
            '11 – 20 %' => [],
            'Más de 20 %' => [],
        ];

        foreach ($licencias as $l) {
            $bruto = (float) $l->monto + (float) $l->descuento;
            $p = $bruto > 0 ? (float) $l->descuento / $bruto * 100 : 0;

            $t = match (true) {
                (float) $l->descuento <= 0 => 'Sin descuento',
                $p <= 10 => '1 – 10 %',
                $p <= 20 => '11 – 20 %',
                default => 'Más de 20 %',
            };
            $tramos[$t][] = $l;
        }

        $filas = [];
        $tasas = [];
        $descTotal = 0.0;
        $conDesc = 0;
        $brutoConDesc = 0.0;
        $resumen = ['sin' => [0, 0], 'con' => [0, 0]]; // [ciclos cumplidos, renovaron]

        foreach ($tramos as $nombre => $lista) {
            $c = collect($lista);
            $desc = (float) $c->sum('descuento');
            $neto = (float) $c->sum('monto');
            // "Cumplió un ciclo" = ya renovó, o ya venció/cancelaron. Las demás todavía no tuvieron oportunidad de renovar.
            $ciclos = $c->filter(fn ($l) => $l->renovaciones > 0 || in_array($l->estado, ['N', 'C']))->count();
            $renov = $c->filter(fn ($l) => $l->renovaciones > 0)->count();
            $vigentes = $c->filter(fn ($l) => in_array($l->estado, ['V', 'X', 'P']))->count();
            $tasa = $this->pct($renov, $ciclos);

            $k = $nombre === 'Sin descuento' ? 'sin' : 'con';
            $resumen[$k][0] += $ciclos;
            $resumen[$k][1] += $renov;

            if ($k === 'con') {
                $descTotal += $desc;
                $conDesc += $c->count();
                $brutoConDesc += $neto + $desc;
            }

            $tasas[] = $tasa ?? 0;
            $filas[] = [
                'tramo' => $nombre,
                'licencias' => $c->count(),
                'descuento' => $desc,
                'neto' => $neto,
                'ciclos' => $ciclos,
                'renovaron' => $renov,
                'tasa' => $tasa,
                'vigentes' => $this->pct($vigentes, $c->count()),
            ];
        }

        $tasaSin = $this->pct($resumen['sin'][1], $resumen['sin'][0]);
        $tasaCon = $this->pct($resumen['con'][1], $resumen['con'][0]);
        $dif = ($tasaSin !== null && $tasaCon !== null) ? round($tasaCon - $tasaSin, 1) : null;

        return [
            'titulo' => 'Efecto de los descuentos',
            'pregunta' => '¿Los descuentos me traen clientes que se quedan o solo regalan margen?',
            'nota' => 'Licencias cuyo inicio cae en el rango, agrupadas por el % de descuento sobre el precio sin descuento. Tasa de renovación = licencias que renovaron ÷ licencias que ya cumplieron un ciclo (renovaron, vencieron o se cancelaron). Con pocas licencias por tramo, tomar la tasa como orientación, no como verdad.',
            'kpis' => [
                $this->kpi('Descuento otorgado', $descTotal, 'dinero', 'ambar', 'receipt-percent', true),
                $this->kpi('Licencias con descuento', $this->pct($conDesc, $licencias->count()), 'pct', 'azul', 'tag', true),
                $this->kpi('Descuento promedio', $this->pct($descTotal, $brutoConDesc), 'pct', 'violeta', 'receipt-percent', true),
                $this->kpi('Renovación con vs sin desc.', $dif === null ? null : (($dif > 0 ? '+' : '').number_format($dif, 1).' pts'), 'texto', $dif !== null && $dif < 0 ? 'ambar' : 'verde', 'scale', true),
            ],
            'grafico' => [
                'tipo' => 'bar',
                'titulo' => 'Tasa de renovación por tramo de descuento',
                'etiquetas' => array_keys($tramos),
                'series' => [['nombre' => 'Tasa de renovación', 'valores' => $tasas, 'color' => ['#10b981', '#3b82f6', '#f59e0b', '#f43f5e']]],
                'formato' => 'pct',
                'apilado' => false,
            ],
            'columnas' => [
                $this->col('tramo', 'Descuento'),
                $this->col('licencias', 'Licencias', 'num'),
                $this->col('descuento', 'Descuento total', 'dinero', true),
                $this->col('neto', 'Monto vendido', 'dinero', true),
                $this->col('ciclos', 'Ciclos cumplidos', 'num'),
                $this->col('renovaron', 'Renovaron', 'num'),
                $this->col('tasa', 'Tasa renovación', 'pct'),
                $this->col('vigentes', 'Vigentes hoy', 'pct'),
            ],
            'filas' => $filas,
        ];
    }

    // =====================================================================
    // 5. Composición de usuarios por tipo (foto de las licencias activas)
    // =====================================================================

    private function repUsuarios(bool $m): array
    {
        $tipos = DB::table('licencia_detalle_usuarios as dd')
            ->join('licencias as l', 'l.id', '=', 'dd.licencia_id')
            ->join('tipos_usuario as t', 't.id', '=', 'dd.tipo_usuario_id')
            ->whereIn('l.estado', ['V', 'X'])
            ->selectRaw('t.codigo, t.nombre, SUM(dd.cantidad) as usuarios, COUNT(DISTINCT dd.licencia_id) as licencias,
                SUM(dd.cantidad * dd.precio_unitario_aplicado) as aporte')
            ->groupBy('t.id', 't.codigo', 't.nombre')
            ->orderByDesc('usuarios')
            ->get();

        $activas = Licencias::whereIn('estado', ['V', 'X'])->count();
        $baseTotal = (float) DB::table('licencias as l')
            ->join('plans as p', 'p.id', '=', 'l.plan_id')
            ->whereIn('l.estado', ['V', 'X'])
            ->sum('p.monto');

        $totUsuarios = (int) $tipos->sum('usuarios');
        $totAporte = (float) $tipos->sum('aporte');

        $filas = $tipos->map(fn ($t) => [
            'tipo' => $t->codigo.' · '.$t->nombre,
            'usuarios' => (int) $t->usuarios,
            'porcentaje' => $this->pct((int) $t->usuarios, $totUsuarios),
            'licencias' => (int) $t->licencias,
            'promedio' => $t->licencias > 0 ? round($t->usuarios / $t->licencias, 1) : null,
            'aporte' => (float) $t->aporte,
            'aporte_pct' => $this->pct((float) $t->aporte, $totAporte),
        ])->all();

        $mayor = $tipos->sortByDesc('aporte')->first();

        return [
            'titulo' => 'Composición de usuarios por tipo',
            'pregunta' => '¿Qué tipo de usuario sostiene mi ingreso y dónde conviene ajustar precios?',
            'nota' => 'Foto de hoy de las licencias Vigentes y Por vencer. "Aporte" = cantidad × precio aplicado en cada licencia (por ciclo, sin descontar descuentos). La base del plan se suma aparte.',
            'kpis' => [
                $this->kpi('Licencias activas', $activas, 'num', 'azul', 'clipboard-document-list'),
                $this->kpi('Usuarios en total', $totUsuarios, 'num', 'violeta', 'users'),
                $this->kpi('Usuarios por licencia', $activas > 0 ? round($totUsuarios / $activas, 1) : null, 'texto', 'turquesa', 'user-plus'),
                $this->kpi('Valor que viene de usuarios', $this->pct($totAporte, $totAporte + $baseTotal), 'pct', 'verde', 'banknotes', true),
                $this->kpi('Tipo que más aporta', $mayor ? $mayor->codigo : null, 'texto', 'ambar', 'trophy', true),
            ],
            'grafico' => [
                'tipo' => 'doughnut',
                'titulo' => 'Usuarios por tipo',
                'etiquetas' => $tipos->map(fn ($t) => $t->codigo.' · '.$t->nombre)->all(),
                'series' => [['nombre' => 'Usuarios', 'valores' => $tipos->map(fn ($t) => (int) $t->usuarios)->all(), 'color' => array_slice(self::COLORES, 0, max(1, $tipos->count()))]],
                'formato' => 'num',
                'apilado' => false,
            ],
            'columnas' => [
                $this->col('tipo', 'Tipo de usuario'),
                $this->col('usuarios', 'Usuarios', 'num'),
                $this->col('porcentaje', '% de usuarios', 'pct'),
                $this->col('licencias', 'Licencias que lo usan', 'num'),
                $this->col('promedio', 'Prom. por licencia', 'texto'),
                $this->col('aporte', 'Aporte por ciclo', 'dinero', true),
                $this->col('aporte_pct', '% del aporte', 'pct', true),
            ],
            'filas' => $filas,
        ];
    }

    // =====================================================================
    // 6. Clientes por país
    // =====================================================================

    private function repPaises(bool $m, CarbonInterface $d, CarbonInterface $h): array
    {
        $pais = "COALESCE(NULLIF(TRIM(e.pais), ''), 'Sin país')";

        $empresas = DB::table('empresas as e')
            ->selectRaw("{$pais} as pais, COUNT(*) as empresas,
                SUM(EXISTS (SELECT 1 FROM licencias l WHERE l.empresa_id = e.id AND l.estado IN ('V','X'))) as activas,
                SUM(e.trial_ends_at IS NOT NULL AND e.trial_ends_at >= NOW()) as demos")
            ->groupByRaw($pais)
            ->get();

        $ingresos = DB::table('licencia_pagos as pg')
            ->join('licencias as l', 'l.id', '=', 'pg.licencia_id')
            ->join('empresas as e', 'e.id', '=', 'l.empresa_id')
            ->whereBetween('pg.fecha_pago', [$d->format('Y-m-d H:i:s'), $h->format('Y-m-d H:i:s')])
            ->selectRaw("{$pais} as pais, SUM(pg.monto) as total")
            ->groupByRaw($pais)
            ->pluck('total', 'pais');

        $totalIng = (float) $ingresos->sum();

        $filas = $empresas->map(fn ($e) => [
            'pais' => $e->pais,
            'empresas' => (int) $e->empresas,
            'activas' => (int) $e->activas,
            'sin' => (int) $e->empresas - (int) $e->activas - (int) $e->demos,
            'demos' => (int) $e->demos,
            'ingresos' => (float) ($ingresos[$e->pais] ?? 0),
            'participacion' => $this->pct((float) ($ingresos[$e->pais] ?? 0), $totalIng),
        ])->sortByDesc(fn ($f) => [$f['ingresos'], $f['empresas']])->values()->all();

        $totEmp = array_sum(array_column($filas, 'empresas'));
        $totAct = array_sum(array_column($filas, 'activas'));
        $totDemo = array_sum(array_column($filas, 'demos'));

        return [
            'titulo' => 'Clientes por país',
            'pregunta' => '¿Dónde está mi mercado y dónde tengo empresas sin convertir?',
            'nota' => 'Empresas con licencia activa = Vigente o Por vencer hoy. "Sin licencia" son empresas registradas que no tienen licencia activa ni demo en curso (oportunidad comercial). Los ingresos son los pagos del rango.',
            'kpis' => [
                $this->kpi('Países', count($filas), 'num', 'turquesa', 'globe-americas'),
                $this->kpi('Empresas registradas', $totEmp, 'num', 'azul', 'building-office'),
                $this->kpi('Con licencia activa', $totAct, 'num', 'verde', 'check-circle'),
                $this->kpi('Demos en curso', $totDemo, 'num', 'violeta', 'beaker'),
                $this->kpi('Ingresos del rango', $totalIng, 'dinero', 'ambar', 'banknotes', true),
            ],
            'grafico' => [
                'tipo' => 'doughnut',
                'titulo' => $m ? 'Ingresos por país' : 'Empresas por país',
                'etiquetas' => array_column($filas, 'pais'),
                'series' => [[
                    'nombre' => $m ? 'Ingresos' : 'Empresas',
                    'valores' => array_column($filas, $m ? 'ingresos' : 'empresas'),
                    'color' => array_slice(self::COLORES, 0, max(1, count($filas))),
                ]],
                'formato' => $m ? 'dinero' : 'num',
                'apilado' => false,
            ],
            'columnas' => [
                $this->col('pais', 'País'),
                $this->col('empresas', 'Empresas', 'num'),
                $this->col('activas', 'Con licencia activa', 'num'),
                $this->col('sin', 'Sin licencia', 'num'),
                $this->col('demos', 'Demos en curso', 'num'),
                $this->col('ingresos', 'Ingresos', 'dinero', true),
                $this->col('participacion', '% de ingresos', 'pct', true),
            ],
            'filas' => $filas,
        ];
    }

    // =====================================================================
    // Exportar la pestaña actual
    // =====================================================================

    /** @return array{0: array<int,string>, 1: array<int, array<int, mixed>>} */
    private function tablaPlana(array $rep): array
    {
        $headings = array_column($rep['columnas'], 'etiqueta');
        $rows = [];

        foreach ($rep['filas'] as $fila) {
            $rows[] = array_map(function ($c) use ($fila) {
                $v = $fila[$c['k']] ?? null;

                return $c['tipo'] === 'badge' ? ($v['texto'] ?? '') : $v;
            }, $rep['columnas']);
        }

        return [$headings, $rows];
    }

    private function nombreArchivo(array $rep, string $ext): string
    {
        return 'decisiones-'.$rep['tab'].'-'.now()->format('Ymd-His').'.'.$ext;
    }

    public function exportarExcel()
    {
        if (! Gate::allows('reportes.export')) {
            return;
        }

        $rep = $this->armarReporte();
        [$headings, $rows] = $this->tablaPlana($rep);

        return Excel::download(new DecisionesExport($rep['titulo'], $headings, $rows), $this->nombreArchivo($rep, 'xlsx'));
    }

    public function exportarPdf()
    {
        if (! Gate::allows('reportes.export')) {
            return;
        }

        $rep = $this->armarReporte();
        [$d, $h] = $this->rango();

        $pdf = Pdf::loadView('pdf.decisiones', [
            'rep' => $rep,
            'desde' => $d->format('Y-m-d'),
            'hasta' => $h->format('Y-m-d'),
            'usaRango' => in_array($rep['tab'], ['retencion', 'ingresos', 'descuentos', 'paises'], true),
            'horizonte' => $this->horizonte,
        ])->setPaper('a4', count($rep['columnas']) > 7 ? 'landscape' : 'portrait');

        $nombre = $this->nombreArchivo($rep, 'pdf');

        // Mismo motivo que en Reportes: guardar y devolver response()->download()
        // para que Livewire lo trate como archivo y no como JSON.
        $carpeta = storage_path('app/temp');

        if (! is_dir($carpeta)) {
            mkdir($carpeta, recursive: true);
        }

        $ruta = $carpeta.'/'.$nombre;
        $pdf->save($ruta);

        return response()->download($ruta, $nombre)->deleteFileAfterSend();
    }

    public function render()
    {
        $tabs = $this->tabsPermitidos();

        return view('livewire.decisiones.decisiones', [
            'tabs' => $tabs,
            'rep' => $this->armarReporte(),
            'usaRango' => ! in_array($this->tab, ['riesgo', 'usuarios'], true),
        ]);
    }
}