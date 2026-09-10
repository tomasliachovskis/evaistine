<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Api\ProductController;
use App\Models\Store;
use App\Models\StoreLocation;
use App\Support\BreadcrumbSchema;
use App\Support\CanonicalUrl;
use App\Support\FaqSchema;
use App\Support\ItemListSchema;
use App\Support\StoreLocationSchema;

// Ported from discount/src/app/parduotuves/{page,[slug]/page,[slug]/[city]/page}.tsx.
class StoreController extends Controller
{
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
                collect($stores)->map(fn ($s) => ['name' => $s['name'], 'href' => "/akcijos/{$s['slug']}"])->all()
            ),
            'faqSchema' => !empty($faq) ? FaqSchema::build($faq) : null,
        ]);
    }

    public function show(ProductController $api, string $slug, ?string $city = null)
    {
        // Per-city subpages were a doorway-page pattern flagged by an SEO
        // audit (1,399 near-identical /parduotuves/{store}/{city} pages,
        // same title/H1, differing only by one address block) — the
        // no-city page below already groups and renders every city's
        // locations in one place, so a bookmarked/indexed city URL just
        // redirects to the store page instead of 404ing.
        if ($city !== null) {
            return redirect("/parduotuves/{$slug}", 301);
        }

        $store = Store::where('slug', $slug)->firstOrFail();
        $response = $api->getStoreLocations($slug);

        if ($response->getStatusCode() === 404) {
            abort(404);
        }

        $payload = json_decode($response->getContent(), true);
        $locations = collect($payload['locations']);

        $path = "/parduotuves/{$slug}";
        $breadcrumbs = [
            ['name' => 'Akcijos', 'href' => '/akcijos'],
            ['name' => 'Parduotuvės', 'href' => '/parduotuves'],
            ['name' => $store->name, 'href' => $path],
        ];

        return view('stores.show', [
            'store' => $store,
            'locations' => $locations,
            'canonical' => CanonicalUrl::build($path),
            'robots' => CanonicalUrl::robotsMeta($path),
            'breadcrumbs' => $breadcrumbs,
            'breadcrumbSchema' => BreadcrumbSchema::build($breadcrumbs),
        ]);
    }

    // Individual /parduotuves/{store}/{city}/{locationSlug} page, brought
    // back after the old blanket per-city pages (see show() above) were
    // flagged as doorways — this is deliberately NOT the same pattern:
    // each page here is keyed by one real, specific address (its own
    // phone/hours/coordinates), not a city-wide aggregation duplicated
    // across every city with only the city name swapped.
    private const DAY_KEYS = ['monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday'];

    private const DAY_LABELS = [
        'monday' => 'Pirmadienis', 'tuesday' => 'Antradienis', 'wednesday' => 'Trečiadienis',
        'thursday' => 'Ketvirtadienis', 'friday' => 'Penktadienis', 'saturday' => 'Šeštadienis', 'sunday' => 'Sekmadienis',
    ];

    public function location(string $slug, string $city, string $locationSlug)
    {
        $store = Store::where('slug', $slug)->firstOrFail();

        // $city is a slug (matches the link built in stores/show.blade.php,
        // e.g. Str::slug('Šiauliai') === 'siauliai') — compare against a
        // slugified city, not a raw lowercase one, or a diacritic city name
        // would never match its own generated link.
        $location = StoreLocation::where('store_id', $store->id)
            ->where('slug', $locationSlug)
            ->active()
            ->get()
            ->first(fn ($l) => \Illuminate\Support\Str::slug($l->city) === \Illuminate\Support\Str::slug($city));

        if (!$location) {
            abort(404);
        }

        $path = "/parduotuves/{$slug}/{$city}/{$locationSlug}";
        $breadcrumbs = [
            ['name' => 'Akcijos', 'href' => '/akcijos'],
            ['name' => 'Parduotuvės', 'href' => '/parduotuves'],
            ['name' => $store->name, 'href' => "/parduotuves/{$slug}"],
            ['name' => $location->address, 'href' => $path],
        ];

        $todayKey = self::DAY_KEYS[now()->dayOfWeekIso - 1];
        $todayHours = $location->hours[$todayKey] ?? null;

        $title = "„{$store->name}“ {$location->address}, {$location->city} – darbo laikas ir kontaktai";

        $descriptionParts = ["„{$store->name}“ parduotuvė adresu {$location->address}, {$location->city}."];
        $descriptionParts[] = $todayHours
            ? "Šiandien ({$this->todayLabel()}) dirba {$todayHours}."
            : "Šiandien ({$this->todayLabel()}) nedirba.";
        if (!empty($location->phone)) {
            $descriptionParts[] = "Tel. {$location->phone}.";
        }

        return view('stores.location', [
            'store' => $store,
            'location' => $location,
            'dayLabels' => self::DAY_LABELS,
            'todayKey' => $todayKey,
            'title' => $title,
            'description' => implode(' ', $descriptionParts),
            'canonical' => CanonicalUrl::build($path),
            'robots' => CanonicalUrl::robotsMeta($path),
            'breadcrumbs' => $breadcrumbs,
            'breadcrumbSchema' => BreadcrumbSchema::build($breadcrumbs),
            'localBusinessSchema' => StoreLocationSchema::build($store, $location, $path),
        ]);
    }

    private function todayLabel(): string
    {
        return self::DAY_LABELS[self::DAY_KEYS[now()->dayOfWeekIso - 1]];
    }
}
