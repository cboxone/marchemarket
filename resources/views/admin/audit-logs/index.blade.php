@extends('layouts.app')

@section('title', __("Journal d'audit"))

@section('content')
<div class="mx-auto max-w-6xl px-4 py-8 sm:px-6">
    <h1 class="mb-2 text-xl font-semibold text-ink">{{ __("Journal d'audit") }}</h1>
    <p class="mb-6 text-sm text-navy-500">{{ __('Historique en lecture seule des actions sensibles réalisées par les administrateurs.') }}</p>

    @if($logs->isEmpty())
        <div class="card p-10 text-center text-navy-500">{{ __('Aucune action enregistrée pour le moment.') }}</div>
    @else
        <div class="overflow-hidden rounded-lg border border-line bg-white">
            <table class="w-full text-sm">
                <thead class="bg-navy-50 text-left text-navy-500">
                    <tr>
                        <th class="px-4 py-3 font-medium">{{ __('Date') }}</th>
                        <th class="px-4 py-3 font-medium">{{ __('Administrateur') }}</th>
                        <th class="px-4 py-3 font-medium">{{ __('Action') }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-line">
                    @foreach($logs as $log)
                        <tr>
                            <td class="px-4 py-3 whitespace-nowrap text-navy-500">{{ $log->created_at->format('d/m/Y H:i') }}</td>
                            <td class="px-4 py-3 whitespace-nowrap text-navy-500">{{ $log->admin->name ?? '—' }}</td>
                            <td class="px-4 py-3 text-ink">{{ $log->description }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        <div class="mt-6">{{ $logs->links() }}</div>
    @endif
</div>
@endsection
