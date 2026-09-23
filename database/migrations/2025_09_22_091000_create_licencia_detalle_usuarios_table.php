<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Cuantos usuarios de cada tipo tiene una licencia, y a que precio se
     * vendieron. Aqui vive el calculo real del costo.
     */
    public function up(): void
    {
        Schema::create('licencia_detalle_usuarios', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('licencia_id');
            $table->unsignedInteger('tipo_usuario_id');
            $table->integer('cantidad')->default(0);
            $table->decimal('precio_unitario_aplicado', 10, 2)
                ->comment('Copia de tipos_usuario.precio_unitario al momento de la venta');
            $table->timestamp('created_at')->nullable();
            $table->timestamp('updated_at')->nullable();

            $table->unique(['licencia_id', 'tipo_usuario_id'], 'licencia_detalle_unico');

            $table->foreign('licencia_id')
                ->references('id')->on('licencias');

            $table->foreign('tipo_usuario_id')
                ->references('id')->on('tipos_usuario');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('licencia_detalle_usuarios');
    }
};
