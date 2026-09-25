<?php

namespace App\Models\TipoUsuario;

use App\Models\Licencias\LicenciaDetalleUsuario;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TipoUsuario extends Model
{
    // El nombre de la tabla no sigue la convención de pluralización de
    // Laravel (sería "tipo_usuarios"), así que se declara explícito.
    protected $table = 'tipos_usuario';

    protected $fillable = [
        'codigo',
        'nombre',
        'precio_unitario',
        'activo',
    ];

    protected $casts = [
        'precio_unitario' => 'decimal:2',
        'activo' => 'boolean',
    ];

    public function scopeActivos($query)
    {
        return $query->where('activo', 1);
    }

    public function detalleUsuarios(): HasMany
    {
        return $this->hasMany(LicenciaDetalleUsuario::class, 'tipo_usuario_id');
    }

    public function getPrecioFormateadoAttribute(): string
    {
        return '$'.number_format((float) $this->precio_unitario, 2);
    }
}