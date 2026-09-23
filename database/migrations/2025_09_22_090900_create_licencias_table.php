<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * El nucleo del modulo: la venta en si.
     *
     * Reglas de negocio (se validan en Laravel, no aqui):
     *  - No permitir una licencia nueva si la empresa ya tiene otra en
     *    estado V/X/P.
     *  - El cron diario cambia estado a 'N' si fecha_vencimiento ya paso.
     *  - Solo un usuario cuyo rol tenga el permiso "licenses.baja" puede
     *    pasar el estado a 'C' (Cancelada).
     *  - Al editar licencia_detalle_usuarios se recalcula `monto`.
     */
    public function up(): void
    {
        Schema::create('licencias', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('empresa_id')
                ->comment('Cliente que adquirio la licencia (FK a empresas)');
            $table->unsignedInteger('plan_id')
                ->comment('FK a plans (Lite/Connect/Ultra/Insight)');
            $table->unsignedInteger('reemplaza_a_licencia_id')->nullable();
            $table->string('codigo_licencia', 40)->nullable();
            $table->date('fecha_inicio');
            $table->date('fecha_vencimiento');
            $table->char('periodicidad', 1)->default('M')
                ->comment('M=Mensual, A=Anual, P=Personalizada');
            $table->decimal('monto', 12, 2)
                ->comment('plan.monto + suma(licencia_detalle_usuarios) - descuento');
            $table->decimal('descuento', 12, 2)->default(0);
            $table->string('moneda', 5)->default('USD');
            $table->char('estado', 1)->default('V')
                ->comment('V=Vigente, X=Por vencer, N=Vencida, P=En proceso, C=Cancelada');
            $table->unsignedBigInteger('vendedor_id')->nullable()
                ->comment('FK a users, quien registro la venta');
            $table->mediumText('observaciones')->nullable();
            $table->timestamp('created_at')->nullable();
            $table->timestamp('updated_at')->nullable();

            $table->index('empresa_id', 'licencias_empresa_id_index');
            $table->index('plan_id', 'licencias_plan_id_index');
            $table->index('estado', 'licencias_estado_index');
            $table->index('fecha_vencimiento', 'licencias_fecha_vencimiento_index');

            $table->foreign('empresa_id')
                ->references('id')->on('empresas');

            $table->foreign('plan_id')
                ->references('id')->on('plans');

            $table->foreign('reemplaza_a_licencia_id', 'licencias_reemplaza_a_foreign')
                ->references('id')->on('licencias');

            $table->foreign('vendedor_id', 'licencias_vendedor_foreing')
                ->references('id')->on('users')
                ->restrictOnDelete()
                ->restrictOnUpdate();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('licencias');
    }
};
