@extends('layouts.app')

@section('title', __('Gestion des catégories'))

@section('content')
<div class="mx-auto max-w-4xl px-4 py-8 sm:px-6">
    <h1 class="mb-6 text-xl font-semibold text-ink">{{ __('Catégories') }}</h1>

    <form action="{{ route('admin.categories.store') }}" method="POST" class="card mb-6 flex flex-wrap items-end gap-4 p-4">
        @csrf
        <div>
            <label class="field-label">{{ __('Nom de la catégorie') }}</label>
            <input type="text" name="name" class="field-input" required>
        </div>
        <div>
            <label class="field-label">{{ __('Catégorie parente') }}</label>
            <select name="parent_id" class="field-input">
                <option value="">{{ __('Aucune (catégorie principale)') }}</option>
                @foreach($categories as $cat)
                    <option value="{{ $cat->id }}">{{ $cat->name }}</option>
                @endforeach
            </select>
        </div>
        <button type="submit" class="btn-primary">{{ __('Ajouter') }}</button>
    </form>

    <div class="overflow-hidden rounded-lg border border-line bg-white">
        <table class="w-full text-sm">
            <thead class="bg-navy-50 text-left text-navy-500">
                <tr>
                    <th class="px-4 py-3 font-medium">{{ __('Nom') }}</th>
                    <th class="px-4 py-3 font-medium">{{ __('Produits') }}</th>
                    <th class="px-4 py-3"></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-line">
                @foreach($categories as $cat)
                    <tr>
                        <td class="px-4 py-3 text-ink">{{ $cat->parent_id ? '— ' : '' }}{{ $cat->name }}</td>
                        <td class="px-4 py-3 text-navy-500">{{ $cat->products_count }}</td>
                        <td class="px-4 py-3 text-right">
                            <form action="{{ route('admin.categories.destroy', $cat) }}" method="POST" onsubmit="return confirm({{ Illuminate\Support\Js::from(__('Supprimer cette catégorie ?')) }});">
                                @csrf @method('DELETE')
                                <button type="submit" class="text-red-600 hover:underline">{{ __('Supprimer') }}</button>
                            </form>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</div>
@endsection
