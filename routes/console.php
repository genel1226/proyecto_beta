<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');


// Suspende licencias vencidas, marca "Por vencer" y avisa del vencimiento.
// Corre justo después de medianoche (hora de APP_TIMEZONE).
Schedule::command('licencias:procesar --solo=vencimientos')
    ->dailyAt('00:05')
    ->withoutOverlapping();
 
// Avisos de 7 y 1 día antes. En horario de oficina para que los lean.
Schedule::command('licencias:procesar --solo=alertas')
    ->dailyAt('08:00')
    ->withoutOverlapping();

