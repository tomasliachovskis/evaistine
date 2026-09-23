<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Api\ProductController;
use App\Models\Store;
use App\Support\BreadcrumbSchema;
use App\Support\CanonicalUrl;
use App\Support\FaqSchema;
use App\Support\ItemListSchema;
use Illuminate\Support\Str;

// Ported from discount/src/app/parduotuves/{page,[slug]/page,[slug]/[city]/page}.tsx.
class StoreController extends Controller
{
    private const DAY_KEYS = ['monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday'];

    private const DAY_LABELS = [
        'monday' => 'Pirmadienis', 'tuesday' => 'Antradienis', 'wednesday' => 'Trečiadienis',
        'thursday' => 'Ketvirtadienis', 'friday' => 'Penktadienis', 'saturday' => 'Šeštadienis', 'sunday' => 'Sekmadienis',
    ];

    public function index(ProductController $api)
    {
        $payload = json_decode($api->getStores()->getContent(), true);
        $stores = $payload['data'];
        $pageMeta = $payload['page_meta'];
        $path = '/parduotuves';
        $faq = $pageMeta['faq'] ?? [];
        $breadcrumbs = [['name' => 'Akcijos', 'href' => '/akcijos'], ['name' => 'Parduotuvės', 'href' => $path]];

        return view('stores.index', [
            'stores' => $stores,
            'pageMeta' => $pageMeta,
            'canonical' => CanonicalUrl::build($path),
            'robots' => CanonicalUrl::robotsMeta($path),
            'breadcrumbs' => $breadcrumbs,
            'breadcrumbSchema' => BreadcrumbSchema::build($breadcrumbs),
            'itemListSchema' => ItemListSchema::build(
                'Parduotuvių akcijos Lietuvoje',
                collect($stores)->map(fn ($s) => ['name' => $s['name'], 'href' => ($s['shows_discounts_page'] ?? true) ? "/akcijos/{$s['slug']}" : "/leidinys/{$s['slug']}"])->all()
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

        $path = "/parduotuves/{$slug}";
        $breadcrumbs = [
            ['name' => 'Akcijos', 'href' => '/akcijos'],
            ['name' => 'Parduotuvės', 'href' => '/parduotuves'],
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

        return view('stores.show', [
            'store' => $store,
            'cities' => $cities,
            'locations' => $locations,
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

        $path = "/parduotuves/{$store->slug}/{$citySlug}";
        $breadcrumbs = [
            ['name' => 'Akcijos', 'href' => '/akcijos'],
            ['name' => 'Parduotuvės', 'href' => '/parduotuves'],
            ['name' => $store->name, 'href' => "/parduotuves/{$store->slug}"],
            ['name' => $cityName, 'href' => $path],
        ];

        $todayKey = self::DAY_KEYS[now()->dayOfWeekIso - 1];
        $todayLabel = self::DAY_LABELS[$todayKey];

        $storeLabel = $count === 1 ? 'parduotuvė' : ($count % 10 >= 2 && $count % 10 <= 9 && !($count % 100 >= 11 && $count % 100 <= 19) ? 'parduotuvės' : 'parduotuvių');
        $title = "„{$store->name}“ {$cityName} – {$count} {$storeLabel}, adresai ir darbo laikas";

        // Real addresses + their real today's-hours, not a generic sentence
        // — this is the part that keeps every city page genuinely distinct.
        $sample = $cityLocations->take(2)->map(function ($location) use ($todayKey) {
            $hours = $location['hours'][$todayKey] ?? null;
            return $hours ? "{$location['address']} (šiandien {$hours})" : $location['address'];
        })->implode(', ');

        $description = "„{$store->name}“ {$cityName} mieste turi {$count} {$storeLabel}: {$sample}"
            .($count > 2 ? ' ir kt.' : '.')
            ." Žemiau visi adresai, darbo laikas ({$todayLabel}) ir kontaktai.";

        return view('stores.city', [
            'store' => $store,
            'cityName' => $cityName,
            'locations' => $cityLocations,
            'dayLabels' => self::DAY_LABELS,
            'todayKey' => $todayKey,
            'title' => $title,
            'description' => $description,
            'canonical' => CanonicalUrl::build($path),
            'robots' => CanonicalUrl::robotsMeta($path),
            'breadcrumbs' => $breadcrumbs,
            'breadcrumbSchema' => BreadcrumbSchema::build($breadcrumbs),
        ]);
    }
}