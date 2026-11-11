<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Auth\Events\Lockout;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class AuthenticatedSessionController extends Controller
{
    /**
     * Nombre de tentatives de connexion autorisées avant blocage temporaire,
     * pour la même combinaison e-mail + adresse IP (protection anti
     * force-brute — voir ensureIsNotRateLimited() ci-dessous).
     */
    private const MAX_ATTEMPTS = 5;

    public function create(): \Illuminate\View\View
    {
        return view('auth.login');
    }

    public function store(Request $request): RedirectResponse
    {
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required'],
        ]);

        $this->ensureIsNotRateLimited($request);

        if (! Auth::attempt($credentials, $request->boolean('remember'))) {
            RateLimiter::hit($this->throttleKey($request));

            return back()->withErrors([
                'email' => __('Ces identifiants ne correspondent à aucun compte.'),
            ])->onlyInput('email');
        }

        RateLimiter::clear($this->throttleKey($request));

        if (Auth::user()->is_banned) {
            Auth::logout();

            return back()->withErrors([
                'email' => __("Ce compte a été suspendu. Contactez le support pour plus d'informations."),
            ])->onlyInput('email');
        }

        $request->session()->regenerate();

        $user = Auth::user();

        return redirect()->intended(
            $user->isAdmin() ? route('admin.dashboard') : ($user->isVendor() ? route('vendor.dashboard') : route('home'))
        );
    }

    public function destroy(Request $request): RedirectResponse
    {
        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect('/');
    }

    /**
     * Bloque temporairement les tentatives de connexion après plusieurs
     * échecs, pour la même combinaison e-mail + IP (et non par IP seule,
     * pour ne pas pénaliser tout un réseau partagé à cause d'un seul
     * utilisateur qui se trompe de mot de passe).
     */
    private function ensureIsNotRateLimited(Request $request): void
    {
        if (! RateLimiter::tooManyAttempts($this->throttleKey($request), self::MAX_ATTEMPTS)) {
            return;
        }

        event(new Lockout($request));

        $seconds = RateLimiter::availableIn($this->throttleKey($request));

        throw ValidationException::withMessages([
            'email' => __('Trop de tentatives de connexion. Merci de réessayer dans :seconds secondes.', ['seconds' => $seconds]),
        ]);
    }

    private function throttleKey(Request $request): string
    {
        return Str::transliterate(Str::lower($request->string('email')) . '|' . $request->ip());
    }
}
