<?php

return [

    /*
    | Días antes del vencimiento en que se avisa por correo.
    | (Además de estos, se manda un aviso cuando la licencia vence.)
    */
    'dias_alerta' => [7, 1],

    /*
    | Desde cuántos días antes del vencimiento la licencia pasa a
    | "Por vencer" (estado X).
    */
    'dias_por_vencer' => 15,

    /*
    | Correo interno de Software4tech. Recibe copia oculta de cada alerta.
    */
    'correo_interno' => env('LICENCIAS_CORREO_INTERNO'),

    /*
    | Duración por defecto de una cuenta demo, en días.
    */
    'demo_dias' => 15,

    /*
    | Si es true, el cron también desactiva las empresas demo cuyo fin de
    | prueba ya pasó. Por defecto está apagado: desactivar una empresa
    | corta su acceso al CMMS, así que conviene encenderlo a propósito.
    */
    'desactivar_demos_vencidas' => env('LICENCIAS_DESACTIVAR_DEMOS', false),

];
