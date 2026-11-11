<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', 'MarcheMarket') — {{ __('Le marché de gros pour professionnels') }}</title>
    <meta name="description" content="@yield('meta_description', __("MarcheMarket connecte les acheteurs professionnels aux fournisseurs vérifiés d'Afrique de l'Ouest, avec prix dégressifs par quantité."))">
    <link rel="canonical" href="{{ url()->current() }}">

    {{-- Open Graph / Twitter Card — chaque page peut surcharger ces valeurs
         via @section('og_title'), @section('og_description'), @section('og_image'). --}}
    <meta property="og:site_name" content="MarcheMarket">
    <meta property="og:type" content="website">
    <meta property="og:url" content="{{ url()->current() }}">
    <meta property="og:title" content="@hasSection('og_title')@yield('og_title')@else@yield('title', 'MarcheMarket')@endif">
    <meta property="og:description" content="@yield('og_description', __("MarcheMarket connecte les acheteurs professionnels aux fournisseurs vérifiés d'Afrique de l'Ouest, avec prix dégressifs par quantité."))">
    @hasSection('og_image')
        <meta property="og:image" content="@yield('og_image')">
    @endif
    <meta name="twitter:card" content="summary_large_image">

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Space+Grotesk:wght@500;600;700&family=Inter:wght@400;500;600&display=swap" rel="stylesheet">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    {{-- Applique le thème mémorisé avant le premier rendu, pour éviter le flash clair→sombre --}}
    <script>
        if (localStorage.getItem('theme') === 'dark' || (!localStorage.getItem('theme') && window.matchMedia('(prefers-color-scheme: dark)').matches)) {
            document.documentElement.classList.add('dark');
        }
    </script>
</head>
<body class="flex min-h-screen flex-col bg-paper transition-colors">

    {{-- Barre utilitaire --}}
    <div class="bg-navy-900 text-navy-100">
        <div class="mx-auto flex max-w-7xl flex-wrap items-center justify-between gap-2 px-4 py-1.5 text-xs sm:px-6">
            <p>{{ __('Livraison dans tout le Bénin et la sous-région') }}</p>
            <div class="flex items-center gap-4">
                @auth
                    @if(auth()->user()->isVendor())
                        <a href="{{ route('vendor.dashboard') }}" class="hover:text-white">{{ __('Espace vendeur') }}</a>
                        <a href="{{ route('vendor.messages.index') }}" class="hover:text-white">
                            {{ __('Messages') }}
                            <span id="unread-messages-badge" class="ml-1 rounded-full bg-amber-500 px-1.5 text-white {{ auth()->user()->unreadMessagesCount() > 0 ? '' : 'hidden' }}">{{ auth()->user()->unreadMessagesCount() }}</span>
                        </a>
                    @elseif(auth()->user()->isAdmin())
                        <a href="{{ route('admin.dashboard') }}" class="hover:text-white">{{ __('Back-office') }}</a>
                    @else
                        <a href="{{ route('orders.index') }}" class="hover:text-white">{{ __('Mes commandes') }}</a>
                        <a href="{{ route('quotes.index') }}" class="hover:text-white">{{ __('Mes devis') }}</a>
                        <a href="{{ route('disputes.index') }}" class="hover:text-white">{{ __('Mes litiges') }}</a>
                        <a href="{{ route('addresses.index') }}" class="hover:text-white">{{ __('Mes adresses') }}</a>
                        <a href="{{ route('profile.edit') }}" class="hover:text-white">{{ __('Mon profil') }}</a>
                        <a href="{{ route('wishlist.index') }}" class="hover:text-white">{{ __('Favoris') }}</a>
                        <a href="{{ route('messages.index') }}" class="hover:text-white">
                            {{ __('Messages') }}
                            <span id="unread-messages-badge" class="ml-1 rounded-full bg-amber-500 px-1.5 text-white {{ auth()->user()->unreadMessagesCount() > 0 ? '' : 'hidden' }}">{{ auth()->user()->unreadMessagesCount() }}</span>
                        </a>
                    @endif
                @else
                    <a href="{{ route('login') }}" class="hover:text-white">{{ __('Connexion') }}</a>
                    <a href="{{ route('register') }}" class="hover:text-white">{{ __('Devenir vendeur') }}</a>
                @endauth

                {{-- Sélecteur de langue --}}
                <form action="{{ route('locale.update', app()->getLocale() === 'fr' ? 'en' : 'fr') }}" method="POST">
                    @csrf
                    <button type="submit" class="flex items-center gap-1 rounded border border-navy-700 px-2 py-0.5 hover:border-navy-500 hover:text-white">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-3.5 w-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M12 21a9 9 0 100-18 9 9 0 000 18zM3.6 9h16.8M3.6 15h16.8M12 3a15 15 0 010 18 15 15 0 010-18z"/></svg>
                        {{ app()->getLocale() === 'fr' ? 'EN' : 'FR' }}
                    </button>
                </form>

                {{-- Bascule thème clair / sombre --}}
                <button
                    type="button"
                    id="theme-toggle"
                    class="flex h-6 w-6 items-center justify-center rounded border border-navy-700 hover:border-navy-500 hover:text-white"
                    aria-label="{{ __('Changer de thème') }}"
                >
                    <svg id="theme-icon-light" xmlns="http://www.w3.org/2000/svg" class="hidden h-3.5 w-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 3v1.5m0 15V21m9-9h-1.5m-15 0H3m15.36-6.36l-1.06 1.06M6.7 17.3l-1.06 1.06m12.72 0l-1.06-1.06M6.7 6.7L5.64 5.64M16.5 12a4.5 4.5 0 11-9 0 4.5 4.5 0 019 0z"/></svg>
                    <svg id="theme-icon-dark" xmlns="http://www.w3.org/2000/svg" class="h-3.5 w-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M21 12.79A9 9 0 1111.21 3 7 7 0 0021 12.79z"/></svg>
                </button>
            </div>
        </div>
    </div>

    {{-- En-tête principal --}}
    <header class="border-b border-line bg-white transition-colors">
        <div class="mx-auto flex max-w-7xl flex-wrap items-center gap-4 px-4 py-4 sm:px-6">
            <a href="{{ route('home') }}" class="font-display text-2xl font-700 text-navy-800">
                Marche<span class="text-amber-500">Market</span>
            </a>

            <form action="{{ route('products.index') }}" method="GET" class="relative order-3 flex w-full flex-1 items-stretch sm:order-none sm:w-auto" autocomplete="off">
                <input
                    type="text"
                    name="q"
                    id="search-input"
                    value="{{ request('q') }}"
                    placeholder="{{ __('Rechercher un produit, une référence, un fournisseur…') }}"
                    class="w-full rounded-l-md border border-r-0 border-line bg-white px-4 py-2.5 text-sm text-ink focus:outline-none focus:ring-1 focus:ring-navy-500"
                >
                <button type="submit" class="rounded-r-md bg-navy-800 px-5 text-sm font-semibold text-white hover:bg-navy-700">
                    {{ __('Rechercher') }}
                </button>
                <div id="search-suggestions" class="absolute left-0 right-0 top-full z-20 mt-1 hidden rounded-md border border-line bg-white shadow-card"></div>
            </form>

            <div class="ml-auto flex items-center gap-5">
                @auth
                    @if(auth()->user()->isCustomer())
                        <a href="{{ route('cart.index') }}" class="relative flex items-center gap-1.5 text-sm font-medium text-ink hover:text-navy-700">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M2.25 3h1.386c.51 0 .955.343 1.087.835l.383 1.437M7.5 14.25a3 3 0 00-3 3h15.75m-12.75-3h11.218c1.121-2.3 1.977-4.694 2.53-7.152.107-.478-.267-.898-.752-.898H5.106M7.5 14.25L5.106 5.272M7.5 14.25L5.25 20.25m11.25-6l2.25 6m-8.25-6a1.5 1.5 0 100 3 1.5 1.5 0 000-3zm7.5 0a1.5 1.5 0 100 3 1.5 1.5 0 000-3z"/></svg>
                            {{ __('Panier') }}
                            <span id="cart-count-badge" class="absolute -right-2 -top-2 flex h-5 w-5 items-center justify-center rounded-full bg-amber-500 text-xs font-bold text-white {{ auth()->user()->cart?->items?->count() > 0 ? '' : 'hidden' }}">
                                {{ auth()->user()->cart?->items?->count() ?? 0 }}
                            </span>
                        </a>
                    @endif
                    <form action="{{ route('logout') }}" method="POST">
                        @csrf
                        <button type="submit" class="text-sm font-medium text-ink hover:text-navy-700">{{ __('Déconnexion') }}</button>
                    </form>
                @else
                    <a href="{{ route('login') }}" class="btn-secondary">{{ __('Connexion') }}</a>
                @endauth
            </div>
        </div>
    </header>

    {{-- Barre de catégories --}}
    <nav class="bg-navy-800">
        <div class="mx-auto flex max-w-7xl items-center gap-6 overflow-x-auto px-4 py-2.5 text-sm text-navy-100 sm:px-6">
            <a href="{{ route('products.index') }}" class="flex shrink-0 items-center gap-1.5 font-semibold {{ request()->is('produits') && !request('categorie') ? 'text-amber-400' : 'text-white' }} hover:text-amber-300">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M3.75 6.75h16.5M3.75 12h16.5M3.75 17.25h16.5"/></svg>
                {{ __('Toutes les catégories') }}
            </a>
            <a href="{{ route('packs.index') }}" class="shrink-0 font-semibold {{ request()->is('packs') ? 'text-amber-400' : 'text-navy-100' }} hover:text-amber-300">{{ __('Packs & bons plans') }}</a>
            @foreach(\App\Models\Category::topLevel()->take(8) as $cat)
                <a href="{{ route('products.index', ['categorie' => $cat->slug]) }}" class="shrink-0 {{ request('categorie') === $cat->slug ? 'text-amber-400 font-semibold' : 'text-navy-100' }} hover:text-white">{{ $cat->name }}</a>
            @endforeach
        </div>
    </nav>

    <main class="flex-1">
        @if(session('status'))
            <div class="mx-auto mt-4 max-w-7xl px-4 sm:px-6">
                <div class="rounded-md border border-trade-green/30 bg-trade-greenLight px-4 py-3 text-sm text-trade-green">
                    {{ session('status') }}
                </div>
            </div>
        @endif

        @if ($errors->any())
            <div class="mx-auto mt-4 max-w-7xl px-4 sm:px-6">
                <div class="rounded-md border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">
                    <ul class="list-inside list-disc space-y-0.5">
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            </div>
        @endif

        @yield('content')
    </main>

    {{-- Container pour les notifications toast --}}
    <div id="toast-container" class="fixed bottom-4 right-4 z-50 flex flex-col gap-2"></div>

    {{-- Pied de page --}}
    <footer class="mt-16 bg-navy-900 text-navy-100">
        <div class="mx-auto grid max-w-7xl grid-cols-2 gap-8 px-4 py-12 sm:grid-cols-5 sm:px-6">
            <div>
                <h3 class="mb-3 font-display text-lg font-semibold text-white">MarcheMarket</h3>
                <p class="text-sm text-navy-300">{{ __("La marketplace de gros qui relie les fournisseurs vérifiés d'Afrique de l'Ouest aux acheteurs professionnels.") }}</p>
            </div>
            <div>
                <h4 class="mb-3 text-sm font-semibold text-white">{{ __('Acheteurs') }}</h4>
                <ul class="space-y-2 text-sm text-navy-300">
                    <li><a href="{{ route('products.index') }}" class="hover:text-white">{{ __('Parcourir le catalogue') }}</a></li>
                    <li><a href="{{ route('register') }}" class="hover:text-white">{{ __('Créer un compte') }}</a></li>
                </ul>
            </div>
            <div>
                <h4 class="mb-3 text-sm font-semibold text-white">{{ __('Vendeurs') }}</h4>
                <ul class="space-y-2 text-sm text-navy-300">
                    <li><a href="{{ route('register') }}" class="hover:text-white">{{ __('Devenir fournisseur') }}</a></li>
                    <li><span>{{ __('Garantie commerciale disponible') }}</span></li>
                </ul>
            </div>
            <div>
                <h4 class="mb-3 text-sm font-semibold text-white">{{ __('Confiance') }}</h4>
                <ul class="space-y-2 text-sm text-navy-300">
                    <li>{{ __('Fournisseurs vérifiés') }}</li>
                    <li>{{ __('Garantie commerciale (Trade Assurance)') }}</li>
                </ul>
            </div>
            <div>
                <h4 class="mb-3 text-sm font-semibold text-white">{{ __('Légal') }}</h4>
                <ul class="space-y-2 text-sm text-navy-300">
                    <li><a href="{{ route('legal.terms') }}" class="hover:text-white">{{ __("Conditions générales") }}</a></li>
                    <li><a href="{{ route('legal.privacy') }}" class="hover:text-white">{{ __('Confidentialité') }}</a></li>
                    <li><a href="{{ route('legal.notice') }}" class="hover:text-white">{{ __('Mentions légales') }}</a></li>
                </ul>
            </div>
        </div>
        <div class="border-t border-navy-700 py-4 text-center text-xs text-navy-300">
            © {{ date('Y') }} MarcheMarket — {{ __('Projet de démonstration.') }}
        </div>
    </footer>

    <script>
        const themeToggle = document.getElementById('theme-toggle');
        const iconLight = document.getElementById('theme-icon-light');
        const iconDark = document.getElementById('theme-icon-dark');

        function syncThemeIcon() {
            const isDark = document.documentElement.classList.contains('dark');
            iconLight.classList.toggle('hidden', !isDark);
            iconDark.classList.toggle('hidden', isDark);
        }
        syncThemeIcon();

        themeToggle.addEventListener('click', () => {
            document.documentElement.classList.toggle('dark');
            localStorage.setItem('theme', document.documentElement.classList.contains('dark') ? 'dark' : 'light');
            syncThemeIcon();
        });
    </script>

    {{-- Autocomplétion de la recherche --}}
    <script>
        const searchInput = document.getElementById('search-input');
        const suggestionsBox = document.getElementById('search-suggestions');
        let debounceTimer;

        if (searchInput && suggestionsBox) {
            searchInput.addEventListener('input', () => {
                clearTimeout(debounceTimer);
                const term = searchInput.value.trim();

                if (term.length < 2) {
                    suggestionsBox.classList.add('hidden');
                    suggestionsBox.innerHTML = '';
                    return;
                }

                debounceTimer = setTimeout(() => {
                    fetch(`{{ route('products.autocomplete') }}?q=${encodeURIComponent(term)}`)
                        .then((res) => res.json())
                        .then((results) => {
                            if (!results.length) {
                                suggestionsBox.classList.add('hidden');
                                suggestionsBox.innerHTML = '';
                                return;
                            }

                            suggestionsBox.innerHTML = results.map((item) => `
                                <a href="${item.url}" class="block truncate px-4 py-2 text-sm text-ink hover:bg-navy-50">${item.name}</a>
                            `).join('');
                            suggestionsBox.classList.remove('hidden');
                        })
                        .catch(() => suggestionsBox.classList.add('hidden'));
                }, 250);
            });

            document.addEventListener('click', (event) => {
                if (!searchInput.contains(event.target) && !suggestionsBox.contains(event.target)) {
                    suggestionsBox.classList.add('hidden');
                }
            });
        }
    </script>

    @auth
        {{-- Données pour le rafraîchissement des badges --}}
        <div id="app-data" 
             data-unread-count-url="{{ route('notifications.unread-count') }}"
             @if(auth()->user()->isCustomer())
             data-cart-count-url="{{ route('cart.count') }}"
             @endif
             style="display: none;"></div>
    @endauth

    @if(session('status'))
        <div id="flash-success" style="display: none;">{{ session('status') }}</div>
    @endif

    @if(session('error'))
        <div id="flash-error" style="display: none;">{{ session('error') }}</div>
    @endif

    @if(session('warning'))
        <div id="flash-warning" style="display: none;">{{ session('warning') }}</div>
    @endif

    <x-cookie-notice />
</body>
</html>
