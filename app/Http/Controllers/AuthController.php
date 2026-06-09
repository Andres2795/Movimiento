<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\View\View;

class AuthController extends Controller
{
    public function showLogin(): View
    {
        return view('auth.login');
    }

    public function login(Request $request): RedirectResponse
    {
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ], [
            'email.required' => 'Ingresa tu correo.',
            'email.email' => 'Ingresa un correo valido.',
            'password.required' => 'Ingresa tu contrasena.',
        ]);

        $this->ensureIsNotRateLimited($request);

        if (! Auth::attempt([
            'email' => $credentials['email'],
            'password' => $credentials['password'],
        ])) {
            RateLimiter::hit($this->throttleKey($request), 60);

            return back()
                ->withErrors(['email' => 'Las credenciales no coinciden o el usuario no tiene permisos de administrador.'])
                ->onlyInput('email');
        }

        $user = $request->user();

        if (! $user || $user->role !== 'administrador' || ! $user->is_active) {
            Auth::logout();

            RateLimiter::hit($this->throttleKey($request), 60);

            return back()
                ->withErrors(['email' => 'Las credenciales no coinciden o el usuario no tiene permisos de administrador.'])
                ->onlyInput('email');
        }

        RateLimiter::clear($this->throttleKey($request));
        $request->session()->regenerate();

        $user->forceFill([
            'last_login_at' => now(),
        ])->save();

        return redirect()->intended(route('documents.upload'));
    }

    public function logout(Request $request): RedirectResponse
    {
        Auth::logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login')->with('status', 'Sesion cerrada correctamente.');
    }

    private function ensureIsNotRateLimited(Request $request): void
    {
        if (! RateLimiter::tooManyAttempts($this->throttleKey($request), 5)) {
            return;
        }

        $seconds = RateLimiter::availableIn($this->throttleKey($request));

        abort(429, "Demasiados intentos. Intenta nuevamente en {$seconds} segundos.");
    }

    private function throttleKey(Request $request): string
    {
        return Str::lower((string) $request->input('email')).'|'.$request->ip();
    }
}
