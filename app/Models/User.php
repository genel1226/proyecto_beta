<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Laravel\Fortify\Contracts\PasskeyUser;
use Laravel\Fortify\PasskeyAuthenticatable;
use Laravel\Fortify\TwoFactorAuthenticatable;

/**
 * @property int $id
 * @property string $name
 * @property string $email
 * @property Carbon|null $email_verified_at
 * @property string $password
 * @property string|null $two_factor_secret
 * @property string|null $two_factor_recovery_codes
 * @property Carbon|null $two_factor_confirmed_at
 * @property string|null $remember_token
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['name', 'email', 'password'])]
#[Hidden(['password', 'two_factor_secret', 'two_factor_recovery_codes', 'remember_token'])]
class User extends Authenticatable implements PasskeyUser
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable, PasskeyAuthenticatable, TwoFactorAuthenticatable;

    /**
     * Código que identifica a este modelo en la columna `model_type`
     * (char(2)) de model_has_permissions. Se define aquí una sola vez;
     * el seeder y las consultas lo leen de esta constante.
     */
    public const PERMISOS_MODEL_TYPE = 'US';

    /**
     * Valor de users.empresa_id que marca al personal interno de Software4tech.
     * Esta aplicación solo administra a esos usuarios; los de las empresas
     * clientes (empresa_id distinto de 0) quedan fuera de las pantallas.
     */
    public const EMPRESA_INTERNA = 0;

    /**
     * Permisos del usuario, cargados una sola vez por request.
     * Sin esto, cada @can / Gate::allows dispararía su propia consulta.
     *
     * @var array<int, string>|null
     */
    private ?array $permisosCache = null;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    /**
     * Nombres de todos los permisos activos del usuario: los de su rol MÁS los
     * extras que se le dieron a él en particular (model_has_permissions).
     *
     * Los extras solo suman: sirven para darle a un usuario algo más que su
     * rol, pero no para quitarle algo que el rol incluye (para eso se crea otro
     * rol). Un rol desactivado no aporta permisos.
     *
     * @return array<int, string>
     */
    public function permisos(): array
    {
        if ($this->permisosCache !== null) {
            return $this->permisosCache;
        }

        $delRol = DB::table('model_has_roles as mr')
            ->join('roles as r', 'r.id', '=', 'mr.role_id')
            ->join('role_has_permissions as rp', 'rp.role_id', '=', 'mr.role_id')
            ->join('permissions as p', 'p.id', '=', 'rp.permission_id')
            ->where('mr.model_type', self::PERMISOS_MODEL_TYPE)
            ->where('mr.model_id', $this->id)
            ->where('r.active', 1)
            ->where('p.active', 1)
            ->whereNull('p.deleted_at')
            ->pluck('p.name');

        $extras = DB::table('model_has_permissions as mp')
            ->join('permissions as p', 'p.id', '=', 'mp.permission_id')
            ->where('mp.model_type', self::PERMISOS_MODEL_TYPE)
            ->where('mp.model_id', $this->id)
            ->where('p.active', 1)
            ->whereNull('p.deleted_at')
            ->pluck('p.name');

        return $this->permisosCache = $delRol->merge($extras)->unique()->values()->all();
    }

    /**
     * Solo el personal interno de Software4tech (empresa_id = 0).
     */
    public function scopeInternos($query)
    {
        return $query->where('empresa_id', self::EMPRESA_INTERNA);
    }

    public function tienePermiso(string $permiso): bool
    {
        return in_array($permiso, $this->permisos(), true);
    }

    /**
     * Llamar después de modificar los permisos del usuario (por ejemplo,
     * desde la pantalla de switches) si se van a volver a consultar en
     * ese mismo request.
     */
    public function olvidarPermisos(): void
    {
        $this->permisosCache = null;
    }

    /**
     * Get the user's initials
     */
    public function initials(): string
    {
        $initials = Str::initials($this->name, true);

        return Str::length($initials) > 1
            ? Str::substr($initials, 0, 1) . Str::substr($initials, -1)
            : $initials;
    }
}
