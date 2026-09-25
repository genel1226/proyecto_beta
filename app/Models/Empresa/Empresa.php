<?php

namespace App\Models\Empresa;

use App\Models\Licencias\Licencias;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Empresa extends Model
{
    protected $table = 'empresas';

    protected $fillable = [
        'cod',
        'razon_social',
        'nombre_comercial',
        'nit',
        'slug',
        'email',
        'telefono',
        'active',
        'pais',
        'direccion',
    ];

    protected $casts = [
        'active' => 'boolean',
    ];

    public function licencias(): HasMany
    {
        return $this->hasMany(Licencias::class, 'empresa_id');
    }

    public function licenciaActiva(): ?Licencias
    {
        return $this->licencias()
            ->whereIn('estado', ['V', 'X', 'P'])
            ->latest('fecha_inicio')
            ->first();
    }

    public function correoAlertas(): ?string
    {
        return $this->email;
    }
}