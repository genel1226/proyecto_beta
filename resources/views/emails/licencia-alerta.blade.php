<x-mail::message>
# {{ $tipo === 'vencida' ? 'Licencia vencida' : 'Licencia por vencer' }}

@if ($tipo === 'vencida')
La licencia **{{ $licencia->codigo_licencia }}** de **{{ $licencia->empresa->nombre_comercial }}** venció el {{ $licencia->fecha_vencimiento->format('d/m/Y') }}.

El acceso de la empresa al CMMS fue suspendido. Para reactivarlo, comunícate con Software4tech.
@else
La licencia **{{ $licencia->codigo_licencia }}** de **{{ $licencia->empresa->nombre_comercial }}** vence {{ $dias === 1 ? 'mañana' : 'en '.$dias.' días' }}, el {{ $licencia->fecha_vencimiento->format('d/m/Y') }}.

Para evitar la suspensión del acceso al CMMS, realiza el pago antes de esa fecha.
@endif

<x-mail::table>
| Dato | Detalle |
| :--- | :--- |
| Licencia | {{ $licencia->codigo_licencia }} |
| Empresa | {{ $licencia->empresa->nombre_comercial }} |
| Plan | {{ $licencia->plan->nombre }} |
| Vencimiento | {{ $licencia->fecha_vencimiento->format('d/m/Y') }} |
</x-mail::table>

Gracias,<br>
{{ config('app.name') }}
</x-mail::message>
