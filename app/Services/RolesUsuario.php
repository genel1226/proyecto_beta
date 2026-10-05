<?php

namespace App\Services;

use App\Models\Rol;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Un usuario tiene UN solo rol a la vez. La tabla model_has_roles no lo
 * impide por sí sola (su clave permite varias filas), así que esta clase es
 * el único lugar que escribe en ella y mantiene esa regla.
 */
class RolesUsuario
{
    public static function rolDe(int $usuarioId): ?Rol
    {
        return Rol::query()
            ->internos()
            ->whereIn('id', DB::table('model_has_roles')
                ->where('model_type', User::PERMISOS_MODEL_TYPE)
                ->where('model_id', $usuarioId)
                ->select('role_id'))
            ->first();
    }

    /**
     * Le pone ESTE rol al usuario, quitándole el que tuviera antes.
     * Solo toca roles internos: no borra nada que pertenezca a otro sistema.
     */
    public static function asignar(int $usuarioId, Rol $rol): void
    {
        DB::transaction(function () use ($usuarioId, $rol) {
            DB::table('model_has_roles')
                ->where('model_type', User::PERMISOS_MODEL_TYPE)
                ->where('model_id', $usuarioId)
                ->whereIn('role_id', Rol::internos()->select('id'))
                ->delete();

            DB::table('model_has_roles')->insert([
                'role_id' => $rol->id,
                'model_type' => User::PERMISOS_MODEL_TYPE,
                'model_id' => $usuarioId,
            ]);
        });
    }

    /**
     * Cuántos administradores ACTIVOS hay, sin contar a uno en particular.
     * Se usa para no dejar el sistema sin ningún administrador.
     */
    public static function contarAdministradoresActivos(?int $excluirUsuarioId = null): int
    {
        return DB::table('model_has_roles as mr')
            ->join('roles as r', 'r.id', '=', 'mr.role_id')
            ->join('users as u', 'u.id', '=', 'mr.model_id')
            ->where('mr.model_type', User::PERMISOS_MODEL_TYPE)
            ->where('r.name', Rol::ADMINISTRADOR)
            ->where('r.empresa_id', Rol::EMPRESA_INTERNA)
            ->where('r.active', 1)
            ->where('u.active', 1)
            ->when($excluirUsuarioId, fn($q) => $q->where('u.id', '!=', $excluirUsuarioId))
            ->count();
    }
}
