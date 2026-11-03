<?php

namespace App\Providers;

use App\Actions\Fortify\CreateNewUser;
use App\Actions\Fortify\ResetUserPassword;
use App\Models\User;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Laravel\Fortify\Fortify;
use Laravel\Fortify\LoginRateLimiter;

class FortifyServiceProvider extends ServiceProvider
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
        $this->configureActions();
        $this->configureAuthentication();
        $this->configureViews();
        $this->configureRateLimiting();
    }

    /**
     * Configure Fortify actions.
     */
    private function configureActions(): void
    {
        Fortify::resetUserPasswordsUsing(ResetUserPassword::class);
        Fortify::createUsersUsing(CreateNewUser::class);
    }

    /**
     * Inicio de sesión con el nombre de usuario (users.username) y mensajes que dicen qué falló:
     * usuario inexistente, usuario inactivo o contraseña incorrecta.
     *
     * Nota: decir cuál de los dos datos falló permite saber qué usuarios existen. Si algún día se
     * prefiere un solo mensaje, basta usar el mismo texto en las tres excepciones.
     */
    private function configureAuthentication(): void
    {
        Fortify::authenticateUsing(function (Request $request) {
            $request->validate([
                'username' => ['required', 'string'],
                'password' => ['required', 'string'],
            ], [
                'username.required' => 'Ingresa tu nombre de usuario.',
                'password.required' => 'Ingresa tu contraseña.',
            ]);

            // Cada intento fallido cuenta para el límite de intentos (si no, esta función lo saltaría).
            $limiter = app(LoginRateLimiter::class);

            $user = User::where('username', trim((string) $request->input('username')))->first();

            if (! $user) {
                $limiter->increment($request);

                throw ValidationException::withMessages(['username' => 'El usuario no existe.']);
            }

            // users.active: 0 = inactivo, 2 = eliminado; 1 = activo (6 y 7 son cuentas especiales).
            if (! in_array((int) $user->active, [1, 6, 7], true)) {
                $limiter->increment($request);

                throw ValidationException::withMessages([
                    'username' => 'El usuario está inactivo. Contacta al administrador.',
                ]);
            }

            if (! Hash::check((string) $request->input('password'), $user->password)) {
                $limiter->increment($request);

                throw ValidationException::withMessages(['password' => 'La contraseña es incorrecta.']);
            }

            return $user;
        });
    }

    /**
     * Configure Fortify views.
     */
    private function configureViews(): void
    {
        Fortify::loginView(fn() => view('pages::auth.login'));
        Fortify::verifyEmailView(fn() => view('pages::auth.verify-email'));
        Fortify::twoFactorChallengeView(fn() => view('pages::auth.two-factor-challenge'));
        Fortify::confirmPasswordView(fn() => view('pages::auth.confirm-password'));
        Fortify::registerView(fn() => view('pages::auth.register'));
        Fortify::resetPasswordView(fn() => view('pages::auth.reset-password'));
        Fortify::requestPasswordResetLinkView(fn() => view('pages::auth.forgot-password'));
    }

    /**
     * Configure rate limiting.
     */
    private function configureRateLimiting(): void
    {
        RateLimiter::for('two-factor', function (Request $request) {
            return Limit::perMinute(5)->by($request->session()->get('login.id'));
        });

        RateLimiter::for('login', function (Request $request) {
            $throttleKey = Str::transliterate(Str::lower($request->input(Fortify::username())) . '|' . $request->ip());

            return Limit::perMinute(5)->by($throttleKey);
        });

        RateLimiter::for('passkeys', function (Request $request) {
            $credentialId = $request->input('credential.id');

            return Limit::perMinute(10)->by(
                ($credentialId ?: $request->session()->getId()) . '|' . $request->ip(),
            );
        });
    }
}
