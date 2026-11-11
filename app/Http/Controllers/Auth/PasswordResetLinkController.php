<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Password;
use Illuminate\View\View;

class PasswordResetLinkController extends Controller
{
    public function create(): View
    {
        return view('auth.forgot-password');
    }

    public function store(Request $request): RedirectResponse
    {
        $request->validate(['email' => ['required', 'email']]);

        // On renvoie toujours le même message, que l'e-mail existe ou non,
        // pour ne pas laisser deviner quels e-mails sont inscrits.
        Password::sendResetLink($request->only('email'));

        return back()->with('status', __('Si cette adresse existe dans nos systèmes, un lien de réinitialisation vient de lui être envoyé.'));
    }
}
