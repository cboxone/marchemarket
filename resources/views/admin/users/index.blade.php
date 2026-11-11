@extends('layouts.app')

@section('title', __('Gestion des utilisateurs'))

@section('content')
<div class="mx-auto max-w-6xl px-4 py-8 sm:px-6">
    <h1 class="mb-6 text-xl font-semibold text-ink">{{ __('Utilisateurs') }}</h1>

    <form action="{{ route('admin.users.index') }}" method="GET" class="card mb-6 flex flex-wrap items-end gap-4 p-4">
        <div>
            <label class="field-label">{{ __('Recherche') }}</label>
            <input type="text" name="q" value="{{ $filters['q'] ?? '' }}" placeholder="{{ __('Nom ou e-mail') }}" class="field-input">
        </div>
        <div>
            <label class="field-label">{{ __('Rôle') }}</label>
            <select name="role" class="field-input">
                <option value="">{{ __('Tous les rôles') }}</option>
                <option value="customer" @selected(($filters['role'] ?? '') === 'customer')>{{ __('Acheteur') }}</option>
                <option value="vendor" @selected(($filters['role'] ?? '') === 'vendor')>{{ __('Vendeur') }}</option>
                <option value="admin" @selected(($filters['role'] ?? '') === 'admin')>Admin</option>
            </select>
        </div>
        <button type="submit" class="btn-dark">{{ __('Filtrer') }}</button>
    </form>

    <div class="overflow-hidden rounded-lg border border-line bg-white">
        <table class="w-full text-sm">
            <thead class="bg-navy-50 text-left text-navy-500">
                <tr>
                    <th class="px-4 py-3 font-medium">{{ __('Nom') }}</th>
                    <th class="px-4 py-3 font-medium">{{ __('E-mail') }}</th>
                    <th class="px-4 py-3 font-medium">{{ __('Rôle') }}</th>
                    <th class="px-4 py-3 font-medium">{{ __('Commandes') }}</th>
                    <th class="px-4 py-3 font-medium">{{ __('Statut') }}</th>
                    <th class="px-4 py-3"></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-line">
                @foreach($users as $user)
                    <tr>
                        <td class="px-4 py-3 text-ink">{{ $user->name }}</td>
                        <td class="px-4 py-3 text-navy-500">{{ $user->email }}</td>
                        <td class="px-4 py-3 text-navy-500">
                            @switch($user->role)
                                @case('admin') Admin @break
                                @case('vendor') {{ __('Vendeur') }} @break
                                @default {{ __('Acheteur') }}
                            @endswitch
                        </td>
                        <td class="px-4 py-3 text-navy-500">{{ $user->orders_count }}</td>
                        <td class="px-4 py-3">
                            @if($user->is_banned)
                                <span class="rounded-full bg-red-50 px-2.5 py-1 text-xs font-medium text-red-600">{{ __('Suspendu') }}</span>
                            @else
                                <span class="badge-trade">{{ __('Actif') }}</span>
                            @endif
                        </td>
                        <td class="px-4 py-3 text-right">
                            @unless($user->isAdmin())
                                @php
                                    $confirmMessage = $user->is_banned
                                        ? __('Réactiver ce compte ?')
                                        : __('Suspendre ce compte ?');
                                @endphp
                                <form action="{{ route('admin.users.toggle-ban', $user) }}" method="POST" onsubmit="return confirm({{ Illuminate\Support\Js::from($confirmMessage) }});">
                                    @csrf @method('PATCH')
                                    <button type="submit" class="{{ $user->is_banned ? 'text-navy-700' : 'text-red-600' }} hover:underline">
                                        {{ $user->is_banned ? __('Réactiver') : __('Suspendre') }}
                                    </button>
                                </form>
                            @endunless
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
    <div class="mt-6">{{ $users->links() }}</div>
</div>
@endsection
