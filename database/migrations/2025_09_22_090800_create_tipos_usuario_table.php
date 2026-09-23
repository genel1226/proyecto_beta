<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Catalogo GT-1 / GT-2 / GT-3 con su precio. Editable por el admin.
     */
    public function up(): void
    {
        Schema::create('tipos_usuario', function (Blueprint $table) {
            $table->increments('id');
            $table->string('codigo', 10)->comment('GT-1, GT-2, GT-3');
            $table->string('nombre', 60)->comment('Ej: Administrador, Jefe Tecnico, Auxiliar');
            $table->decimal('precio_unitario', 10, 2)
                ->comment('Precio de referencia por usuario de este tipo');
            $table->boolean('activo')->default(true);
            $table->timestamp('created_at')->nullable();
            $table->timestamp('updated_at')->nullable();

            $table->unique('codigo', 'tipos_usuario_codigo_unique');
        });

        // Valores de ejemplo (confidenciales, ajustar con la empresa)
        DB::table('tipos_usuario')->insert([
            ['codigo' => 'GT-1', 'nombre' => 'Administrador', 'precio_unitario' => 10.00, 'activo' => 1, 'created_at' => now(), 'updated_at' => now()],
            ['codigo' => 'GT-2', 'nombre' => 'Jefe Tecnico', 'precio_unitario' => 7.00, 'activo' => 1, 'created_at' => now(), 'updated_at' => now()],
            ['codigo' => 'GT-3', 'nombre' => 'Auxiliar / solo ordenes de trabajo', 'precio_unitario' => 3.00, 'activo' => 1, 'created_at' => now(), 'updated_at' => now()],
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('tipos_usuario');
    }
};
