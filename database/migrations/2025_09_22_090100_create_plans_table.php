<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('plans', function (Blueprint $table) {
            $table->increments('id');
            $table->string('nombre', 45)->default('');
            $table->char('sigla', 1)->nullable();
            $table->decimal('monto', 14, 4)->default(0);
            $table->integer('cantidad_u')->nullable();
            $table->char('lapso', 1)->nullable();
            $table->string('style', 25)->nullable();
            $table->string('paypal_id', 45)->nullable();
            $table->string('stripe_id', 191)->nullable();
            $table->char('tipo', 1)->default('')->comment('1=Primario; 2=Personal');
            $table->integer('cantidad_min')->nullable()
                ->comment('Cantidad minima de usuarios que debe tener el plan');
            $table->timestamp('created_at')->nullable();
            $table->timestamp('updated_at')->nullable();
        });

        // Seed sugerido de los 4 planes reales (ajustar monto/lapso/cantidad_min
        // con los valores confirmados por la empresa; estos son de ejemplo)
        // DB::table('plans')->insert([
        //     ['nombre' => 'Lite',    'sigla' => 'L', 'monto' => 15.00, 'lapso' => 'M', 'tipo' => '1', 'cantidad_min' => 1, 'created_at' => now(), 'updated_at' => now()],
        //     ['nombre' => 'Connect', 'sigla' => 'C', 'monto' => 25.00, 'lapso' => 'M', 'tipo' => '1', 'cantidad_min' => 1, 'created_at' => now(), 'updated_at' => now()],
        //     ['nombre' => 'Ultra',   'sigla' => 'U', 'monto' => 40.00, 'lapso' => 'M', 'tipo' => '1', 'cantidad_min' => 1, 'created_at' => now(), 'updated_at' => now()],
        //     ['nombre' => 'Insight', 'sigla' => 'I', 'monto' => 60.00, 'lapso' => 'M', 'tipo' => '1', 'cantidad_min' => 1, 'created_at' => now(), 'updated_at' => now()],
        // ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('plans');
    }
};
