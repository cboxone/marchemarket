@extends('layouts.app')

@section('title', __('Vérifications KYC'))

@section('content')
<div class="mx-auto max-w-5xl px-4 py-8 sm:px-6">
    <h1 class="mb-6 text-xl font-semibold text-ink">{{ __('Vérifications KYC') }}</h1>

    @if(session('status'))
        <div class="mb-6 rounded-md bg-trade-green/10 px-4 py-3 text-sm text-trade-green">{{ session('status') }}</div>
    @endif
    @if($errors->has('kyc'))
        <div class="mb-6 rounded-md bg-red-50 px-4 py-3 text-sm text-red-700">{{ $errors->first('kyc') }}</div>
    @endif

    @if($vendors->isEmpty())
        <div class="card p-10 text-center text-navy-500">{{ __('Aucun dossier en attente.') }}</div>
    @else
        <div class="overflow-x-auto rounded-lg border border-line bg-white">
            <table class="w-full text-sm">
                <thead class="bg-navy-50 text-left text-navy-500">
                    <tr>
                        <th class="px-4 py-3 font-medium">{{ __('Boutique') }}</th>
                        <th class="px-4 py-3 font-medium">{{ __('Contact') }}</th>
                        <th class="px-4 py-3 font-medium">{{ __('IFU') }}</th>
                        <th class="px-4 py-3 font-medium">{{ __('Envoyé le') }}</th>
                        <th class="px-4 py-3"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-line">
                    @foreach($vendors as $vendor)
                        <tr>
                            <td class="px-4 py-3 text-ink">{{ $vendor->store_name }}</td>
                            <td class="px-4 py-3 text-navy-500">{{ $vendor->user->email }}</td>
                            <td class="px-4 py-3 text-navy-500">{{ $vendor->tax_id }}</td>
                            <td class="px-4 py-3 text-navy-500">{{ $vendor->kyc_submitted_at->format('d/m/Y H:i') }}</td>
                            <td class="px-4 py-3 text-right"><a href="{{ route('admin.verifications.show', $vendor) }}" class="text-navy-700 hover:underline">{{ __('Examiner') }}</a></td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        <div class="mt-6">{{ $vendors->links() }}</div>
    @endif
</div>
@endsection
