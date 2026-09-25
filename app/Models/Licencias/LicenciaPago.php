<?php

namespace App\Models\Licencias;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LicenciaPago extends Model
{
    protected $table = 'licencia_pagos';

    public $timestamps = false; // la tabla solo tiene created_at, no updated_at

    protected $fillable = [
        'licencia_id',
        'fecha_pago',
        'monto',
        'fecha_vencimiento_anterior',
        'fecha_vencimiento_nueva',
        'registrado_por',
    ];

    protected $casts = [
        'fecha_pago' => 'datetime',
        'fecha_vencimiento_anterior' => 'date',
        'fecha_vencimiento_nueva' => 'date',
        'monto' => 'decimal:2',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $pago) {
            $pago->created_at ??= now();
        });
    }

    public function licencia(): BelongsTo
    {
        return $this->belongsTo(Licencias::class, 'licencia_id');
    }

    public function registradoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'registrado_por');
    }
}