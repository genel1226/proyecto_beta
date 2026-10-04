<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Catálogo de permisos del sistema (22 permisos en 6 módulos).
 *
 * Se puede correr las veces que haga falta: si un permiso ya existe
 * (mismo name + guard_name) lo actualiza en vez de duplicarlo.
 *
 *   php artisan db:seed --class=PermisosSeeder
 *
 * Al final le asigna TODOS los permisos al primer usuario de la tabla
 * `users` (el de id más bajo), para que alguien pueda usar la pantalla
 * de switches y repartir permisos al resto.
 *
 * Estructura de `permissions`:
 *   - Cada módulo tiene una fila "título" (type 0): agrupa, no otorga nada.
 *   - Cada permiso cuelga de su título vía parent_id.
 *   - type: 1 = lectura, 2 = edición (mismo criterio que ya usa la tabla).
 *
 * Reglas de dependencia (las debe respetar la pantalla de switches):
 *   - <modulo>.index es la puerta de entrada: sin ella, el resto de
 *     acciones de ese módulo no sirven.
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

        'GESTION DE PERMISOS' => [
            'en' => 'PERMISSION MANAGEMENT',
            'permisos' => [
                ['permisos.admin', '2', 'Usar la pantalla de switches para asignar permisos a otros usuarios', 'Manage other users permissions'],
            ],
        ],
    ];

    public function run(): void
    {
        DB::transaction(function () {
            $idsAsignables = [];

            foreach (self::CATALOGO as $titulo => $modulo) {
                $idTitulo = $this->upsert(
                    ['name' => $titulo, 'guard_name' => self::GUARD],
                    ['parent_id' => null, 'description' => $titulo, 'description_en' => $modulo['en'], 'active' => 1, 'type' => '0'],
                );

                foreach ($modulo['permisos'] as [$nombre, $type, $descripcion, $descripcionEn]) {
                    $idsAsignables[] = $this->upsert(
                        ['name' => $nombre, 'guard_name' => self::GUARD],
                        ['parent_id' => $idTitulo, 'description' => $descripcion, 'description_en' => $descripcionEn, 'active' => 1, 'type' => $type],
                    );
                }
            }

            $this->command?->info(count($idsAsignables).' permisos sincronizados.');

            $this->asignarTodosAlPrimerUsuario($idsAsignables);
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

    /**
     * @param  array<int, int>  $idsPermisos
     */
    private function asignarTodosAlPrimerUsuario(array $idsPermisos): void
    {
        $admin = User::query()->orderBy('id')->first();

        if (! $admin) {
            $this->command?->warn('No hay usuarios todavía: los permisos quedaron creados pero sin asignar a nadie.');

            return;
        }

        // insertOrIgnore: la clave primaria es (permission_id, model_id, model_type),
        // así que volver a correr el seeder no duplica ni falla.
        DB::table('model_has_permissions')->insertOrIgnore(
            array_map(fn (int $idPermiso) => [
                'permission_id' => $idPermiso,
                'model_type' => User::PERMISOS_MODEL_TYPE,
                'model_id' => $admin->id,
            ], $idsPermisos)
        );

        $this->command?->info("Los {$this->contar($idsPermisos)} permisos quedaron asignados al usuario #{$admin->id} ({$admin->email}).");
    }

    /**
     * @param  array<int, int>  $ids
     */
    private function contar(array $ids): int
    {
        return count($ids);
    }
}
