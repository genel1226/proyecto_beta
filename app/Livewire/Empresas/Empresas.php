<?php

namespace App\Livewire\Empresas;

use App\Models\Empresa\Empresa;
use App\Support\Paises;
use Flux\Flux;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Locked;
use Livewire\Attributes\On;
use Livewire\Component;

class Empresas extends Component
{
    // Very little is needed to make a happy life. - Marcus Aurelius

    /**
     * Null = se está creando una empresa nueva.
     * Con valor = se está editando la empresa con ese id.
     *
     * #[Locked]: sin esto, cualquiera podría cambiar este id desde el
     * navegador y hacer que "Guardar cambios" edite OTRA empresa.
     */
    #[Locked]
    public ?int $empresa_id = null;

    public string $codigo_actual = '';

    public string $razon_social = '';

    public string $nombre_comercial = '';

    public string $nit = '';

    public string $email = '';

    public string $pais = '';

    public string $telefono = '';

    public string $pagina_web = '';

    public string $direccion = '';

    /**
     * Cuenta demo: empresa de prueba, sin licencia, con fecha de fin de la
     * prueba (se guarda en empresas.trial_ends_at). Al crearle su primera
     * licencia Vigente deja de ser demo (lo hace AccesoEmpresa::otorgar).
     */
    public bool $es_demo = false;

    public string $demo_hasta = '';

    /** Empresa que se está por activar o desactivar (modal de confirmación). */
    #[Locked]
    public ?int $estado_id = null;

    /** true = se va a reactivar; false = se va a desactivar. */
    #[Locked]
    public bool $estado_activar = false;

    public string $estado_empresa = '';

    public function mount(): void
    {
        Gate::authorize('empresas.index');
    }

    /**
     * Validación en tiempo real: se dispara cada vez que un campo con
     * wire:model.live/.blur cambia (ver el blade). Solo valida ESE campo,
     * no el formulario completo, para que el error aparezca al momento
     * sin esperar a "Guardar".
     */
    public function updated(string $property): void
    {
        if (array_key_exists($property, $this->rules())) {
            $this->validateOnly($property);
        }
    }

    /**
     * Nada de esto reemplaza la validación de guardar() (que sigue
     * revisando TODO antes de tocar la base de datos) — esto solo
     * decide si el botón se deja presionar o no.
     */
    public function getPuedeGuardarProperty(): bool
    {
        $completos = trim($this->razon_social) !== ''
            && trim($this->nombre_comercial) !== ''
            && trim($this->nit) !== ''
            && trim($this->email) !== ''
            && trim($this->pais) !== ''
            && (! $this->es_demo || $this->demo_hasta !== '');

        return $completos && $this->getErrorBag()->isEmpty();
    }

    protected function rules(): array
    {
        return [
            'razon_social' => ['required', 'string', 'max:191'],
            'nombre_comercial' => ['required', 'string', 'max:191'],
            'nit' => ['required', 'string', 'max:20', Rule::unique('empresas', 'nit')->ignore($this->empresa_id)],
            // 50 = tamaño de la columna. A este correo llegan las alertas de vencimiento.
            'email' => ['required', 'email', 'max:50'],
            'pais' => ['required', Rule::in(Paises::todos())],
            'telefono' => ['nullable', 'string', 'max:50'],
            'pagina_web' => ['nullable', 'string', 'max:60', 'regex:/^(https?:\/\/)?([\w-]+\.)+[\w-]{2,}(\/\S*)?$/i'],
            'direccion' => ['nullable', 'string', 'max:500'],
            'es_demo' => ['boolean'],
            'demo_hasta' => [Rule::requiredIf($this->es_demo), 'nullable', 'date'],
        ];
    }

    protected function messages(): array
    {
        return [
            'required' => 'El campo :attribute es obligatorio.',
            'email' => 'Escribe un correo válido.',
            'max' => 'El campo :attribute no puede superar los :max caracteres.',
            'unique' => 'Ya existe una empresa con ese :attribute.',
            'in' => 'Selecciona un país de la lista.',
            'pagina_web.regex' => 'Escribe una dirección válida, por ejemplo: www.empresa.com',
        ];
    }

    protected function validationAttributes(): array
    {
        return [
            'razon_social' => 'razón social',
            'nombre_comercial' => 'nombre comercial',
            'nit' => 'NIT',
            'email' => 'correo',
            'pais' => 'país',
            'telefono' => 'teléfono',
            'pagina_web' => 'página web',
            'direccion' => 'dirección',
            'demo_hasta' => 'fin de la prueba',
        ];
    }

    /**
     * Botón "+ Nueva empresa". Limpia el formulario por si el modal quedó
     * con datos de una edición anterior.
     */
    public function nuevo(): void
    {
        $this->reset([
            'empresa_id',
            'codigo_actual',
            'razon_social',
            'nombre_comercial',
            'nit',
            'email',
            'pais',
            'telefono',
            'pagina_web',
            'direccion',
            'es_demo',
            'demo_hasta',
        ]);
        $this->resetErrorBag();
    }

    /**
     * Lo dispara el botón de lápiz de la tabla. Se puede invocar desde el
     * navegador aunque el botón esté oculto, así que el permiso se valida aquí.
     */
    #[On('editar-empresa')]
    public function abrirEdicion(int $id): void
    {
        if (! $this->autorizar('empresas.edit')) {
            return;
        }

        $empresa = Empresa::findOrFail($id);

        $this->empresa_id = $empresa->id;
        $this->codigo_actual = (string) $empresa->cod;
        $this->razon_social = (string) $empresa->razon_social;
        $this->nombre_comercial = (string) $empresa->nombre_comercial;
        $this->nit = (string) $empresa->nit;
        $this->email = (string) $empresa->email;
        $this->pais = (string) $empresa->pais;
        $this->telefono = (string) $empresa->telefono;
        $this->pagina_web = (string) $empresa->pagina_web;
        $this->direccion = (string) $empresa->direccion;
        $this->es_demo = $empresa->trial_ends_at !== null;
        $this->demo_hasta = $empresa->trial_ends_at?->format('Y-m-d') ?? '';

        $this->resetErrorBag();
        $this->modal('nueva-empresa')->show();
    }

    /**
     * Al prender el interruptor de demo se propone una fecha de fin de
     * prueba (config('licencias.demo_dias'), por defecto 15 días).
     */
    public function updatedEsDemo(bool $valor): void
    {
        if ($valor && $this->demo_hasta === '') {
            $this->demo_hasta = now()->addDays((int) config('licencias.demo_dias'))->format('Y-m-d');
        }
    }

    /**
     * Vista previa del próximo código (E001, E002...). Solo se muestra al
     * crear; el código real se fija en guardar().
     */
    public function getProximoCodigoProperty(): string
    {
        return $this->formatearCodigo($this->siguienteNumero());
    }

    /**
     * Siguiente número libre para el código. Se calcula con el mayor
     * número que exista, no con un conteo de filas, así no se repite
     * aunque alguna vez se borre una empresa.
     */
    private function siguienteNumero(bool $bloquear = false): int
    {
        $consulta = Empresa::query()->whereRaw('cod REGEXP ?', ['^E[0-9]{3}$']);

        if ($bloquear) {
            $consulta->lockForUpdate();
        }

        return ((int) $consulta->max(DB::raw('CAST(SUBSTRING(cod, 2) AS UNSIGNED)'))) + 1;
    }

    private function formatearCodigo(int $numero): string
    {
        return 'E' . str_pad((string) $numero, 3, '0', STR_PAD_LEFT);
    }

    /**
     * Slug único a partir del nombre comercial: "mi-empresa", y si ya
     * existe, "mi-empresa-2", "mi-empresa-3"...
     */
    private function slugUnico(string $nombre): string
    {
        $base = Str::slug($nombre) ?: 'empresa';
        $candidato = $base;
        $n = 2;

        while (Empresa::where('slug', $candidato)->exists()) {
            $candidato = "{$base}-{$n}";
            $n++;
        }

        return $candidato;
    }

    public function guardar(): void
    {
        $editando = $this->empresa_id !== null;

        if (! $this->autorizar($editando ? 'empresas.edit' : 'empresas.create')) {
            return;
        }

        foreach (['razon_social', 'nombre_comercial', 'nit', 'email', 'pais', 'telefono', 'pagina_web', 'direccion'] as $campo) {
            $this->{$campo} = trim($this->{$campo});
        }

        $validados = $this->validate();

        $demo = (bool) $validados['es_demo'];
        $hastaDemo = $validados['demo_hasta'] ?? null;

        // Fin de la prueba al final de ese día; sin demo, se limpia.
        $trial = ($demo && $hastaDemo) ? Carbon::parse($hastaDemo)->endOfDay() : null;

        // Los opcionales vacíos se guardan como NULL, no como texto vacío.
        $datos = array_map(
            fn($valor) => $valor === '' ? null : $valor,
            Arr::except($validados, ['es_demo', 'demo_hasta'])
        );

        if ($editando) {
            $empresa = Empresa::findOrFail($this->empresa_id);

            // Una demo no tiene licencia: si ya tiene una activa, no puede serlo.
            if ($demo && $empresa->licenciaActiva()) {
                $this->addError('es_demo', 'Esta empresa ya tiene una licencia activa, no puede ser una cuenta demo.');

                return;
            }

            // El código y el slug no se tocan al editar: el slug puede estar
            // en uso en otras partes del CMMS y cambiarlo rompería enlaces.
            $empresa->update($datos + ['trial_ends_at' => $trial]);

            $mensaje = "La empresa {$empresa->cod} se actualizó correctamente.";
        } else {
            try {
                $empresa = DB::transaction(function () use ($datos, $trial) {
                    $numero = $this->siguienteNumero(bloquear: true);

                    // La columna es char(4): E001 hasta E999.
                    if ($numero > 999) {
                        return null;
                    }

                    return Empresa::create($datos + [
                        'cod' => $this->formatearCodigo($numero),
                        'slug' => $this->slugUnico($datos['nombre_comercial']),
                        'active' => true,
                        'trial_ends_at' => $trial,
                    ]);
                });
            } catch (UniqueConstraintViolationException) {
                Flux::toast(
                    heading: 'No se pudo guardar',
                    text: 'Otro usuario registró una empresa al mismo tiempo. Intenta de nuevo.',
                    variant: 'warning',
                );

                return;
            }

            if (! $empresa) {
                Flux::toast(
                    heading: 'Límite de códigos alcanzado',
                    text: 'Se llegó a E999, el máximo que admite la columna de código.',
                    variant: 'danger',
                );

                return;
            }

            $mensaje = "La empresa {$empresa->cod} se creó correctamente.";
        }

        $this->modal('nueva-empresa')->close();
        $this->dispatch('empresa-guardada'); // refresca la tabla

        Flux::toast(heading: 'Listo', text: $mensaje, variant: 'success');

        $this->nuevo();
    }

    /**
     * Lo dispara el botón de activar/desactivar de la tabla. Abre el modal
     * de confirmación; el cambio real ocurre en cambiarEstado().
     */
    #[On('confirmar-estado-empresa')]
    public function confirmarEstado(int $id): void
    {
        if (! $this->autorizar('empresas.desactivar')) {
            return;
        }

        $empresa = Empresa::findOrFail($id);

        if ($empresa->active && $licencia = $empresa->licenciaActiva()) {
            Flux::toast(
                heading: 'No se puede desactivar',
                text: "La empresa {$empresa->cod} tiene la licencia {$licencia->codigo_licencia} activa. Dala de baja primero.",
                variant: 'warning',
            );

            return;
        }

        $this->estado_id = $empresa->id;
        $this->estado_activar = ! $empresa->active;
        $this->estado_empresa = "{$empresa->cod} · {$empresa->nombre_comercial}";

        $this->modal('confirmar-estado-empresa')->show();
    }

    public function cambiarEstado(): void
    {
        if (! $this->estado_id) {
            return;
        }

        // Se valida otra vez aquí: el permiso pudo cambiar con el modal
        // abierto, o alguien pudo llamar este método directamente.
        if (! $this->autorizar('empresas.desactivar')) {
            $this->cerrarModalEstado();

            return;
        }

        $empresa = Empresa::findOrFail($this->estado_id);
        $activar = $this->estado_activar;

        if (! $activar && $licencia = $empresa->licenciaActiva()) {
            $this->cerrarModalEstado();

            Flux::toast(
                heading: 'No se puede desactivar',
                text: "La empresa {$empresa->cod} tiene la licencia {$licencia->codigo_licencia} activa. Dala de baja primero.",
                variant: 'warning',
            );

            return;
        }

        $empresa->active = $activar;
        $empresa->save();

        $this->cerrarModalEstado();
        $this->dispatch('empresa-guardada'); // refresca la tabla

        Flux::toast(
            heading: $activar ? 'Empresa reactivada' : 'Empresa desactivada',
            text: 'La empresa ' . $empresa->cod . ($activar ? ' se reactivó' : ' se desactivó') . ' correctamente.',
            variant: 'success',
        );
    }

    private function cerrarModalEstado(): void
    {
        $this->modal('confirmar-estado-empresa')->close();
        $this->reset(['estado_id', 'estado_activar', 'estado_empresa']);
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
        return view('livewire.empresas.empresas', [
            'paises' => Paises::todos(),
        ]);
    }
}
