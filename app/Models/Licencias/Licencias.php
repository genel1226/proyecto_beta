<?php

namespace App\Models\Licencias;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

#[Fillable(
    'empresa_id',
    'plan_id',
    'codigo_licencia',
    'fecha_inicio',
    'fecha_vencimiento',
    'periodicidad',
    'monto',
    'descuento',
    'moneda',
    'estado',
    'observaciones'
)]

class Licencias extends Model
{
    /** @use HasFactory<\Database\Factories\Licencias\LicenciasFactory> */
    use HasFactory;
}
