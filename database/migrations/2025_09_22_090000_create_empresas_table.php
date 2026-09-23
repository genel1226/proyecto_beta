<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('empresas', function (Blueprint $table) {
            $table->increments('id');
            $table->char('cod', 4);
            $table->string('razon_social', 191);
            $table->string('nombre_comercial', 191);
            $table->string('nit', 20);
            $table->string('slug', 191);
            $table->string('logotipo', 191)->nullable()->default('default.png');
            $table->string('pagina_web', 60)->nullable();
            $table->string('email', 50)->nullable();
            $table->string('telefono', 50)->nullable();
            $table->boolean('active')->default(true);
            $table->string('pais', 45)->nullable();
            $table->mediumText('direccion')->nullable();
            $table->string('mail_color', 20)->nullable();
            $table->char('plan', 1)->nullable();
            $table->integer('plan_id')->nullable();
            $table->integer('cantidad_u')->nullable();
            $table->string('timezone', 80)->nullable();
            $table->char('gmt_hour', 6)->nullable();
            $table->integer('gmt')->nullable();
            $table->char('default_language', 2)->nullable()->default('es');
            $table->char('registro', 1)->nullable()->default('1')
                ->comment('0 => La empresa debe finalizar su registro / 1 => La empresa fue registrada exitosamente');
            $table->timestamp('created_at')->nullable();
            $table->timestamp('updated_at')->nullable();
            $table->string('stripe_id', 191)->nullable();
            $table->string('card_brand', 191)->nullable();
            $table->string('card_last_four', 4)->nullable();
            $table->timestamp('trial_ends_at')->nullable();
            $table->char('request_asset_inspection_location', 1)->nullable()->default('0')
                ->comment('Solicitar ubicacion en ordenes de inspeccion de activos');
            $table->char('is_corp', 1)->nullable()->default('0')
                ->comment('0 No es corporativa / 1 Es corporativa');
            $table->json('erp_binding')->nullable()
                ->comment('JSON para la vinculacion de las cuentas de ERPhere');
            $table->char('con_licencia', 1)->nullable()->default('0');
            $table->date('next_payment_date')->nullable();
            $table->decimal('last_payment_amount', 12, 2)->nullable();
            $table->string('helpdesk_domain', 50)->nullable()
                ->comment('Dominio para solicitudes de Helpdesk');

            $table->unique('cod', 'empresas_cod_unique');
            $table->unique('slug', 'empresas_slug_unique');
            $table->index('stripe_id', 'empresas_stripe_id_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('empresas');
    }
};
