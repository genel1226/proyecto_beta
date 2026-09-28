<?php

namespace App\Console\Commands;

use App\Mail\LicenciaAlertaMail;
use App\Models\Empresa\Empresa;
use App\Models\Licencias\Licencias as ModelsLicencias;
use App\Services\AccesoEmpresa;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;

/**
 * Motor diario de licencias. Se programa DOS veces al día (ver routes/console.php):
 *
 *   licencias:procesar --solo=vencimientos   (00:05)
 *       - Licencia cuya fecha ya pasó  -> Vencida (N), empresa inactiva,
 *         correo de "venció". Sin período de gracia.
 *       - Licencia que entra en los últimos N días -> Por vencer (X).
 *       - (Opcional) demos con la prueba terminada -> empresa inactiva.
 *
 *   licencias:procesar --solo=alertas        (08:00)
 *       - Correo a los 7 y a 1 día del vencimiento (configurable).
 *
 * Como cada aviso se manda cuando la fecha coincide EXACTAMENTE con "hoy + N",
 * no hace falta una tabla que recuerde qué correos ya salieron. La limitación
 * es que si el cron no corre un día, ese aviso se pierde.
 *
 * Una licencia "vence" cuando su fecha_vencimiento es anterior a hoy: el mismo
 * día del vencimiento todavía está vigente.
 */
class ProcesarLicencias extends Command
{
    protected $signature = 'licencias:procesar {--solo= : "alertas" o "vencimientos"; sin opción hace las dos cosas}';

    protected $description = 'Suspende las licencias vencidas y envía las alertas de vencimiento';

    public function handle(): int
    {
        $solo = $this->option('solo');

        if ($solo && ! in_array($solo, ['alertas', 'vencimientos'], true)) {
            $this->error('La opción --solo debe ser "alertas" o "vencimientos".');

            return self::INVALID;
        }

        if (! $solo || $solo === 'vencimientos') {
            $this->procesarVencimientos();
        }

        if (! $solo || $solo === 'alertas') {
            $this->enviarAlertas();
        }

        return self::SUCCESS;
    }

    private function procesarVencimientos(): void
    {
        $hoy = today()->toDateString();

        // 1) Vencidas: la fecha ya pasó -> se suspende la empresa y se avisa.
        $vencidas = ModelsLicencias::with(['empresa', 'plan'])
            ->whereIn('estado', ['V', 'X'])
            ->whereDate('fecha_vencimiento', '<', $hoy)
            ->get();

        foreach ($vencidas as $licencia) {
            DB::transaction(function () use ($licencia) {
                $licencia->update(['estado' => 'N']);
                AccesoEmpresa::suspender($licencia->empresa);
            });

            $this->info("Vencida: {$licencia->codigo_licencia} (empresa suspendida)");
            $this->enviar($licencia, 'vencida');
        }

        // 2) Por vencer: entra en la ventana de aviso (por defecto, 15 días).
        $limite = today()->addDays((int) config('licencias.dias_por_vencer'))->toDateString();

        $marcadas = ModelsLicencias::where('estado', 'V')
            ->whereDate('fecha_vencimiento', '>=', $hoy)
            ->whereDate('fecha_vencimiento', '<=', $limite)
            ->update(['estado' => 'X']);

        $this->info("Marcadas como Por vencer: {$marcadas}");

        // 3) Demos con la prueba terminada (apagado por defecto).
        if (config('licencias.desactivar_demos_vencidas')) {
            $demos = Empresa::where('active', 1)
                ->whereNotNull('trial_ends_at')
                ->where('trial_ends_at', '<', now())
                ->update(['active' => false]);

            $this->info("Demos desactivadas: {$demos}");
        }
    }

    private function enviarAlertas(): void
    {
        foreach (config('licencias.dias_alerta') as $dias) {
            $fecha = today()->addDays((int) $dias)->toDateString();

            $licencias = ModelsLicencias::with(['empresa', 'plan'])
                ->whereIn('estado', ['V', 'X'])
                ->whereDate('fecha_vencimiento', $fecha)
                ->get();

            foreach ($licencias as $licencia) {
                $this->info("Aviso a {$dias} día(s): {$licencia->codigo_licencia}");
                $this->enviar($licencia, 'por_vencer', (int) $dias);
            }
        }
    }

    /**
     * Va al correo de la empresa, con copia oculta al correo interno de
     * Software4tech. Si falla un envío, se registra y se sigue con el resto.
     */
    private function enviar(ModelsLicencias $licencia, string $tipo, int $dias = 0): void
    {
        $cliente = $licencia->empresa?->correoAlertas();
        $interno = config('licencias.correo_interno');

        $principal = $cliente ?: $interno;

        if (! $principal) {
            $this->warn("Sin correo para {$licencia->codigo_licencia}: la empresa no tiene email y no hay LICENCIAS_CORREO_INTERNO.");

            return;
        }

        try {
            $correo = Mail::to($principal);

            if ($cliente && $interno) {
                $correo->bcc($interno);
            }

            $correo->send(new LicenciaAlertaMail($licencia, $tipo, $dias));
        } catch (Throwable $e) {
            Log::error("No se pudo enviar la alerta de {$licencia->codigo_licencia}: {$e->getMessage()}");
            $this->error("No se pudo enviar el correo de {$licencia->codigo_licencia}: {$e->getMessage()}");
        }
    }
}
