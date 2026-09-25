<?php

namespace App\Models\Plan;

use App\Models\Licencias\Licencias;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Plan extends Model
{
    protected $table = 'plans';

    protected $fillable = [
        'nombre',
        'sigla',
        'monto',
        'lapso',
        'tipo',
        'cantidad_min',
    ];

    protected $casts = [
        'monto' => 'decimal:2',
        'cantidad_min' => 'integer',
    ];

    public function scopePrimarios($query)
    {
        return $query->where('tipo', '1');
    }

    public function licencias(): HasMany
    {
        return $this->hasMany(Licencias::class, 'plan_id');
    }

    public function getMontoFormateadoAttribute(): string
    {
        return '$'.number_format((float) $this->monto, 2);
    }
}