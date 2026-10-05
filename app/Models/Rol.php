<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Rol de este sistema (tabla `roles`, la misma que usa el CMMS).
 *
 * Los roles del personal de Software4tech llevan empresa_id = 0. Así esta
 * aplicación solo ve y toca los suyos, y los roles de las empresas clientes
 * (empresa_id distinto de 0) quedan fuera de las pantallas.
 */
class Rol extends Model
{
    public const ADMINISTRADOR = 'Administrador';

    public const EMPRESA_INTERNA = 0;

    public const GUARD = 'web';

    protected $table = 'roles';

    protected $fillable = ['name', 'guard_name', 'active', 'description', 'empresa_id'];

    protected $casts = [
        'active' => 'boolean',
    ];

    public function scopeInternos($query)
    {
        return $query->where('empresa_id', self::EMPRESA_INTERNA)
            ->where('guard_name', self::GUARD);
    }

    /**
     * El Administrador es un rol del sistema: siempre tiene todos los
     * permisos, y no se puede renombrar ni editar desde la pantalla.
     */
    public function esDelSistema(): bool
    {
        return $this->name === self::ADMINISTRADOR;
    }
}
