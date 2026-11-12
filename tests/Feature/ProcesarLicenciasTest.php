<?php

use App\Mail\LicenciaAlertaMail;
use App\Models\Empresa\Empresa;
use App\Models\Licencias\Licencias;
use App\Models\Plan\Plan;
use Illuminate\Support\Facades\Mail;

beforeEach(function () {
    $this->travelTo(now()->setDate(2026, 11, 12)->startOfDay()->addHours(10));
    Mail::fake();
});

function crearEmpresaDePrueba(): Empresa
{
    $sufijo = fake()->unique()->numerify('###');

    return Empresa::create([
        'cod' => 'E'.$sufijo,
        'razon_social' => 'Empresa '.$sufijo.' S.A.',
        'nombre_comercial' => 'Empresa '.$sufijo,
        'nit' => fake()->numerify('#########'),
        'slug' => 'empresa-'.$sufijo,
        'email' => fake()->unique()->safeEmail(),
        'active' => true,
    ]);
}

/**
 * @param  array<string, mixed>  $atributos
 */
function crearLicenciaDePrueba(array $atributos = []): Licencias
{
    $plan = Plan::firstOrCreate(['nombre' => 'Lite'], ['sigla' => 'L', 'monto' => 15, 'lapso' => 'M', 'tipo' => '1']);

    return Licencias::create([
        'empresa_id' => crearEmpresaDePrueba()->id,
        'plan_id' => $plan->id,
        'codigo_licencia' => 'LIC-'.fake()->unique()->numerify('####'),
        'fecha_inicio' => today()->subMonth(),
        'fecha_vencimiento' => today(),
        'monto' => 15,
        'estado' => 'V',
        ...$atributos,
    ]);
}

it('marca como vencida una licencia que venció ayer, suspende la empresa y envía un correo', function () {
    $licencia = crearLicenciaDePrueba(['fecha_vencimiento' => today()->subDay()]);

    $this->artisan('licencias:procesar', ['--solo' => 'vencimientos'])->assertSuccessful();

    expect($licencia->fresh()->estado)->toBe('N')
        ->and($licencia->empresa->fresh()->active)->toBeFalse();

    Mail::assertSentCount(1);
    Mail::assertSent(LicenciaAlertaMail::class, fn (LicenciaAlertaMail $correo) => $correo->tipo === 'vencida'
        && $correo->licencia->is($licencia)
        && $correo->hasTo($licencia->empresa->email));
});

it('marca como por vencer una licencia vigente que vence en 10 días', function () {
    $licencia = crearLicenciaDePrueba(['fecha_vencimiento' => today()->addDays(10)]);

    $this->artisan('licencias:procesar', ['--solo' => 'vencimientos'])->assertSuccessful();

    expect($licencia->fresh()->estado)->toBe('X')
        ->and($licencia->empresa->fresh()->active)->toBeTrue();

    Mail::assertNothingSent();
});

it('no marca como vencida una licencia que vence hoy', function () {
    $licencia = crearLicenciaDePrueba(['fecha_vencimiento' => today()]);

    $this->artisan('licencias:procesar', ['--solo' => 'vencimientos'])->assertSuccessful();

    expect($licencia->fresh()->estado)->not->toBe('N')
        ->and($licencia->empresa->fresh()->active)->toBeTrue();

    Mail::assertNotSent(LicenciaAlertaMail::class);
});
