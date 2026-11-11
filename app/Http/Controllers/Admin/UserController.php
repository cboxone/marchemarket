<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Mail\AccountSuspendedMail;
use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;

class UserController extends Controller
{
    public function index(Request $request)
    {
        $query = User::query()->withCount('orders');

        if ($role = $request->string('role')->toString()) {
            $query->where('role', $role);
        }

        if ($search = $request->string('q')->trim()->toString()) {
            $query->where(fn ($q) => $q->where('name', 'like', "%{$search}%")->orWhere('email', 'like', "%{$search}%"));
        }

        $users = $query->latest()->paginate(20)->withQueryString();

        return view('admin.users.index', [
            'users' => $users,
            'filters' => $request->only(['role', 'q']),
        ]);
    }

    public function toggleBan(Request $request, User $user)
    {
        abort_if($user->id === $request->user()->id, 403, __('Vous ne pouvez pas vous bannir vous-même.'));
        abort_if($user->isAdmin(), 403, __('Impossible de suspendre un compte administrateur.'));

        $user->update(['is_banned' => ! $user->is_banned]);

        AuditLog::record(
            $user->is_banned ? 'user.banned' : 'user.unbanned',
            ($user->is_banned ? 'Compte suspendu : ' : 'Compte réactivé : ') . $user->name . ' (' . $user->email . ')',
            $user
        );

        if ($user->is_banned) {
            Mail::to($user->email)->send(new AccountSuspendedMail($user));
        }

        return back()->with('status', $user->is_banned ? __('Compte suspendu.') : __('Compte réactivé.'));
    }
}
