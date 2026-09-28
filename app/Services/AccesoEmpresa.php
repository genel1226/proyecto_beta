<?php

namespace App\Services;

use App\Models\Empresa\Empresa;
use App\Models\Licencias\Licencias;

/**
 * Decide si una empresa puede entrar al CMMS.
 *
 * `empresas.active` controla el acceso al sistema real, así que TODO cambio
 * de estado de una licencia que afecte el acceso pasa por aquí, para que la
 * regla viva en un solo lugar:
 *
 *   - otorgar():   licencia nueva Vigente, renovada, activada o reactivada.
 *   - suspender(): licencia vencida o dada de baja.
 *
 * También deja al día los campos que ya usaba el sistema original
 * (con_licencia, next_payment_date, last_payment_amount), por si algún
 * reporte del CMMS los lee.
 */
class AccesoEmpresa
{
    /**
     * La empresa tiene una licencia vigente: puede entrar.
     * Si era una cuenta demo, deja de serlo.
     */
    public static function otorgar(Licencias $licencia): void
    {
        $empresa = $licencia->empresa;

        if (! $empresa) {
            return;
        }

        $ultimoPago = $licencia->pagos()->latest('fecha_pago')->value('monto');

        $empresa->forceFill([
            'active' => true,
            'con_licencia' => '1',
            'trial_ends_at' => null,
            'next_payment_date' => $licencia->fecha_vencimiento->toDateString(),
            'last_payment_amount' => $ultimoPago ?? $licencia->monto,
        ])->save();
    }

    /**
     * Se acabó la licencia: la empresa deja de poder entrar.
     *
     * Por seguridad, si la empresa todavía tiene otra licencia Vigente o
     * Por vencer, no se toca.
     */
    public static function suspender(?Empresa $empresa): void
    {
        if (! $empresa) {
            return;
        }

        $sigueVigente = $empresa->licencias()->whereIn('estado', ['V', 'X'])->exists();

        if ($sigueVigente) {
            return;
        }

        $empresa->forceFill([
            'active' => false,
            'con_licencia' => '0',
        ])->save();
    }
}
