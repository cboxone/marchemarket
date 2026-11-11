@extends('layouts.app')

@section('title', __('Paiement de la commande'))

@section('content')
<div class="mx-auto max-w-xl px-4 py-12 sm:px-6">

    @if(session('status'))
        <div class="mb-6 rounded-md bg-trade-green/10 px-4 py-3 text-sm text-trade-green">{{ session('status') }}</div>
    @endif
    @if($errors->any())
        <div class="mb-6 rounded-md bg-red-50 px-4 py-3 text-sm text-red-700">
            @foreach($errors->all() as $e)<p>{{ $e }}</p>@endforeach
        </div>
    @endif

    <div class="card p-8 text-center">
        <h1 class="mb-2 font-display text-2xl font-semibold text-ink">{{ __('Régler la commande') }} {{ $order->reference }}</h1>
        <p class="mb-6 text-navy-500">{{ __('Montant à payer') }}</p>
        <p class="mb-8 font-display text-4xl font-semibold text-navy-800">
            {{ number_format((float) $order->total_amount, 0, ',', ' ') }} FCFA
        </p>

        @php($deadline = $order->unpaidCancelDeadline())
        @if($deadline)
            <p class="mb-4 text-xs text-navy-500">
                {{ __('Sans paiement avant :time, cette commande sera annulée automatiquement.', ['time' => $deadline->format('d/m/Y H:i')]) }}
            </p>
        @endif

        @if($sandbox)
            <p class="mb-4 rounded-md bg-amber-50 px-3 py-2 text-xs text-amber-700">
                {{ __('Mode test (sandbox) : aucun débit réel ne sera effectué.') }}
            </p>
        @endif

        <button
            id="pay-button"
            type="button"
            class="btn-primary w-full"
            onclick="openKkiapayWidget({
                amount: {{ (int) $order->total_amount }},
                api_key: {{ \Illuminate\Support\Js::from($kkiapayPublicKey) }},
                sandbox: {{ $sandbox ? 'true' : 'false' }},
                position: 'center',
                data: {{ \Illuminate\Support\Js::from($order->reference) }},
                phone: {{ \Illuminate\Support\Js::from($order->shipping_phone) }},
                paymentMethods: ['momo', 'card']
            })"
        >
            {{ __('Payer par Mobile Money ou carte') }}
        </button>

        {{-- Icônes génériques (pas les logos de marque officiels, pour rester
             libre de droits) : un téléphone pour le Mobile Money, une carte
             pour Visa/Mastercard. --}}
        <div class="mt-3 flex items-center justify-center gap-4 text-navy-300">
            <span class="flex items-center gap-1.5 text-xs">
                <svg viewBox="0 0 24 24" class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="1.8"><rect x="7" y="2" width="10" height="20" rx="2"/><line x1="7" y1="18" x2="17" y2="18"/></svg>
                {{ __('Mobile Money') }}
            </span>
            <span class="flex items-center gap-1.5 text-xs">
                <svg viewBox="0 0 24 24" class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="1.8"><rect x="2" y="5" width="20" height="14" rx="2"/><line x1="2" y1="10" x2="22" y2="10"/></svg>
                Visa · Mastercard
            </span>
        </div>

        <p class="mt-3 text-xs text-navy-300">{{ __('Paiement sécurisé par KkiaPay — MTN Mobile Money, Moov Money, carte bancaire.') }}</p>
        <div id="payment-error" class="mt-4 hidden rounded-md bg-red-50 px-3 py-2 text-sm text-red-700"></div>
    </div>

    @if($bankTransferAvailable)
        <div class="my-6 flex items-center gap-3 text-xs text-navy-300">
            <span class="h-px flex-1 bg-line"></span> {{ __('ou') }} <span class="h-px flex-1 bg-line"></span>
        </div>

        <div class="card p-8">
            @if(! $order->hasChosenBankTransfer())
                <h2 class="mb-2 text-base font-semibold text-ink">{{ __('Payer par virement bancaire') }}</h2>
                <p class="mb-4 text-sm text-navy-500">{{ __("Un virement prend parfois plusieurs jours ouvrés : votre commande ne sera pas annulée avant :hours heures dès que vous choisissez cette option.", ['hours' => config('marketplace.bank_transfer.window_hours')]) }}</p>
                <form action="{{ route('payment.bank-transfer.choose', $order) }}" method="POST">
                    @csrf
                    <button type="submit" class="btn-secondary w-full">{{ __('Je vais payer par virement bancaire') }}</button>
                </form>
            @elseif($order->bank_transfer_declared_at)
                <h2 class="mb-2 text-base font-semibold text-ink">{{ __('Virement en cours de vérification') }}</h2>
                <p class="text-sm text-navy-500">
                    {{ __('Preuve déposée le :date. Nous la comparons à notre relevé bancaire et vous confirmons par e-mail.', ['date' => $order->bank_transfer_declared_at->format('d/m/Y H:i')]) }}
                </p>
            @else
                <h2 class="mb-2 text-base font-semibold text-ink">{{ __('Virement bancaire') }}</h2>

                @if($order->bank_transfer_rejected_reason)
                    <div class="mb-4 rounded-md bg-red-50 px-3 py-2 text-sm text-red-700">
                        {{ __("Nous n'avons pas retrouvé votre précédent virement.") }}
                        {{ __('Motif :') }} {{ $order->bank_transfer_rejected_reason }}
                        {{ __('Vérifiez le montant et déposez une nouvelle preuve ci-dessous.') }}
                    </div>
                @endif

                <div class="mb-5 rounded-md border border-line bg-navy-50/40 p-4 text-left text-sm">
                    @if($bankTransfer['bank_name'])<p><span class="text-navy-300">{{ __('Banque') }} :</span> {{ $bankTransfer['bank_name'] }}</p>@endif
                    @if($bankTransfer['account_name'])<p><span class="text-navy-300">{{ __('Titulaire du compte') }} :</span> {{ $bankTransfer['account_name'] }}</p>@endif
                    @if($bankTransfer['account_number'])<p><span class="text-navy-300">{{ __('Numéro de compte') }} :</span> {{ $bankTransfer['account_number'] }}</p>@endif
                    @if($bankTransfer['iban'])<p><span class="text-navy-300">IBAN :</span> {{ $bankTransfer['iban'] }}</p>@endif
                    @if($bankTransfer['swift'])<p><span class="text-navy-300">{{ __('Code SWIFT/BIC') }} :</span> {{ $bankTransfer['swift'] }}</p>@endif
                    <p class="mt-2 font-medium text-ink">{{ __('Motif du virement à indiquer : :ref', ['ref' => $order->reference]) }}</p>
                </div>

                <form action="{{ route('payment.bank-transfer.declare', $order) }}" method="POST" enctype="multipart/form-data" class="space-y-4 text-left">
                    @csrf
                    <div>
                        <label class="field-label" for="reference">{{ __("Référence ou nom de l'expéditeur du virement (facultatif)") }}</label>
                        <input type="text" id="reference" name="reference" maxlength="120" value="{{ old('reference') }}" class="field-input">
                    </div>
                    <div>
                        <label class="field-label" for="proof">{{ __('Preuve du virement (capture ou reçu PDF)') }}</label>
                        <input type="file" id="proof" name="proof" accept="image/png,image/jpeg,image/webp,application/pdf" required class="field-input">
                    </div>
                    <button type="submit" class="btn-primary w-full">{{ __("J'ai effectué le virement") }}</button>
                </form>
            @endif
        </div>
    @endif
</div>

{{-- Widget KkiaPay --}}
<script src="https://cdn.kkiapay.me/k.js"></script>
<script>
    const paymentFailedMessage = @json(__('La vérification du paiement a échoué.'));
    const paymentCancelledMessage = @json(__('Le paiement a été annulé ou a échoué.'));

    addSuccessListener(function (response) {
        // Le widget confirme côté client — on revérifie systématiquement côté serveur
        // avant de considérer la commande comme payée.
        fetch('{{ route('payment.verify', $order) }}', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': '{{ csrf_token() }}',
                'Accept': 'application/json',
            },
            body: JSON.stringify({ transaction_id: response.transactionId }),
        })
            .then((res) => res.json().then((data) => ({ ok: res.ok, data })))
            .then(({ ok, data }) => {
                if (ok && data.ok) {
                    window.location.href = data.redirect;
                } else {
                    document.getElementById('payment-error').textContent = data.message || paymentFailedMessage;
                    document.getElementById('payment-error').classList.remove('hidden');
                }
            });
    });

    addFailedListener(function () {
        document.getElementById('payment-error').textContent = paymentCancelledMessage;
        document.getElementById('payment-error').classList.remove('hidden');
    });
</script>
@endsection
