<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Modelo liviano sobre la tabla `permissions` (la misma que ya usa
 * PermisosSeeder). Solo para leer y navegar el catálogo — crear o
 * editar permisos sigue siendo trabajo del seeder, no de esta pantalla.
 */
class Permission extends Model
{
    protected $table = 'permissions';

    protected $fillable = ['parent_id', 'name', 'guard_name', 'description', 'description_en', 'active', 'type'];

    protected $casts = [
        'active' => 'boolean',
    ];

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id')->orderBy('id');
    }

    /**
     * Los "módulos": las filas título (type = '0', sin padre) que agrupan
     * al resto. Son las que se ven como encabezado en la pantalla de
     * switches (ej. "GESTION DE LICENCIAS").
     */
    public function scopeModulos($query)
    {
        return $query->whereNull('parent_id')
            ->where('type', '0')
            ->where('active', true)
            ->orderBy('id');
    }
}