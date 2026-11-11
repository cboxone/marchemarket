@extends('layouts.app')

@section('title', __('Importer des produits'))

@section('content')
<div class="mx-auto max-w-2xl px-4 py-8 sm:px-6">
    <h1 class="mb-2 text-xl font-semibold text-ink">{{ __('Importer des produits en masse') }}</h1>
    <p class="mb-6 text-sm text-navy-500">
        {{ __('Publiez plusieurs produits en une seule fois à partir d\'un fichier CSV, plutôt que de remplir le formulaire un par un.') }}
    </p>

    @if(session('import_errors') && count(session('import_errors')) > 0)
        <div class="mb-6 rounded-md border border-red-200 bg-red-50 p-4 text-sm text-red-700">
            <p class="mb-2 font-medium">{{ __('Certaines lignes n\'ont pas pu être importées :') }}</p>
            <ul class="list-inside list-disc space-y-1">
                @foreach(session('import_errors') as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <div class="card mb-6 p-5">
        <h2 class="mb-2 text-sm font-semibold text-ink">{{ __('1. Téléchargez le modèle') }}</h2>
        <p class="mb-3 text-sm text-navy-500">
            {{ __('Le fichier doit contenir les colonnes suivantes, dans cet ordre : nom, catégorie (doit correspondre exactement à une catégorie existante), unité, quantité minimale, stock, prix de base, description (facultative).') }}
        </p>
        <a href="{{ route('vendor.products.import-template') }}" class="btn-secondary">{{ __('Télécharger le modèle CSV') }}</a>
    </div>

    <div class="card p-5">
        <h2 class="mb-3 text-sm font-semibold text-ink">{{ __('2. Envoyez votre fichier rempli') }}</h2>
        <form action="{{ route('vendor.products.import-store') }}" method="POST" enctype="multipart/form-data">
            @csrf
            <input type="file" name="file" accept=".csv,text/csv" class="field-input mb-4" required>
            <button type="submit" class="btn-primary">{{ __('Importer') }}</button>
        </form>
    </div>

    <p class="mt-4 text-xs text-navy-300">
        {{ __('Chaque produit importé reçoit un unique palier de prix (le prix de base). Vous pourrez ajouter des paliers dégressifs, une promotion ou des photos ensuite, en modifiant chaque produit individuellement.') }}
    </p>
</div>
@endsection
