<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Vistas de apoyo para dashboard y reportes. Laravel no tiene un
     * Schema Builder para vistas, se crean con SQL crudo.
     */
    public function up(): void
    {
        DB::statement("
            CREATE VIEW `vw_licencias_dashboard` AS
            SELECT
                l.id,
                e.razon_social,
                e.nombre_comercial,
                p.nombre AS plan,
                l.fecha_inicio,
                l.fecha_vencimiento,
                l.estado,
                DATEDIFF(l.fecha_vencimiento, CURDATE()) AS dias_restantes,
                l.monto,
                l.descuento,
                l.moneda,
                u.name AS vendedor
            FROM licencias l
            JOIN empresas e ON e.id = l.empresa_id
            JOIN plans p ON p.id = l.plan_id
            LEFT JOIN users u ON u.id = l.vendedor_id
        ");

        DB::statement("
            CREATE VIEW `vw_licencia_detalle_costos` AS
            SELECT
                d.licencia_id,
                t.codigo AS tipo_usuario,
                t.nombre AS tipo_usuario_nombre,
                d.cantidad,
                d.precio_unitario_aplicado,
                (d.cantidad * d.precio_unitario_aplicado) AS subtotal
            FROM licencia_detalle_usuarios d
            JOIN tipos_usuario t ON t.id = d.tipo_usuario_id
        ");
    }

    public function down(): void
    {
        DB::statement('DROP VIEW IF EXISTS `vw_licencias_dashboard`');
        DB::statement('DROP VIEW IF EXISTS `vw_licencia_detalle_costos`');
    }
};
