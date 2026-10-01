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
    @php
        // Google cuts snippets at ~155-160 chars — trim at a word boundary
        // here once instead of in every controller's description builder.
        $metaDescription = trim((string) ($description ?? '')) !== ''
            ? $description
            : 'Akcijos ir nuolaidos iš Maxima, Lidl, Iki, Rimi ir kitų tinklų vienoje vietoje. Peržiūrėkite šviežiausius savaitės pasiūlymus.';
        if (mb_strlen($metaDescription) > 158) {
            $metaDescription = rtrim(mb_substr($metaDescription, 0, 155));
            $metaDescription = rtrim(preg_replace('/\s+\S*$/u', '', $metaDescription), " \t.,;:–-") . '…';
        }
        // Error pages pass canonical=false: a 404 has no canonical page.
        $canonicalUrl = $canonical ?? url()->current();
        // Open Graph needs an absolute image URL; product/flyer/article
        // pages pass their own ($ogImage), everything else the site image.
        $ogImageUrl = $ogImage ?? null;
        if ($ogImageUrl && str_starts_with($ogImageUrl, '/')) {
            $ogImageUrl = \App\Support\CanonicalUrl::origin() . $ogImageUrl;
        }
        $ogImageUrl = $ogImageUrl ?: \App\Support\CanonicalUrl::origin() . '/assets/logo.svg';
    @endphp
    <title>{{ $pageTitle }}</title>
    <link rel="icon" href="/favicon.ico" type="image/x-icon" sizes="32x32">
    <meta name="description" content="{{ $metaDescription }}">
    @if ($canonicalUrl)
        <link rel="canonical" href="{{ $canonicalUrl }}">
    @endif
    <meta name="robots" content="{{ $robots ?? 'index, follow' }}">

    {{-- Open Graph / Twitter: share previews (Facebook, Messenger, Viber,
         X). Were missing on every page (audit 2026-09-28). --}}
    <meta property="og:site_name" content="SuperAkcijos.lt">
    <meta property="og:locale" content="lt_LT">
    <meta property="og:type" content="{{ $ogType ?? 'website' }}">
    <meta property="og:title" content="{{ $pageTitle }}">
    <meta property="og:description" content="{{ $metaDescription }}">
    <meta property="og:url" content="{{ $canonicalUrl ?: url()->current() }}">
    <meta property="og:image" content="{{ $ogImageUrl }}">
    <meta name="twitter:card" content="{{ ($ogImage ?? null) ? 'summary_large_image' : 'summary' }}">
    <meta name="twitter:title" content="{{ $pageTitle }}">
    <meta name="twitter:description" content="{{ $metaDescription }}">
    <meta name="twitter:image" content="{{ $ogImageUrl }}">

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
@php
    // Phone app bar (site-header): title = the current page's breadcrumb,
    // Atgal = the one before it. Pages without breadcrumbs pass app-title /
    // back-href themselves. Crumbs come either mapped (name/href) or raw
    // from the API (name/slug).
    $crumbHref = fn ($crumb) => $crumb['href'] ?? (isset($crumb['slug']) ? ($crumb['slug'] === '/' ? '/' : '/'.ltrim($crumb['slug'], '/')) : null);
    $crumbList = array_values($breadcrumbs ?? []);
    $appTitle = $appTitle ?? (count($crumbList) ? ($crumbList[count($crumbList) - 1]['name'] ?? null) : null);
    $backHref = $backHref ?? (count($crumbList) >= 2 ? $crumbHref($crumbList[count($crumbList) - 2]) : (count($crumbList) === 1 ? '/' : null));
@endphp
<body class="h-full min-h-screen bg-background text-font antialiased">
    <x-site-header :app-title="$appTitle" :back-href="$backHref" />

    <main class="pt-[calc(var(--header-h)+env(safe-area-inset-top,0px))] pb-[calc(5.5rem+env(safe-area-inset-bottom,0px))] sm:pb-0">
        {{ $slot }}
    </main>

    <x-site-footer />

    <x-mobile-bottom-nav />

    <x-auth-modal />
    <x-price-watch-modal />
    <x-cookie-consent />
    <script>
        document.addEventListener('alpine:init', () => {
            Alpine.store('authModal', {
                open: @json((isset($errors) && $errors->any()) || request()->boolean('login')),
            });
            Alpine.store('priceWatchModal', {
                open: false,
            });
        });
    </script>

    @stack('body-end')
    @livewireScripts
</body>
</html>
