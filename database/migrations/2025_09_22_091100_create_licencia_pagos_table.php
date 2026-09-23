<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Historial de pagos/renovaciones. Sin metodo de pago ni comprobantes:
     * el sistema no factura, solo monitorea.
     */
    public function up(): void
    {
        Schema::create('licencia_pagos', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('licencia_id');
            $table->dateTime('fecha_pago');
            $table->decimal('monto', 12, 2);
            $table->date('fecha_vencimiento_anterior')->nullable();
            $table->date('fecha_vencimiento_nueva');
            $table->unsignedBigInteger('registrado_por')->nullable()
                ->comment('FK a users');
            $table->timestamp('created_at')->nullable();

            $table->index('licencia_id', 'licencia_pagos_licencia_id_index');

            $table->foreign('licencia_id')
                ->references('id')->on('licencias');

            $table->foreign('registrado_por', 'licencia_pagos_realizado_foreing')
                ->references('id')->on('users')
                ->restrictOnDelete()
                ->restrictOnUpdate();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('licencia_pagos');
    }
};
