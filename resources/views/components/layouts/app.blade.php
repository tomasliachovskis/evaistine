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
    <link rel="icon" href="/favicon.ico" type="image/x-icon" sizes="32x32">
    <meta name="description" content="{{ $description ?? 'Akcijos ir nuolaidos iš Maxima, Lidl, Iki, Rimi ir kitų tinklų vienoje vietoje. Peržiūrėkite šviežiausius savaitės pasiūlymus.' }}">
    <link rel="canonical" href="{{ $canonical ?? url()->current() }}">
    <meta name="robots" content="{{ $robots ?? 'index, follow' }}">

    @if (request()->is('/'))
        {{-- SEO audit finding: was emitted on every single page — Google
             only needs Organization/WebSite once, conventionally on the
             homepage; repeating it site-wide is just redundant payload. --}}
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
    @endif

    @stack('head')

    {{-- Ported from discount/src/app/layout.tsx — never carried over in the
         Blade rebuild, so GA4 (even automatic page_view events) and Clarity
         hadn't been recording anything on this site at all. Loaded
         unconditionally regardless of the cookie-consent banner's choice,
         same as the original. --}}
    <script src="https://www.googletagmanager.com/gtag/js?id=G-WD8DH3ZRD3" async></script>
    <script>
        window.dataLayer = window.dataLayer || [];
        function gtag(){dataLayer.push(arguments);}
        gtag('js', new Date());
        gtag('config', 'G-WD8DH3ZRD3');
    </script>
    <script>
        (function(c,l,a,r,i,t,y){
            c[a]=c[a]||function(){(c[a].q=c[a].q||[]).push(arguments)};
            t=l.createElement(r);t.async=1;t.src="https://www.clarity.ms/tag/"+i;
            y=l.getElementsByTagName(r)[0];y.parentNode.insertBefore(t,y);
        })(window, document, "clarity", "script", "v3dr99seco");
    </script>

    <meta name="csrf-token" content="{{ csrf_token() }}">

    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @livewireStyles
</head>
<body class="h-full min-h-screen bg-background text-font antialiased">
    <x-site-header />

    <main class="pt-[calc(3.5rem+env(safe-area-inset-top,0px))] pb-[calc(4.25rem+env(safe-area-inset-bottom,0px))] sm:pb-0 lg:pt-[calc(7.25rem+env(safe-area-inset-top,0px))]">
        {{ $slot }}
    </main>

    <x-site-footer />

    <x-mobile-bottom-nav />

    <x-auth-modal />
    <x-price-watch-modal />
    <x-cookie-consent />
    <x-signup-savings-popup />
    <script>
        document.addEventListener('alpine:init', () => {
            Alpine.store('authModal', {
                open: @json((isset($errors) && $errors->any()) || request()->boolean('login')),
            });
            Alpine.store('priceWatchModal', {
                open: false,
            });
            // Shared with site-header.blade.php (scroll-hide) and
            // discount-filters.blade.php (sticky bar's top offset) — a
            // global store instead of local component state so the two,
            // living in unrelated files/components, can react to the same
            // "is the fixed header currently showing its full height"
            // signal without wiring a prop through every listing page.
            Alpine.store('siteHeader', {
                visible: true,
            });
        });
    </script>

    @stack('body-end')
    @livewireScripts
</body>
</html>
