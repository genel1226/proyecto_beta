<?php

namespace App\Models\Licencias;

use App\Models\Empresa\Empresa;
use App\Models\Plan\Plan;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Licencias extends Model
{
    protected $table = 'licencias';

    protected $fillable = [
        'empresa_id',
        'plan_id',
        'reemplaza_a_licencia_id',
        'codigo_licencia',
        'fecha_inicio',
        'fecha_vencimiento',
        'periodicidad',
        'monto',
        'descuento',
        'moneda',
        'estado',
        'vendedor_id',
        'observaciones',
    ];

    protected $casts = [
        'fecha_inicio' => 'date',
        'fecha_vencimiento' => 'date',
        'monto' => 'decimal:2',
        'descuento' => 'decimal:2',
    ];

    // ---- Relaciones ----

    public function empresa(): BelongsTo
    {
        return $this->belongsTo(Empresa::class, 'empresa_id');
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(Plan::class, 'plan_id');
    }

    public function vendedor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'vendedor_id');
    }

    public function reemplazaA(): BelongsTo
    {
        return $this->belongsTo(self::class, 'reemplaza_a_licencia_id');
    }

    public function detalleUsuarios(): HasMany
    {
        return $this->hasMany(LicenciaDetalleUsuario::class, 'licencia_id');
    }

    public function pagos(): HasMany
    {
        return $this->hasMany(LicenciaPago::class, 'licencia_id');
    }

    // ---- Scopes ----

    /** Vigente, Por vencer o En proceso: cuenta como "activa" para la regla de una sola licencia por empresa. */
    public function scopeActivas($query)
    {
        return $query->whereIn('estado', ['V', 'X', 'P']);
    }

    // ---- Cálculo del monto (el mismo que usa LicenciaForm) ----

    public function recalcularMonto(): float
    {
        $base = $this->plan->monto ?? 0;
        $usuarios = $this->detalleUsuarios->sum(fn (LicenciaDetalleUsuario $d) => $d->cantidad * $d->precio_unitario_aplicado);

        return max($base + $usuarios - $this->descuento, 0);
    }

    public function getDiasRestantesAttribute(): int
    {
        return now()->startOfDay()->diffInDays($this->fecha_vencimiento, false);
    }
}