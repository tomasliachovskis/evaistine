<!DOCTYPE html>
<html lang="lt" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    @php
        // Every controller except HomeController passes a bare title with no
        // "| SuperAkcijos.lt" suffix — confirmed against production, where
        // every single page type carries it. Centralized here instead of in
        // every controller (HomePageMetaService already bakes the suffix
        // into its own generated title, so guard against double-suffixing).
        $pageTitle = $title ?? 'Rask visas akcijas ir nuolaidas Lietuvoje';
        if (!str_ends_with($pageTitle, '| SuperAkcijos.lt')) {
            $pageTitle .= ' | SuperAkcijos.lt';
        }
    @endphp
    <title>{{ $pageTitle }}</title>
    <meta name="description" content="{{ $description ?? 'Akcijos ir nuolaidos iš Maxima, Lidl, Iki, Rimi ir kitų tinklų vienoje vietoje. Peržiūrėkite šviežiausius savaitės pasiūlymus.' }}">
    <link rel="canonical" href="{{ $canonical ?? url()->current() }}">
    <meta name="robots" content="{{ $robots ?? 'index, follow' }}">

    <script type="application/ld+json">
        {!! json_encode([
            '@context' => 'https://schema.org',
            '@type' => 'Organization',
            'name' => 'SuperAkcijos.lt',
            'url' => 'https://superakcijos.lt',
            'logo' => 'https://superakcijos.lt/assets/logo.svg',
            'description' => 'Naujausi akcijų ir nuolaidų leidiniai vienoje vietoje. Rask geriausias MAXIMA, IKI, LIDL, NORFA, RIMI ir kitų prekybos tinklų akcijas.',
        ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) !!}
    </script>
    <script type="application/ld+json">
        {!! json_encode([
            '@context' => 'https://schema.org',
            '@type' => 'WebSite',
            'name' => 'SuperAkcijos.lt',
            'url' => 'https://superakcijos.lt',
            'description' => 'Rask visas akcijas ir nuolaidas vienoje vietoje',
            'potentialAction' => [
                '@type' => 'SearchAction',
                'target' => [
                    '@type' => 'EntryPoint',
                    'urlTemplate' => 'https://superakcijos.lt/akcijos/paieska/{search_term_string}',
                ],
                'query-input' => 'required name=search_term_string',
            ],
        ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) !!}
    </script>

    @stack('head')

    <meta name="csrf-token" content="{{ csrf_token() }}">

    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @livewireStyles
</head>
<body class="h-full min-h-screen bg-background text-font antialiased">
    <x-site-header />

    <main class="pt-[calc(3.5rem+env(safe-area-inset-top,0px))] pb-[calc(4.25rem+env(safe-area-inset-bottom,0px))] sm:pt-[calc(6.25rem+env(safe-area-inset-top,0px))] sm:pb-0">
        {{ $slot }}
    </main>

    <x-site-footer />

    <x-mobile-bottom-nav />

    <x-auth-modal />
    <x-cookie-consent />
    <script>
        document.addEventListener('alpine:init', () => {
            Alpine.store('authModal', {
                open: @json($errors->any() || request()->boolean('login')),
                mode: @json(old('form', 'login') === 'register' ? 'register' : 'login'),
            });
        });
    </script>

    @stack('body-end')
    @livewireScripts
</body>
</html>
