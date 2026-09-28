<?php

namespace App\Livewire\Licencias;

use App\Models\Empresa\Empresa;
use App\Models\Licencias\Licencias as ModelsLicencias;
use App\Models\Plan\Plan;
use App\Models\TipoUsuario\TipoUsuario;
use Flux\Flux;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Locked;
use Livewire\Attributes\On;
use Livewire\Component;

class Licencias extends Component
{
    // I have not failed. I've just found 10,000 ways that won't work. - Thomas Edison

    /**
     * Null = se está creando una licencia nueva.
     * Con valor = se está editando la licencia con ese id.
     *
     * #[Locked]: sin esto, cualquiera podría cambiar este id desde el
     * navegador y hacer que "Guardar cambios" edite OTRA licencia.
     */
    #[Locked]
    public ?int $licencia_id = null;

    public ?int $empresa_id = null;

    public ?int $plan_id = null;

    public string $estado_inicial = 'V';

    public string $start_date;

    public string $end_date = '';

    public string $periodicidad = 'M';

    /**
     * Sin tipo a propósito: si el vendedor borra el campo numérico, el
     * navegador manda '' y una propiedad `float` estricta lanzaría error.
     * Siempre se lee con (float) y se valida como numérico.
     */
    public $descuento = 0;

    public string $observaciones = '';

    public string $codigo_actual = '';

    #[Locked]
    public ?int $baja_id = null;

    public string $baja_codigo = '';

    /**
     * tipo_usuario_id => cantidad.
     *
     * SOLO cantidades. Los precios no viven aquí: todo lo que está en una
     * propiedad pública viaja al navegador y se puede leer en la pestaña
     * Network. Los precios se calculan en el servidor (tiposConPrecio()).
     *
     * @var array<int, int>
     */
    public array $cantidades = [];

    public function mount(): void
    {
        Gate::authorize('licenses.index');

        $this->start_date = now()->format('Y-m-d');
        $this->cargarCantidades();
    }

    /**
     * Arma $cantidades con los tipos de usuario activos (más los que ya
     * tenga la licencia, aunque hoy estén desactivados en el catálogo).
     * Sin licencia, todo queda en 0.
     */
    private function cargarCantidades(?ModelsLicencias $licencia = null): void
    {
        $enLicencia = $licencia
            ? $licencia->detalleUsuarios->pluck('cantidad', 'tipo_usuario_id')
            : collect();

        $this->cantidades = TipoUsuario::query()
            ->where(fn ($q) => $q->where('activo', 1)->orWhereIn('id', $enLicencia->keys()->all()))
            ->orderBy('id')
            ->pluck('id')
            ->mapWithKeys(fn ($id) => [$id => (int) ($enLicencia[$id] ?? 0)])
            ->all();
    }

    /**
     * Botón "+ Nueva licencia". Resetea todo por si el modal quedó con
     * datos de una edición anterior.
     */
    public function nuevo(): void
    {
        $this->reset(['licencia_id', 'codigo_actual', 'empresa_id', 'plan_id', 'estado_inicial', 'end_date', 'descuento', 'observaciones']);
        $this->start_date = now()->format('Y-m-d');
        $this->cargarCantidades();
        $this->resetErrorBag();
    }

    /**
     * Lo dispara LicenciasTabla cuando el usuario hace clic en "Editar".
     *
     * Este método se puede invocar desde el navegador aunque el botón
     * esté oculto, así que el permiso se valida aquí, en el servidor.
     */
    #[On('editar-licencia')]
    public function abrirEdicion(int $id): void
    {
        if (! $this->autorizar('licenses.edit')) {
            return;
        }

        $licencia = ModelsLicencias::with('detalleUsuarios')->findOrFail($id);

        if (in_array($licencia->estado, ['N', 'C'], true)) {
            Flux::toast(
                heading: 'No se puede editar',
                text: "La licencia {$licencia->codigo_licencia} está Vencida o Cancelada.",
                variant: 'warning',
            );

            return;
        }

        $this->licencia_id = $licencia->id;
        $this->empresa_id = $licencia->empresa_id;
        $this->plan_id = $licencia->plan_id;
        $this->start_date = $licencia->fecha_inicio->format('Y-m-d');
        $this->end_date = $licencia->fecha_vencimiento->format('Y-m-d');
        $this->periodicidad = $licencia->periodicidad;
        $this->codigo_actual = (string) $licencia->codigo_licencia;

        // Lo que el usuario no tiene permiso de ver NO se carga en
        // propiedades públicas (viajaría al navegador aunque no se pinte).
        $this->descuento = Gate::allows('licenses.descuento.ver') ? (float) $licencia->descuento : 0;
        $this->observaciones = Gate::allows('licenses.observaciones.ver') ? (string) $licencia->observaciones : '';

        $this->cargarCantidades($licencia);

        $this->resetErrorBag();
        $this->modal('nueva-licencia')->show();
    }

    public function incrementar(int $tipoId): void
    {
        if (array_key_exists($tipoId, $this->cantidades)) {
            $this->cantidades[$tipoId]++;
        }
    }

    public function decrementar(int $tipoId): void
    {
        if (array_key_exists($tipoId, $this->cantidades) && $this->cantidades[$tipoId] > 0) {
            $this->cantidades[$tipoId]--;
        }
    }

    public function getMinEndDateProperty(): string
    {
        return $this->start_date ?: now()->format('Y-m-d');
    }

    /**
     * Vista previa de cuál sería el próximo código (solo aplica cuando
     * se está creando; en edición se muestra el código ya existente).
     */
    public function getProximoCodigoProperty(): string
    {
        $siguienteId = (ModelsLicencias::max('id') ?? 0) + 1;

        return 'L'.str_pad((string) $siguienteId, 4, '0', STR_PAD_LEFT);
    }

    /**
     * Tipos de usuario con su precio efectivo y la cantidad elegida.
     *
     * Precio efectivo:
     *   - si el tipo ya estaba en la licencia que se edita, el precio con
     *     el que se vendió entonces (no el actual del catálogo);
     *   - si es nuevo, el precio actual del catálogo.
     *
     * Incluye los tipos activos y los que la licencia ya tenía, para que
     * al guardar una edición nunca se borre por error un tipo que quedó
     * desactivado en el catálogo.
     *
     * @return array<int, array{id:int, codigo:string, nombre:string, precio:float, cantidad:int}>
     */
    private function tiposConPrecio(): array
    {
        $aplicados = $this->licencia_id
            ? (ModelsLicencias::find($this->licencia_id)?->detalleUsuarios()->pluck('precio_unitario_aplicado', 'tipo_usuario_id') ?? collect())
            : collect();

        return TipoUsuario::query()
            ->where(fn ($q) => $q->where('activo', 1)->orWhereIn('id', $aplicados->keys()->all()))
            ->orderBy('id')
            ->get()
            ->map(fn (TipoUsuario $tipo) => [
                'id' => $tipo->id,
                'codigo' => $tipo->codigo,
                'nombre' => $tipo->nombre,
                'precio' => (float) ($aplicados[$tipo->id] ?? $tipo->precio_unitario),
                'cantidad' => (int) ($this->cantidades[$tipo->id] ?? 0),
            ])
            ->all();
    }

    /**
     * Único lugar donde se calcula el monto. La vista solo lo muestra.
     *
     * @param  array<int, array{id:int, codigo:string, nombre:string, precio:float, cantidad:int}>  $tipos
     * @return array{plan_nombre:?string, plan_monto:float, subtotal_usuarios:float, descuento:float, total:float, cantidad_total:int}
     */
    private function calcular(array $tipos): array
    {
        $plan = $this->plan_id ? Plan::find($this->plan_id) : null;
        $planMonto = (float) ($plan->monto ?? 0);
        $subtotal = (float) collect($tipos)->sum(fn (array $t) => $t['cantidad'] * $t['precio']);
        $descuento = $this->descuentoEfectivo();

        return [
            'plan_nombre' => $plan?->nombre,
            'plan_monto' => $planMonto,
            'subtotal_usuarios' => $subtotal,
            'descuento' => $descuento,
            'total' => max($planMonto + $subtotal - $descuento, 0),
            'cantidad_total' => (int) collect($tipos)->sum('cantidad'),
        ];
    }

    /**
     * Aplicar un descuento exige poder verlo: si pudiera aplicar sin ver,
     * el campo llegaría vacío y al guardar borraría el descuento existente.
     */
    private function puedeAplicarDescuento(): bool
    {
        return Gate::allows('licenses.descuento.aplicar') && Gate::allows('licenses.descuento.ver');
    }

    /**
     * El descuento que realmente cuenta para el cálculo.
     * Sin permiso para aplicarlo, se ignora lo que llegue del formulario:
     * en edición se conserva el que la licencia ya tenía; en creación, 0.
     */
    private function descuentoEfectivo(): float
    {
        if ($this->puedeAplicarDescuento()) {
            return max((float) $this->descuento, 0);
        }

        return $this->licencia_id
            ? (float) (ModelsLicencias::whereKey($this->licencia_id)->value('descuento') ?? 0)
            : 0.0;
    }

    public function guardar(): void
    {
        $editando = $this->licencia_id !== null;

        if (! $this->autorizar($editando ? 'licenses.edit' : 'licenses.create')) {
            return;
        }

        $reglas = [
            'empresa_id' => ['required', 'exists:empresas,id'],
            'plan_id' => ['required', 'exists:plans,id'],
            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date', 'after:start_date'],
            'periodicidad' => ['required', 'in:M,A,P'],
            'descuento' => ['nullable', 'numeric', 'min:0'],
        ];

        // En edición el estado no se toca aquí (ese campo ni se muestra),
        // así que solo se valida al crear. Antes se validaba siempre y una
        // licencia en 'X' (Por vencer) no se podía guardar sin ver ningún error.
        if (! $editando) {
            $reglas['estado_inicial'] = ['required', 'in:V,P'];
        }

        $this->validate($reglas);

        $licenciaActual = $editando ? ModelsLicencias::findOrFail($this->licencia_id) : null;

        if ($licenciaActual && in_array($licenciaActual->estado, ['N', 'C'], true)) {
            Flux::toast(
                heading: 'No se puede editar',
                text: 'Una licencia Vencida o Cancelada ya no se puede modificar.',
                variant: 'warning',
            );

            return;
        }

        // Estado "vivo" a validar: al crear, el que eligió el vendedor;
        // al editar, el que la licencia ya tiene.
        $estadoRelevante = $licenciaActual?->estado ?? $this->estado_inicial;

        $tipos = $this->tiposConPrecio();
        $calculo = $this->calcular($tipos);

        if (in_array($estadoRelevante, ['V', 'X'], true) && $calculo['cantidad_total'] < 1) {
            $this->addError('detalle', 'Una licencia Vigente o Por vencer necesita al menos un usuario.');

            return;
        }

        // En edición manda la empresa de la licencia, no lo que diga el formulario.
        $empresaId = $licenciaActual?->empresa_id ?? $this->empresa_id;

        $otraActiva = ModelsLicencias::where('empresa_id', $empresaId)
            ->whereIn('estado', ['V', 'X', 'P'])
            ->when($licenciaActual, fn ($q) => $q->where('id', '!=', $licenciaActual->id))
            ->exists();

        if ($otraActiva) {
            $this->addError('empresa_id', 'Esta empresa ya tiene otra licencia activa.');

            return;
        }

        // Todo o nada: si falla el detalle, no queda una licencia huérfana.
        DB::transaction(function () use ($licenciaActual, $tipos, $calculo, $empresaId) {
            $datos = [
                'plan_id' => $this->plan_id,
                'fecha_inicio' => $this->start_date,
                'fecha_vencimiento' => $this->end_date,
                'periodicidad' => $this->periodicidad,
                'descuento' => $calculo['descuento'],
                'monto' => $calculo['total'],
            ];

            // Sin permiso para ver observaciones, tampoco se escriben ni se pisan.
            if (Gate::allows('licenses.observaciones.ver')) {
                $datos['observaciones'] = $this->observaciones;
            }

            if ($licenciaActual) {
                $licenciaActual->update($datos);
                $licencia = $licenciaActual;
            } else {
                $licencia = ModelsLicencias::create($datos + [
                    'empresa_id' => $empresaId,
                    'moneda' => 'USD',
                    'estado' => $this->estado_inicial,
                    'vendedor_id' => Auth::id(),
                ]);

                // El código real se asigna DESPUÉS del insert, con el id que
                // MySQL ya le dio a esta fila: nunca puede chocar con otro.
                $licencia->codigo_licencia = 'L'.str_pad((string) $licencia->id, 4, '0', STR_PAD_LEFT);
                $licencia->save();
            }

            // Sincroniza el detalle (igual al crear que al editar): guarda o
            // actualiza los tipos con cantidad > 0 y borra los que quedaron en 0.
            $conservados = [];

            foreach ($tipos as $tipo) {
                if ($tipo['cantidad'] > 0) {
                    $fila = $licencia->detalleUsuarios()->updateOrCreate(
                        ['tipo_usuario_id' => $tipo['id']],
                        ['cantidad' => $tipo['cantidad'], 'precio_unitario_aplicado' => $tipo['precio']]
                    );
                    $conservados[] = $fila->id;
                }
            }

            $licencia->detalleUsuarios()->whereNotIn('id', $conservados)->delete();
        });

        $this->modal('nueva-licencia')->close();
        $this->dispatch('licencia-creada');

        $this->nuevo();
    }

    /**
     * Lo dispara LicenciasTabla al hacer clic en "Dar de baja".
     * Abre el modal de confirmación; el cambio real ocurre en darDeBaja().
     */
    #[On('confirmar-baja')]
    public function confirmarBaja(int $id): void
    {
        if (! $this->autorizar('licenses.baja')) {
            return;
        }

        $licencia = ModelsLicencias::findOrFail($id);

        if (! in_array($licencia->estado, ['V', 'X', 'P'], true)) {
            Flux::toast(
                heading: 'No se puede dar de baja',
                text: "La licencia {$licencia->codigo_licencia} ya está Vencida o Cancelada.",
                variant: 'warning',
            );

            return;
        }

        $this->baja_id = $licencia->id;
        $this->baja_codigo = (string) $licencia->codigo_licencia;

        $this->modal('confirmar-baja')->show();
    }

    public function darDeBaja(): void
    {
        if (! $this->baja_id) {
            return;
        }

        // Se vuelve a validar aquí: el permiso pudo cambiar mientras el modal
        // estaba abierto, o alguien pudo llamar este método directamente.
        if (! $this->autorizar('licenses.baja')) {
            $this->modal('confirmar-baja')->close();
            $this->reset(['baja_id', 'baja_codigo']);

            return;
        }

        $licencia = ModelsLicencias::findOrFail($this->baja_id);

        if (! in_array($licencia->estado, ['V', 'X', 'P'], true)) {
            $this->modal('confirmar-baja')->close();
            $this->reset(['baja_id', 'baja_codigo']);

            return;
        }

        $licencia->estado = 'C';
        $licencia->save();

        $codigo = $this->baja_codigo;

        $this->modal('confirmar-baja')->close();
        $this->reset(['baja_id', 'baja_codigo']);
        $this->dispatch('licencia-creada'); // refresca la tabla

        Flux::toast(
            heading: 'Licencia dada de baja',
            text: "La licencia {$codigo} se dio de baja correctamente.",
            variant: 'success',
        );
    }

    /**
     * true si el usuario tiene el permiso; si no, avisa con un toast y
     * devuelve false para que el método que llamó se detenga.
     */
    private function autorizar(string $permiso): bool
    {
        if (Gate::allows($permiso)) {
            return true;
        }

        Flux::toast(
            heading: 'Sin permiso',
            text: 'No tienes permiso para realizar esta acción.',
            variant: 'danger',
        );

        return false;
    }

    public function render()
    {
        $tipos = $this->tiposConPrecio();

        $verPrecios = Gate::allows('licenses.precios.ver');
        $verMonto = Gate::allows('licenses.monto.ver');
        $verDescuento = Gate::allows('licenses.descuento.ver');

        return view('livewire.licencias.licencias', [
            'empresas' => Empresa::orderBy('razon_social')->get(),
            'planes' => Plan::primarios()->orderBy('monto')->get(),
            'tipos' => $tipos,
            'calculo' => $this->calcular($tipos),

            // Qué puede ver / hacer este usuario dentro del formulario
            'verPrecios' => $verPrecios,
            'verMonto' => $verMonto,
            'verDescuento' => $verDescuento,
            'aplicarDescuento' => $this->puedeAplicarDescuento(),
            'verObservaciones' => Gate::allows('licenses.observaciones.ver'),
            'verResumen' => $verPrecios || $verMonto || $verDescuento,
        ]);
    }
}
