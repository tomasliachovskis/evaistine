<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Api\ProductController;
use App\Models\Discount;
use App\Models\Store;
use App\Support\BreadcrumbSchema;
use App\Support\CanonicalUrl;
use App\Support\FaqSchema;
use App\Support\ItemListSchema;
use App\Support\LithuanianPlural;
use App\Support\PharmacyName;
use App\Support\OpeningHours;
use App\Support\StoreLocationsSchema;
use Illuminate\Support\Str;

// Ported from discount/src/app/vaistines/{page,[slug]/page,[slug]/[city]/page}.tsx.
class StoreController extends Controller
{
    public function index(ProductController $api)
    {
        $payload = json_decode($api->getStores()->getContent(), true);
        $stores = $payload['data'];
        $pageMeta = $payload['page_meta'];
        $path = '/vaistines';
        $faq = $pageMeta['faq'] ?? [];
        $breadcrumbs = [['name' => 'Pradžia', 'href' => '/'], ['name' => 'Vaistinės', 'href' => $path]];

        return view('stores.index', [
            'stores' => $stores,
            'pageMeta' => $pageMeta,
            'canonical' => CanonicalUrl::build($path),
            'robots' => CanonicalUrl::robotsMeta($path),
            'breadcrumbs' => $breadcrumbs,
            'breadcrumbSchema' => BreadcrumbSchema::build($breadcrumbs),
            'itemListSchema' => ItemListSchema::build(
                'Vaistinių akcijos Lietuvoje',
                collect($stores)->map(fn ($s) => ['name' => $s['name'], 'href' => ($s['shows_discounts_page'] ?? true) ? "/{$s['slug']}" : "/leidinys/{$s['slug']}"])->all()
            ),
            'faqSchema' => !empty($faq) ? FaqSchema::build($faq) : null,
        ]);
    }

    public function show(ProductController $api, string $slug, ?string $city = null)
    {
        $store = Store::where('slug', $slug)->firstOrFail();
        $response = $api->getStoreLocations($slug);

        if ($response->getStatusCode() === 404) {
            abort(404);
        }

        $payload = json_decode($response->getContent(), true);
        $locations = collect($payload['locations']);
        $locationsByCity = $locations->groupBy('city');

        if ($city !== null) {
            return $this->showCity($store, $locationsByCity, $city);
        }

        $path = "/vaistines/{$slug}";
        $breadcrumbs = [
            ['name' => 'Pradžia', 'href' => '/'],
            ['name' => 'Vaistinės', 'href' => '/vaistines'],
            ['name' => $store->name, 'href' => $path],
        ];

        // Cities, not individual addresses, are what the hub links out to —
        // each city gets its own real page (see showCity()) with the full
        // address/phone/hours detail; this index just needs enough per-city
        // real data (count, one sample address) to not be a bare link list.
        $cities = $locationsByCity->map(fn ($cityLocations, $cityName) => [
            'name' => $cityName,
            'slug' => Str::slug($cityName),
            'count' => $cityLocations->count(),
            'sampleAddress' => $cityLocations->first()['address'] ?? null,
        ])->values()->sortBy('name');

        // The chain-wide hours most locations share, if a clear majority
        // does — a 30-store chain with mixed hours gets no hours line.
        $summaries = $locations->map(fn (array $location) => OpeningHours::summary((array) ($location['hours'] ?? [])))->filter();
        $commonHours = $summaries->countBy()->sortDesc();
        $typicalHours = $commonHours->isNotEmpty() && $commonHours->first() * 2 > $locations->count() ? $commonHours->keys()->first() : null;

        $total = $locations->count();
        $topCities = $cities->sortByDesc('count')->take(3)->map(fn (array $city) => "{$city['name']} ({$city['count']})")->implode(', ');

        return view('stores.show', [
            'store' => $store,
            'title' => PharmacyName::phrase($store->name, 'genitive_plural') . ' adresai ir darbo laikas',
            'description' => "{$store->name} Lietuvoje – {$total} ".LithuanianPlural::storeWord($total)
                .($cities->count() > 1 ? ": {$topCities}".($cities->count() > 3 ? ' ir kiti miestai' : '') : '')
                .'.'.($typicalHours ? " Dažniausias darbo laikas: {$typicalHours}." : '')
                .' Adresai, darbo laikas ir kontaktai pagal miestą.',
            'cities' => $cities,
            'locationsUrl' => "/api/store-locations/{$slug}",
            'totalCount' => $locations->count(),
            'canonical' => CanonicalUrl::build($path),
            'robots' => CanonicalUrl::robotsMeta($path),
            'breadcrumbs' => $breadcrumbs,
            'breadcrumbSchema' => BreadcrumbSchema::build($breadcrumbs),
        ]);
    }

    // Real per-city page — NOT the old doorway pattern (1,399 near-identical
    // pages flagged by an SEO audit: same title/H1, differing only by one
    // address block). This one's title/description are built from real,
    // per-city data (actual address count + first two real addresses with
    // their own hours), so two different cities never render the same
    // sentence — a small city genuinely reads differently from a big one,
    // not just a swapped city name in fixed boilerplate.
    private function showCity(Store $store, $locationsByCity, string $citySlug)
    {
        $cityName = $locationsByCity->keys()->first(fn ($name) => Str::slug($name) === Str::slug($citySlug));

        if (!$cityName) {
            abort(404);
        }

        $cityLocations = $locationsByCity->get($cityName)->sortBy('address')->values();
        $count = $cityLocations->count();

        $path = "/vaistines/{$store->slug}/{$citySlug}";
        $breadcrumbs = [
            ['name' => 'Pradžia', 'href' => '/'],
            ['name' => 'Vaistinės', 'href' => '/vaistines'],
            ['name' => $store->name, 'href' => "/vaistines/{$store->slug}"],
            ['name' => $cityName, 'href' => $path],
        ];

        // Query words first: people search "senukai klaipeda darbo laikas".
        // Hours come from OpeningHours::summary() (whole week), not today's
        // row — Google keeps a snippet for days, so "šiandien 08:00–21:00"
        // was often wrong by the time someone read it.
        $title = $count === 1
            ? "{$store->name} {$cityName} darbo laikas – {$cityLocations->first()['address']}"
            : "{$store->name} {$cityName} darbo laikas – {$count} ".LithuanianPlural::storeWord($count);

        $summaries = $cityLocations->map(fn (array $location) => OpeningHours::summary((array) ($location['hours'] ?? [])));
        $sharedHours = $summaries->filter()->count() === $count && $summaries->unique()->count() === 1 ? $summaries->first() : null;

        if ($count === 1) {
            $description = "{$store->name} {$cityName}, {$cityLocations->first()['address']}"
                .($sharedHours ? " ({$sharedHours})" : '').'. Adresas, kontaktai ir vieta žemėlapyje.';
        } elseif ($sharedHours) {
            $description = "{$store->name} {$cityName}: ".$cityLocations->take(2)->pluck('address')->implode(', ')
                .($count > 2 ? ' ir kt.' : '.')." Darbo laikas: {$sharedHours}. Visi adresai ir kontaktai.";
        } else {
            // Hours differ per address: name as many addresses with their
            // hours as fit before the layout's ~158-char snippet cut.
            $build = fn (int $take) => "{$store->name} {$cityName}: ".$cityLocations->take($take)
                ->map(fn (array $location, int $i) => $summaries[$i] ? "{$location['address']} ({$summaries[$i]})" : $location['address'])
                ->implode(', ').($count > $take ? ' ir kt.' : '.');
            $description = mb_strlen($build(2)) <= 158 ? $build(2) : $build(1);
            $description .= ' Visi adresai, darbo laikas ir kontaktai.';
        }

        return view('stores.city', [
            'store' => $store,
            'cityName' => $cityName,
            'locations' => $cityLocations->map(fn (array $location) => [
                ...$location,
                'hours_summary' => OpeningHours::summary((array) ($location['hours'] ?? [])),
            ]),
            'hoursFacts' => OpeningHours::cityFacts($cityLocations),
            // Same rule as the store toolbar: link deals/leaflets only when
            // the store currently has any.
            'offersCount' => $store->showsDiscountsPage() ? Discount::where('store_id', $store->id)->count() : 0,
            'leafletsCount' => $store->flyers()->ready()->currentlyValid()->count(),
            'title' => $title,
            'description' => $description,
            'canonical' => CanonicalUrl::build($path),
            'robots' => CanonicalUrl::robotsMeta($path),
            'breadcrumbs' => $breadcrumbs,
            'breadcrumbSchema' => BreadcrumbSchema::build($breadcrumbs),
            'locationsSchema' => StoreLocationsSchema::build($store->name, $cityName, $cityLocations, CanonicalUrl::build($path)),
        ]);
    }
}