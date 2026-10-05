<?php

namespace Database\Seeders;

use App\Models\Rol;
use App\Models\User;
use App\Services\RolesUsuario;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Catálogo de permisos y roles base del sistema.
 *
 * Se puede correr las veces que haga falta, sin duplicar nada:
 *
 *   php artisan db:seed --class=PermisosSeeder
 *
 * Qué hace, en orden:
 *   1. Crea o actualiza los permisos del catálogo (y desactiva los que ya no existen).
 *   2. Crea los 3 roles base. El Administrador SIEMPRE se resincroniza con todos los
 *      permisos (así los permisos nuevos le llegan solos). Los demás reciben sus
 *      permisos base solo la PRIMERA vez: después se editan desde la pantalla Roles
 *      y volver a correr el seeder NO pisa esos cambios.
 *   3. Si el usuario de id más bajo todavía no tiene rol, lo hace Administrador.
 *
 * Estructura de `permissions`:
 *   - Cada módulo tiene una fila "título" (type 0): agrupa, no otorga nada.
 *   - Cada permiso cuelga de su título vía parent_id.
 *   - type: 1 = lectura, 2 = edición.
 *
 * Reglas de dependencia (aún no se imponen en las pantallas):
 *   - <modulo>.index es la puerta de entrada: sin ella, el resto de acciones de
 *     ese módulo no sirven.
 *   - licenses.descuento.aplicar exige licenses.descuento.ver.
 */
class PermisosSeeder extends Seeder
{
    private const GUARD = 'web';

    /**
     * [nombre del permiso, type, descripción ES, descripción EN]
     */
    private const CATALOGO = [
        'GESTION DE EMPRESAS' => [
            'en' => 'COMPANY MANAGEMENT',
            'permisos' => [
                ['empresas.index', '1', 'Ver la lista de empresas', 'View the companies list'],
                ['empresas.create', '2', 'Crear empresas', 'Create companies'],
                ['empresas.edit', '2', 'Editar empresas', 'Edit companies'],
                ['empresas.desactivar', '2', 'Desactivar y reactivar empresas', 'Deactivate and reactivate companies'],
            ],
        ],

        'GESTION DE LICENCIAS' => [
            'en' => 'LICENSE MANAGEMENT',
            'permisos' => [
                ['licenses.index', '1', 'Ver la lista de licencias (código, empresa, plan, fechas y estado)', 'View the licenses list'],
                ['licenses.create', '2', 'Crear licencias', 'Create licenses'],
                ['licenses.edit', '2', 'Editar licencias', 'Edit licenses'],
                ['licenses.baja', '2', 'Dar de baja licencias', 'Cancel licenses'],
                ['licenses.monto.ver', '1', 'Ver el monto de las licencias', 'View license amounts'],
                ['licenses.descuento.ver', '1', 'Ver el descuento de las licencias', 'View license discounts'],
                ['licenses.descuento.aplicar', '2', 'Aplicar o modificar descuentos (requiere ver descuentos)', 'Apply or change discounts'],
                ['licenses.precios.ver', '1', 'Ver precios por tipo de usuario y monto base del plan', 'View per-user prices and plan base price'],
                ['licenses.observaciones.ver', '1', 'Ver y escribir las observaciones de una licencia', 'View and write license notes'],
                ['licenses.renovar', '2', 'Renovar licencias (registrar el pago de un nuevo período)', 'Renew licenses'],
                ['licenses.activar', '2', 'Activar licencias en proceso y reactivar licencias dadas de baja', 'Activate or reactivate licenses'],
            ],
        ],

        'GESTION DE TIPOS DE USUARIO' => [
            'en' => 'USER TYPE MANAGEMENT',
            'permisos' => [
                ['tipos_usuario.index', '1', 'Ver el catálogo de tipos de usuario y sus precios', 'View the user types catalog'],
                ['tipos_usuario.create', '2', 'Crear tipos de usuario', 'Create user types'],
                ['tipos_usuario.edit', '2', 'Editar tipos de usuario y sus precios', 'Edit user types and prices'],
                ['tipos_usuario.desactivar', '2', 'Desactivar y reactivar tipos de usuario', 'Deactivate and reactivate user types'],
            ],
        ],

        'GESTION DE PAGOS' => [
            'en' => 'PAYMENT MANAGEMENT',
            'permisos' => [
                ['pagos.index', '1', 'Ver el historial de pagos', 'View the payments history'],
                ['pagos.create', '2', 'Registrar pagos', 'Register payments'],
            ],
        ],

        'REPORTES' => [
            'en' => 'REPORTS',
            'permisos' => [
                ['reportes.index', '1', 'Entrar a la sección de reportes', 'Access the reports section'],
                ['reportes.montos', '1', 'Ver montos dentro de los reportes', 'View amounts inside reports'],
                ['reportes.export', '1', 'Exportar reportes a Excel y PDF', 'Export reports to Excel and PDF'],
            ],
        ],

        'GESTION DE USUARIOS' => [
            'en' => 'USER MANAGEMENT',
            'permisos' => [
                ['usuarios.index', '1', 'Ver la lista de usuarios del sistema', 'View the system users list'],
                ['usuarios.create', '2', 'Crear usuarios', 'Create users'],
                ['usuarios.edit', '2', 'Editar usuarios y cambiarles el rol', 'Edit users and change their role'],
                ['usuarios.desactivar', '2', 'Desactivar y reactivar usuarios', 'Deactivate and reactivate users'],
                ['usuarios.permisos', '2', 'Dar o quitar permisos extra a un usuario en particular', 'Grant or remove extra permissions for a single user'],
            ],
        ],

        'GESTION DE ROLES' => [
            'en' => 'ROLE MANAGEMENT',
            'permisos' => [
                ['roles.index', '1', 'Ver los roles y los permisos de cada uno', 'View roles and their permissions'],
                ['roles.create', '2', 'Crear roles', 'Create roles'],
                ['roles.edit', '2', 'Editar roles y sus permisos (afecta a todos los usuarios con ese rol)', 'Edit roles and their permissions'],
            ],
        ],
    ];

    /**
     * Permisos que ya no existen en el catálogo (antes había una sola pantalla de
     * "switches" por usuario; ahora son Roles y Usuarios). Se desactivan para que no
     * aparezcan en las pantallas; no se borran.
     */
    private const OBSOLETOS = ['permisos.admin', 'GESTION DE PERMISOS'];

    /**
     * Roles base del personal interno (empresa_id = 0).
     * 'permisos' => '*' significa todos los del catálogo.
     */
    private const ROLES = [
        Rol::ADMINISTRADOR => [
            'descripcion' => 'Acceso total. Rol del sistema: siempre tiene todos los permisos y no se puede editar.',
            'permisos' => '*',
        ],

        'Gestor comercial' => [
            'descripcion' => 'Opera empresas, licencias, cobros y reportes. No da de baja licencias ni administra usuarios.',
            'permisos' => [
                'empresas.index',
                'empresas.create',
                'empresas.edit',
                'licenses.index',
                'licenses.create',
                'licenses.edit',
                'licenses.renovar',
                'licenses.activar',
                'licenses.monto.ver',
                'licenses.precios.ver',
                'licenses.descuento.ver',
                'licenses.descuento.aplicar',
                'licenses.observaciones.ver',
                'pagos.index',
                'pagos.create',
                'tipos_usuario.index',
                'reportes.index',
                'reportes.montos',
                'reportes.export',
            ],
        ],

        'Auditor' => [
            'descripcion' => 'Solo consulta: ve empresas, licencias, pagos y reportes, sin montos ni acciones.',
            'permisos' => ['empresas.index', 'licenses.index', 'pagos.index', 'reportes.index'],
        ],
    ];

    public function run(): void
    {
        DB::transaction(function () {
            /** @var array<string, int> $idsPorNombre nombre del permiso => id */
            $idsPorNombre = [];

            foreach (self::CATALOGO as $titulo => $modulo) {
                $idTitulo = $this->upsert(
                    ['name' => $titulo, 'guard_name' => self::GUARD],
                    ['parent_id' => null, 'description' => $titulo, 'description_en' => $modulo['en'], 'active' => 1, 'type' => '0'],
                );

                foreach ($modulo['permisos'] as [$nombre, $type, $descripcion, $descripcionEn]) {
                    $idsPorNombre[$nombre] = $this->upsert(
                        ['name' => $nombre, 'guard_name' => self::GUARD],
                        ['parent_id' => $idTitulo, 'description' => $descripcion, 'description_en' => $descripcionEn, 'active' => 1, 'type' => $type],
                    );
                }
            }

            $this->command?->info(count($idsPorNombre) . ' permisos sincronizados.');

            $this->desactivarObsoletos();

            $roles = $this->sincronizarRoles($idsPorNombre);

            $this->asignarRolAlPrimerUsuario($roles[Rol::ADMINISTRADOR]);
        });
    }

    /**
     * Crea la fila si no existe; si existe, la actualiza (sin tocar created_at).
     * Devuelve el id.
     *
     * @param  array<string, mixed>  $clave
     * @param  array<string, mixed>  $valores
     */
    private function upsert(array $clave, array $valores): int
    {
        $existente = DB::table('permissions')->where($clave)->first();

        if ($existente) {
            DB::table('permissions')
                ->where('id', $existente->id)
                ->update($valores + ['updated_at' => now()]);

            return (int) $existente->id;
        }

        return (int) DB::table('permissions')->insertGetId(
            $clave + $valores + ['created_at' => now(), 'updated_at' => now()]
        );
    }

    private function desactivarObsoletos(): void
    {
        DB::table('permissions')
            ->whereIn('name', self::OBSOLETOS)
            ->update(['active' => 0, 'updated_at' => now()]);
    }

    /**
     * Crea los roles que falten y les da sus permisos base.
     *
     * @param  array<string, int>  $idsPorNombre
     * @return array<string, int> nombre del rol => id
     */
    private function sincronizarRoles(array $idsPorNombre): array
    {
        $roles = [];

        foreach (self::ROLES as $nombre => $definicion) {
            $existente = DB::table('roles')
                ->where('name', $nombre)
                ->where('guard_name', self::GUARD)
                ->where('empresa_id', Rol::EMPRESA_INTERNA)
                ->first();

            $esNuevo = $existente === null;

            $idRol = $esNuevo
                ? (int) DB::table('roles')->insertGetId([
                    'name' => $nombre,
                    'guard_name' => self::GUARD,
                    'active' => 1,
                    'description' => $definicion['descripcion'],
                    'empresa_id' => Rol::EMPRESA_INTERNA,
                    'created_at' => now(),
                    'updated_at' => now(),
                ])
                : (int) $existente->id;

            $roles[$nombre] = $idRol;

            if ($definicion['permisos'] === '*') {
                // Administrador: siempre todos, también en cada corrida posterior.
                $this->darPermisos($idRol, array_values($idsPorNombre));
            } elseif ($esNuevo) {
                // Los demás roles: solo la primera vez, para no pisar lo que se edite después.
                $this->darPermisos($idRol, array_map(fn(string $nombrePermiso) => $idsPorNombre[$nombrePermiso], $definicion['permisos']));
            }

            $this->command?->info($esNuevo ? "Rol creado: {$nombre}" : "Rol existente: {$nombre}");
        }

        return $roles;
    }

    /**
     * @param  array<int, int>  $idsPermisos
     */
    private function darPermisos(int $idRol, array $idsPermisos): void
    {
        // insertOrIgnore: la clave primaria es (permission_id, role_id), así que
        // volver a correr el seeder no duplica ni falla.
        DB::table('role_has_permissions')->insertOrIgnore(
            array_map(fn(int $idPermiso) => ['permission_id' => $idPermiso, 'role_id' => $idRol], $idsPermisos)
        );
    }

    /**
     * El usuario de id más bajo (quien ya tenía todos los permisos) pasa a ser
     * Administrador, solo si todavía no tiene rol. Sus permisos directos de antes
     * se borran porque el rol ya los incluye todos y quedarían duplicados.
     */
    private function asignarRolAlPrimerUsuario(int $idRolAdministrador): void
    {
        $primero = User::query()->orderBy('id')->first();

        if (! $primero) {
            $this->command?->warn('No hay usuarios todavía: los roles quedaron creados pero sin asignar a nadie.');

            return;
        }

        if (RolesUsuario::rolDe($primero->id)) {
            return;
        }

        RolesUsuario::asignar($primero->id, Rol::findOrFail($idRolAdministrador));

        // Que no se quede fuera de su propia lista (solo ve a los internos) ni inactivo.
        DB::table('users')->where('id', $primero->id)->update([
            'active' => 1,
            'empresa_id' => User::EMPRESA_INTERNA,
        ]);

        DB::table('model_has_permissions')
            ->where('model_type', User::PERMISOS_MODEL_TYPE)
            ->where('model_id', $primero->id)
            ->delete();

        $this->command?->info("El usuario #{$primero->id} ({$primero->email}) ahora es " . Rol::ADMINISTRADOR . '.');
    }
}
