<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Vendor;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules;

class RegisteredUserController extends Controller
{
    public function create(): \Illuminate\View\View
    {
        return view('auth.register');
    }

    public function store(Request $request): RedirectResponse
    {
        $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:' . User::class],
            'password' => ['required', 'confirmed', Rules\Password::defaults()],
            'account_type' => ['required', 'in:customer,vendor'],
            'company_name' => ['nullable', 'string', 'max:255'],
            'store_name' => ['required_if:account_type,vendor', 'nullable', 'string', 'max:255'],
            'terms' => ['accepted'],
        ], [
            'terms.accepted' => __('Vous devez accepter les conditions générales pour créer un compte.'),
        ]);

        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => Hash::make($request->password),
            'role' => $request->account_type,
            'company_name' => $request->company_name,
        ]);

        if ($request->account_type === 'vendor') {
            Vendor::create([
                'user_id' => $user->id,
                'store_name' => $request->store_name,
                'slug' => str($request->store_name)->slug() . '-' . $user->id,
                'country' => 'Bénin',
            ]);
        }

        event(new Registered($user));

        Auth::login($user);

        return redirect($user->isVendor() ? route('vendor.dashboard') : route('home'));
    }
}
