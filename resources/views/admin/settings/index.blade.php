@extends('layouts.app')

@section('title', 'Paramètres — MarcheMarket')

@section('content')
<div class="mx-auto max-w-7xl px-4 py-8 sm:px-6">
    <x-breadcrumb>
        <x-breadcrumb-item href="{{ route('admin.dashboard') }}">Tableau de bord</x-breadcrumb-item>
        <x-breadcrumb-item active>Paramètres</x-breadcrumb-item>
    </x-breadcrumb>

    <h1 class="mt-6 text-2xl font-semibold text-ink">Paramètres du site</h1>

    @if(session('status'))
        <div class="mt-4 rounded-md border border-trade-green/30 bg-trade-greenLight px-4 py-3 text-sm text-trade-green">
            {{ session('status') }}
        </div>
    @endif

    <form action="{{ route('admin.settings.update') }}" method="POST" class="mt-6 space-y-8">
        @csrf

        @foreach($settings as $group => $groupSettings)
            <div class="card p-6">
                <h2 class="mb-4 text-lg font-semibold text-ink">{{ ucfirst($group) }}</h2>

                <div class="grid gap-6 md:grid-cols-2">
                    @foreach($groupSettings as $setting)
                        <div>
                            <label for="setting_{{ $setting->key }}" class="block text-sm font-medium text-ink">
                                {{ $setting->description ?? $setting->key }}
                            </label>

                            @if($setting->type === 'boolean')
                                <select
                                    id="setting_{{ $setting->key }}"
                                    name="settings[{{ $setting->key }}]"
                                    class="mt-1 w-full rounded-md border border-line bg-white px-3 py-2 text-sm focus:outline-none focus:ring-1 focus:ring-navy-500"
                                >
                                    <option value="1" {{ $setting->value ? 'selected' : '' }}>Oui</option>
                                    <option value="0" {{ !$setting->value ? 'selected' : '' }}>Non</option>
                                </select>

                            @elseif($setting->type === 'integer' || $setting->type === 'float')
                                <input
                                    type="number"
                                    id="setting_{{ $setting->key }}"
                                    name="settings[{{ $setting->key }}]"
                                    value="{{ $setting->value }}"
                                    step="{{ $setting->type === 'float' ? '0.01' : '1' }}"
                                    class="mt-1 w-full rounded-md border border-line bg-white px-3 py-2 text-sm focus:outline-none focus:ring-1 focus:ring-navy-500"
                                >

                            @elseif($setting->type === 'json')
                                <textarea
                                    id="setting_{{ $setting->key }}"
                                    name="settings[{{ $setting->key }}]"
                                    rows="4"
                                    class="mt-1 w-full rounded-md border border-line bg-white px-3 py-2 text-sm font-mono focus:outline-none focus:ring-1 focus:ring-navy-500"
                                >{{ is_array($setting->value) ? json_encode($setting->value, JSON_PRETTY_PRINT) : $setting->value }}</textarea>

                            @else
                                <input
                                    type="text"
                                    id="setting_{{ $setting->key }}"
                                    name="settings[{{ $setting->key }}]"
                                    value="{{ $setting->value }}"
                                    class="mt-1 w-full rounded-md border border-line bg-white px-3 py-2 text-sm focus:outline-none focus:ring-1 focus:ring-navy-500"
                                >
                            @endif
                        </div>
                    @endforeach
                </div>
            </div>
        @endforeach

        <div class="flex justify-end">
            <button type="submit" class="btn-primary">
                Enregistrer les modifications
            </button>
        </div>
    </form>
</div>
@endsection
