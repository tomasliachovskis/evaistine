<?php

namespace App\Http\Controllers;

use App\Services\PriceIndexService;
use App\Support\BreadcrumbSchema;
use App\Support\CacheVersion;
use App\Support\CanonicalUrl;
use App\Support\ContentFreshness;
use App\Support\LithuanianDate;
use Illuminate\Support\Facades\Cache;

class CheapestProductsController extends Controller
{
    public function index(PriceIndexService $service)
    {
        $path = '/pigiausios-prekes';

        $breadcrumbs = [
            ['name' => 'Akcijos', 'href' => '/'],
            ['name' => 'Pigiausios prekės', 'href' => $path],
        ];

        return view('pigiausios-prekes.show', [
            'title' => 'Pigiausios prekės parduotuvėse – SuperAkcijos.lt',
            'description' => 'Kiekvieną savaitę sekame kasdienių prekių kainas didžiausiuose prekybos tinkluose ir parodome, kur šiuo metu pigiausia apsipirkti.',
            'canonical' => CanonicalUrl::build($path),
            // Deliberately not in the sitemap yet and not linked from
            // anywhere on the site — explicit noindex too, so it can't get
            // picked up by Google before it's actually ready to launch.
            'robots' => 'noindex, nofollow, noarchive, nosnippet',
            'breadcrumbs' => $breadcrumbs,
            'breadcrumbSchema' => BreadcrumbSchema::build($breadcrumbs),
            // Reads pre-warmed curated_deals rows — see
            // PriceIndexService::refreshPersistedIndex()/DealPoolRefresher.
            // Same cache key as NewHomeController's homepage teaser (same
            // underlying data) — this dev DB is the real shared remote
            // instance, so even this cheap indexed read still costs ~10
            // network round trips; caching the assembled result avoids
            // paying that twice.
            'data' => Cache::remember(
                'price_index_data_'.CacheVersion::suffix(['discounts']),
                1800,
                fn () => $service->getPageData()
            ),
            'freshnessLabel' => LithuanianDate::relative(ContentFreshness::forAll()),
        ]);
    }
}
