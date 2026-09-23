<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Tabla informativa (comparativo de caracteristicas por plan para la
     * pagina de precios). NO tiene FK hacia `plans`, no participa en la
     * logica de venta ni de licencias.
     *
     * Mapeo de nomenclatura (las columnas no se renombran, son del sistema
     * real): free=Lite, starter=Connect, profesional=Ultra, empresarial=Insight
     */
    public function up(): void
    {
        Schema::create('plan_benefits', function (Blueprint $table) {
            $table->increments('id');
            $table->integer('parent_id')->nullable();
            $table->mediumText('nombre')->nullable();
            $table->mediumText('name')->nullable();
            $table->string('free', 45)->nullable()->comment('Corresponde al plan Lite');
            $table->string('starter', 45)->nullable()->comment('Corresponde al plan Connect');
            $table->string('profesional', 45)->nullable()->comment('Corresponde al plan Ultra');
            $table->string('empresarial', 45)->nullable()->comment('Corresponde al plan Insight');
            $table->timestamp('created_at')->nullable();
            $table->timestamp('updated_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('plan_benefits');
    }
};
