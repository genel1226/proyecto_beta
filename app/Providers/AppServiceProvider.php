<?php

namespace App\Providers;

use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureDefaults();
        $this->configurePermisos();
    }

    /**
     * Configure default behaviors for production-ready applications.
     */
    protected function configureDefaults(): void
    {
        Date::use(CarbonImmutable::class);

        DB::prohibitDestructiveCommands(
            app()->isProduction(),
        );

        Password::defaults(fn (): ?Password => app()->isProduction()
            ? Password::min(12)
                ->mixedCase()
                ->letters()
                ->numbers()
                ->symbols()
                ->uncompromised()
            : null,
        );
    }

    /**
     * Un solo punto de chequeo para todos los permisos del sistema.
     *
     * Con esto funcionan, sin definir cada habilidad a mano:
     *   - @can('licenses.create') en las vistas Blade
     *   - Gate::allows('licenses.baja') / Gate::authorize(...) en el código
     *   - ->middleware('can:licenses.index') en las rutas
     *
     * Devuelve true si el usuario tiene el permiso; si no, devuelve null
     * (no false) para que Laravel siga con su flujo normal y las demás
     * habilidades o policies del framework no se vean afectadas.
     */
    protected function configurePermisos(): void
    {
        Gate::before(function (User $user, string $ability) {
            return $user->tienePermiso($ability) ?: null;
        });
    }
}
