@extends('layouts.app')

@section('title', __('Modifier') . ' ' . $product->name)

@section('content')
<div class="mx-auto max-w-3xl px-4 py-8 sm:px-6">
    <h1 class="mb-6 text-xl font-semibold text-ink">{{ __('Modifier') }} « {{ $product->name }} »</h1>
    <form action="{{ route('vendor.products.update', $product) }}" method="POST" enctype="multipart/form-data" class="card p-6">
        @include('vendor.products._form')
    </form>
</div>
@endsection
