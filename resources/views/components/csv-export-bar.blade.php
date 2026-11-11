@props(['route', 'label' => __('Exporter en CSV')])

<form action="{{ route($route) }}" method="GET" class="mb-6 flex flex-wrap items-end gap-3 rounded-lg border border-line bg-white p-4">
    <div>
        <label class="field-label" for="export-from">{{ __('Du') }}</label>
        <input type="date" id="export-from" name="from" value="{{ request('from') }}" class="field-input">
    </div>
    <div>
        <label class="field-label" for="export-to">{{ __('Au') }}</label>
        <input type="date" id="export-to" name="to" value="{{ request('to') }}" class="field-input">
    </div>
    <button type="submit" class="btn-secondary">{{ $label }}</button>
    <p class="basis-full text-xs text-navy-300">{{ __('Laissez les dates vides pour tout exporter.') }}</p>
</form>
