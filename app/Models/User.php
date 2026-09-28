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
     * Nombres de todos los permisos activos asignados directamente a
     * este usuario. Es aditivo: cada fila de model_has_permissions SUMA
     * un permiso, y borrarla lo quita. No hay permisos heredados de roles.
     *
     * @return array<int, string>
     */
    public function permisos(): array
    {
        return $this->permisosCache ??= DB::table('model_has_permissions')
            ->join('permissions', 'permissions.id', '=', 'model_has_permissions.permission_id')
            ->where('model_has_permissions.model_type', self::PERMISOS_MODEL_TYPE)
            ->where('model_has_permissions.model_id', $this->id)
            ->where('permissions.active', 1)
            ->whereNull('permissions.deleted_at')
            ->pluck('permissions.name')
            ->all();
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
            ? Str::substr($initials, 0, 1).Str::substr($initials, -1)
            : $initials;
    }
}
