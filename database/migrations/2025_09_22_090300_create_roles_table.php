<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('roles', function (Blueprint $table) {
            $table->increments('id');
            $table->string('name', 191);
            $table->string('guard_name', 191);
            $table->boolean('active')->default(true);
            $table->string('description', 191)->default('null');
            $table->unsignedInteger('empresa_id')->default(1);
            $table->timestamp('created_at')->nullable();
            $table->timestamp('updated_at')->nullable();
        });

        // Sugerido para el modulo de licencias (ajustar si ya existen roles
        // equivalentes en la BD real):
        // DB::table('roles')->insert([
        //     ['name' => 'Administrador Licencias', 'guard_name' => 'web', 'active' => 1, 'description' => 'Crea, edita y da de baja licencias', 'empresa_id' => 1, 'created_at' => now(), 'updated_at' => now()],
        //     ['name' => 'Vendedor', 'guard_name' => 'web', 'active' => 1, 'description' => 'Crea y edita licencias de sus clientes', 'empresa_id' => 1, 'created_at' => now(), 'updated_at' => now()],
        //     ['name' => 'Visualizador Licencias', 'guard_name' => 'web', 'active' => 1, 'description' => 'Solo consulta estado/plan/fechas', 'empresa_id' => 1, 'created_at' => now(), 'updated_at' => now()],
        // ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('roles');
    }
};
