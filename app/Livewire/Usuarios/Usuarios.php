<?php

namespace App\Livewire\Usuarios;

use App\Models\Permission;
use App\Models\Rol;
use App\Models\User;
use App\Services\RolesUsuario;
use Flux\Flux;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Livewire\Attributes\Locked;
use Livewire\Attributes\On;
use Livewire\Component;

/**
 * Usuarios internos de Software4tech (users.empresa_id = 0).
 *
 * Cada usuario tiene UN rol, y los permisos reales son los del rol más los
 * "extras" que se le den a él en particular desde el botón de llave.
 */
class Usuarios extends Component
{
    // ---- Formulario de crear / editar ----

    /** #[Locked]: si no, desde el navegador se podría hacer que "Guardar" edite a OTRO usuario. */
    #[Locked]
    public ?int $usuario_id = null;

    public string $nombre = '';

    public string $email = '';

    public string $cargo = '';

    public string $password = '';

    /** String a propósito: el select manda texto y '' cuando no se ha elegido nada. */
    public string $rol_id = '';

    // ---- Modal de activar / desactivar ----

    #[Locked]
    public ?int $estado_id = null;

    /** true = se va a reactivar; false = se va a desactivar. */
    #[Locked]
    public bool $estado_activar = false;

    public string $estado_usuario = '';

    // ---- Modal de permisos extra ----

    #[Locked]
    public ?int $permisos_usuario_id = null;

    /**
     * permission_id => true/false. Un permiso va en true si lo da el ROL (se muestra
     * bloqueado) o si es un EXTRA de este usuario (se puede apagar). Se llenan todas
     * las llaves porque wire:model necesita que existan para enlazarse a su switch.
     *
     * @var array<int, bool>
     */
    public array $extras = [];

    public function mount(): void
    {
        Gate::authorize('usuarios.index');
    }

    // =====================================================================
    // Validación
    // =====================================================================

    protected function rules(): array
    {
        return [
            'nombre' => ['required', 'string', 'max:40'],
            'email' => ['required', 'email', 'max:191', Rule::unique('users', 'email')->ignore($this->usuario_id)],
            'cargo' => ['nullable', 'string', 'max:100'],
            'rol_id' => [
                'required',
                Rule::exists('roles', 'id')
                    ->where('empresa_id', Rol::EMPRESA_INTERNA)
                    ->where('guard_name', Rol::GUARD)
                    ->where('active', 1),
            ],
            // Al crear es obligatoria; al editar, vacía = no se cambia.
            'password' => $this->usuario_id
                ? ['nullable', Password::default()]
                : ['required', Password::default()],
        ];
    }

    protected function messages(): array
    {
        return [
            'required' => 'El campo :attribute es obligatorio.',
            'email' => 'Escribe un correo válido.',
            'nombre.max' => 'El nombre no puede superar los :max caracteres.',
            'email.max' => 'El correo no puede superar los :max caracteres.',
            'cargo.max' => 'El cargo no puede superar los :max caracteres.',
            'email.unique' => 'Ya existe un usuario con ese correo.',
            'rol_id.exists' => 'Selecciona un rol de la lista.',
        ];
    }

    protected function validationAttributes(): array
    {
        return [
            'nombre' => 'nombre',
            'email' => 'correo',
            'cargo' => 'cargo',
            'rol_id' => 'rol',
            'password' => 'contraseña',
        ];
    }

    /**
     * Validación en tiempo real: cuando el valor cambia (updated) y cuando el
     * usuario sale del campo aunque no haya escrito nada (wire:blur).
     */
    public function updated(string $property): void
    {
        $this->validarCampo($property);
    }

    public function validarCampo(string $campo): void
    {
        if (array_key_exists($campo, $this->rules())) {
            $this->validateOnly($campo);
        }
    }

    /**
     * No reemplaza la validación de guardar(): solo decide si el botón se deja presionar.
     */
    public function getPuedeGuardarProperty(): bool
    {
        $completos = trim($this->nombre) !== ''
            && trim($this->email) !== ''
            && ($this->esPropio() || $this->rol_id !== '')
            && ($this->usuario_id !== null || $this->password !== '');

        return $completos && $this->getErrorBag()->isEmpty();
    }

    private function esPropio(): bool
    {
        return $this->usuario_id !== null && $this->usuario_id === Auth::id();
    }

    // =====================================================================
    // Crear y editar
    // =====================================================================

    public function nuevo(): void
    {
        $this->reset(['usuario_id', 'nombre', 'email', 'cargo', 'password', 'rol_id']);
        $this->resetErrorBag();
    }

    #[On('editar-usuario')]
    public function abrirEdicion(int $id): void
    {
        if (! $this->autorizar('usuarios.edit')) {
            return;
        }

        $usuario = User::internos()->findOrFail($id);

        $this->usuario_id = $usuario->id;
        $this->nombre = (string) $usuario->name;
        $this->email = (string) $usuario->email;
        $this->cargo = (string) $usuario->cargo;
        $this->password = '';
        $this->rol_id = (string) (RolesUsuario::rolDe($usuario->id)?->id ?? '');

        $this->resetErrorBag();
        $this->modal('usuario-form')->show();
    }

    public function guardar(): void
    {
        $editando = $this->usuario_id !== null;

        if (! $this->autorizar($editando ? 'usuarios.edit' : 'usuarios.create')) {
            return;
        }

        $this->nombre = trim($this->nombre);
        $this->email = Str::lower(trim($this->email));
        $this->cargo = trim($this->cargo);

        $reglas = $this->rules();
        $propio = $this->esPropio();

        // Nadie se cambia a sí mismo el rol (podría quedarse sin acceso).
        if ($propio) {
            unset($reglas['rol_id']);
        }

        $datos = $this->validate($reglas);
        $cargo = ($datos['cargo'] ?? '') !== '' ? $datos['cargo'] : null;
        $password = (string) ($datos['password'] ?? '');

        if ($editando) {
            $usuario = User::internos()->findOrFail($this->usuario_id);
            $rolNuevo = $propio ? null : Rol::internos()->findOrFail((int) $this->rol_id);
            $rolActual = RolesUsuario::rolDe($usuario->id);

            // Sacar a un Administrador del rol no puede dejar el sistema sin ninguno activo.
            if (
                $rolNuevo && $rolActual && $rolActual->esDelSistema() && ! $rolNuevo->esDelSistema()
                && RolesUsuario::contarAdministradoresActivos($usuario->id) < 1
            ) {
                $this->addError('rol_id', 'Debe quedar al menos un administrador activo.');

                return;
            }

            DB::transaction(function () use ($usuario, $datos, $cargo, $password, $rolNuevo) {
                $usuario->forceFill(['name' => $datos['nombre'], 'email' => $datos['email'], 'cargo' => $cargo]);

                if ($password !== '') {
                    $usuario->password = $password; // el cast 'hashed' del modelo la encripta
                }

                $usuario->save();

                if ($rolNuevo) {
                    RolesUsuario::asignar($usuario->id, $rolNuevo);
                }
            });

            $mensaje = "El usuario {$datos['nombre']} se actualizó correctamente.";
        } else {
            $rol = Rol::internos()->findOrFail((int) $this->rol_id);

            DB::transaction(function () use ($datos, $cargo, $password, $rol) {
                $usuario = User::forceCreate([
                    'empresa_id' => User::EMPRESA_INTERNA,
                    'username' => $this->usernameUnico($datos['email']),
                    'name' => $datos['nombre'],
                    'email' => $datos['email'],
                    'cargo' => $cargo,
                    'password' => $password,
                    'email_verified_at' => now(),
                    // Columna heredada del CMMS: se deja en 0. El rol de ESTE sistema vive
                    // en model_has_roles, y poner aquí un id podría coincidir con un rol del CMMS.
                    'role_id' => 0,
                    'active' => 1,
                ]);

                RolesUsuario::asignar($usuario->id, $rol);
            });

            $mensaje = "El usuario {$datos['nombre']} se creó con el rol {$rol->name}.";
        }

        $this->modal('usuario-form')->close();
        $this->dispatch('usuario-guardado'); // refresca la tabla

        Flux::toast(heading: 'Listo', text: $mensaje, variant: 'success');

        $this->nuevo();
    }

    /**
     * users.username es obligatorio y único (viene del CMMS), pero aquí se inicia
     * sesión con el correo. Se genera solo a partir del correo para no pedir un dato más.
     */
    private function usernameUnico(string $email): string
    {
        $base = Str::of(Str::before($email, '@'))
            ->lower()
            ->replaceMatches('/[^a-z0-9._-]/', '')
            ->limit(40, '')
            ->toString();

        $base = $base !== '' ? $base : 'usuario';
        $candidato = $base;
        $n = 2;

        while (User::where('username', $candidato)->exists()) {
            $candidato = Str::limit($base, 40, '') . '-' . $n;
            $n++;
        }

        return $candidato;
    }

    // =====================================================================
    // Activar / desactivar
    // =====================================================================

    #[On('confirmar-estado-usuario')]
    public function confirmarEstado(int $id): void
    {
        if (! $this->autorizar('usuarios.desactivar')) {
            return;
        }

        $usuario = User::internos()->findOrFail($id);

        if ($motivo = $this->motivoNoDesactivable($usuario)) {
            Flux::toast(heading: 'No se puede desactivar', text: $motivo, variant: 'warning');

            return;
        }

        $this->estado_id = $usuario->id;
        $this->estado_activar = (int) $usuario->active !== 1;
        $this->estado_usuario = "{$usuario->name} ({$usuario->email})";

        $this->modal('confirmar-estado-usuario')->show();
    }

    public function cambiarEstado(): void
    {
        if (! $this->estado_id) {
            return;
        }

        // Se vuelve a validar aquí: el permiso pudo cambiar con el modal abierto,
        // o alguien pudo llamar este método directamente.
        if (! $this->autorizar('usuarios.desactivar')) {
            $this->cerrarModalEstado();

            return;
        }

        $usuario = User::internos()->findOrFail($this->estado_id);
        $activar = $this->estado_activar;

        if (! $activar && $motivo = $this->motivoNoDesactivable($usuario)) {
            $this->cerrarModalEstado();
            Flux::toast(heading: 'No se puede desactivar', text: $motivo, variant: 'warning');

            return;
        }

        $usuario->forceFill(['active' => $activar ? 1 : 0])->save();

        $this->cerrarModalEstado();
        $this->dispatch('usuario-guardado');

        Flux::toast(
            heading: $activar ? 'Usuario reactivado' : 'Usuario desactivado',
            text: $activar
                ? "{$usuario->name} puede volver a entrar."
                : "{$usuario->name} ya no puede entrar. Si tenía una sesión abierta, se cierra en su próxima acción.",
            variant: 'success',
        );
    }

    /**
     * Devuelve por qué NO se puede desactivar a este usuario, o null si se puede.
     * Reactivar siempre se puede.
     */
    private function motivoNoDesactivable(User $usuario): ?string
    {
        if ((int) $usuario->active !== 1) {
            return null;
        }

        if ($usuario->id === Auth::id()) {
            return 'No puedes desactivar tu propia cuenta.';
        }

        $rol = RolesUsuario::rolDe($usuario->id);

        if ($rol && $rol->esDelSistema() && RolesUsuario::contarAdministradoresActivos($usuario->id) < 1) {
            return 'Es el único administrador activo. Debe quedar al menos uno.';
        }

        return null;
    }

    private function cerrarModalEstado(): void
    {
        $this->modal('confirmar-estado-usuario')->close();
        $this->reset(['estado_id', 'estado_activar', 'estado_usuario']);
    }

    // =====================================================================
    // Permisos extra de un usuario
    // =====================================================================

    #[On('permisos-usuario')]
    public function abrirPermisos(int $id): void
    {
        if (! $this->autorizar('usuarios.permisos')) {
            return;
        }

        $usuario = User::internos()->findOrFail($id);

        $this->permisos_usuario_id = $usuario->id;
        $this->cargarExtras();

        $this->modal('permisos-usuario')->show();
    }

    private function cargarExtras(): void
    {
        $ids = Permission::whereNotNull('parent_id')->where('active', 1)->pluck('id');
        $base = array_fill_keys($ids->all(), false);

        if ($this->permisos_usuario_id) {
            $directos = DB::table('model_has_permissions')
                ->where('model_type', User::PERMISOS_MODEL_TYPE)
                ->where('model_id', $this->permisos_usuario_id)
                ->pluck('permission_id');

            foreach ([...$this->idsDelRol($this->permisos_usuario_id), ...$directos->all()] as $id) {
                if (array_key_exists($id, $base)) {
                    $base[$id] = true;
                }
            }
        }

        $this->extras = $base;
    }

    /**
     * Ids de los permisos que le da el rol (un rol desactivado no da ninguno).
     *
     * @return array<int, int>
     */
    private function idsDelRol(int $usuarioId): array
    {
        $rol = RolesUsuario::rolDe($usuarioId);

        if (! $rol || ! $rol->active) {
            return [];
        }

        if ($rol->esDelSistema()) {
            return Permission::whereNotNull('parent_id')->where('active', 1)->pluck('id')->all();
        }

        return DB::table('role_has_permissions')->where('role_id', $rol->id)->pluck('permission_id')->all();
    }

    private function esPermisoValido(int $permisoId): bool
    {
        return Permission::whereKey($permisoId)->whereNotNull('parent_id')->where('active', 1)->exists();
    }

    /**
     * Se dispara solo cuando el cambio viene del navegador (un clic en un switch).
     * Los permisos que da el rol están bloqueados en pantalla, pero aquí se
     * ignoran igual por si alguien los manipula.
     */
    public function updatedExtras($value, $key): void
    {
        $usuarioId = $this->permisos_usuario_id;
        $permisoId = (int) $key;

        if (
            $key === null || ! $usuarioId || ! Gate::allows('usuarios.permisos')
            || ! $this->esPermisoValido($permisoId)
            || in_array($permisoId, $this->idsDelRol($usuarioId), true)
        ) {
            $this->cargarExtras(); // deshace lo que el navegador haya intentado

            return;
        }

        if ($value) {
            $this->otorgar($usuarioId, [$permisoId]);
        } else {
            $this->revocar($usuarioId, [$permisoId]);
        }
    }

    public function marcarTodoExtras(int $moduloId): void
    {
        $this->cambiarModuloExtras($moduloId, true);
    }

    public function quitarTodoExtras(int $moduloId): void
    {
        $this->cambiarModuloExtras($moduloId, false);
    }

    /**
     * Prende o apaga los extras de un módulo. Lo que da el rol no se toca.
     */
    private function cambiarModuloExtras(int $moduloId, bool $encender): void
    {
        $usuarioId = $this->permisos_usuario_id;

        if (
            ! $usuarioId || ! $this->autorizar('usuarios.permisos')
            || ! Permission::modulos()->whereKey($moduloId)->exists()
        ) {
            return;
        }

        $delRol = $this->idsDelRol($usuarioId);

        $ids = array_values(array_diff(
            Permission::where('parent_id', $moduloId)->where('active', 1)->pluck('id')->all(),
            $delRol,
        ));

        if ($encender) {
            $this->otorgar($usuarioId, $ids);
        } else {
            $this->revocar($usuarioId, $ids);
        }

        foreach ($ids as $id) {
            $this->extras[$id] = $encender;
        }

        Flux::toast(
            text: $encender ? 'Se activaron los permisos extra de este módulo.' : 'Se quitaron los permisos extra de este módulo.',
            variant: $encender ? 'success' : 'warning',
        );
    }

    /**
     * @param  array<int, int>  $idsPermisos
     */
    private function otorgar(int $usuarioId, array $idsPermisos): void
    {
        if (empty($idsPermisos)) {
            return;
        }

        DB::table('model_has_permissions')->insertOrIgnore(
            array_map(fn(int $id) => [
                'permission_id' => $id,
                'model_type' => User::PERMISOS_MODEL_TYPE,
                'model_id' => $usuarioId,
            ], $idsPermisos)
        );
    }

    /**
     * @param  array<int, int>  $idsPermisos
     */
    private function revocar(int $usuarioId, array $idsPermisos): void
    {
        if (empty($idsPermisos)) {
            return;
        }

        DB::table('model_has_permissions')
            ->whereIn('permission_id', $idsPermisos)
            ->where('model_type', User::PERMISOS_MODEL_TYPE)
            ->where('model_id', $usuarioId)
            ->delete();
    }

    // =====================================================================

    /**
     * true si el usuario tiene el permiso; si no, avisa con un toast y
     * devuelve false para que el método que llamó se detenga.
     */
    private function autorizar(string $permiso): bool
    {
        if (Gate::allows($permiso)) {
            return true;
        }

        Flux::toast(
            heading: 'Sin permiso',
            text: 'No tienes permiso para realizar esta acción.',
            variant: 'danger',
        );

        return false;
    }

    public function render()
    {
        $extrasUsuario = $this->permisos_usuario_id ? User::find($this->permisos_usuario_id) : null;

        return view('livewire.usuarios.usuarios', [
            'roles' => Rol::internos()->where('active', 1)->orderBy('id')->get(),
            'esPropio' => $this->esPropio(),

            // Solo se cargan cuando el modal de permisos extra está en uso.
            'extrasUsuario' => $extrasUsuario,
            'rolDelExtras' => $extrasUsuario ? RolesUsuario::rolDe($extrasUsuario->id) : null,
            'idsDelRolExtras' => $extrasUsuario ? array_flip($this->idsDelRol($extrasUsuario->id)) : [],
            'modulos' => $extrasUsuario
                ? Permission::modulos()->with(['children' => fn($q) => $q->where('active', 1)])->get()
                : collect(),
        ]);
    }
}
