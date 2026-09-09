<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\DiscountTemp;
use App\Models\Discount;
use App\Models\Store;
use App\Models\StoreFlyer;
use App\Models\StoreLocation;
use App\Services\FlyerCoverInfoExtractor;
use App\Services\StoreFlyerSlugBuilder;
use App\Services\StoreFlyerTitleBuilder;
use App\Support\FlyerStorage;
use App\Support\WorkingHoursParser;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;

class ScrapingController extends Controller
{
    public function storeDiscountTemp(Request $request)
    {
        $validator = Validator::make($request->all(), [
            '*.name' => 'nullable|string',
            '*.store' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $discountTemps = [];
        foreach ($request->all() as $product) {
            if (!empty($product['start_at']) &&
                (\DateTime::createFromFormat('Y-m-d', $product['start_at'])) !== false &&
                new \DateTime($product['start_at']) > (new \DateTime())->modify('+10 months')
            ) {
                $product['start_at'] = (new \DateTime($product['start_at']))
                    ->modify('-1 year')
                    ->format('Y-m-d');
            }
            $discountTemps[] = DiscountTemp::create($product);
        }

        return response()->json($discountTemps, 201);
    }

    public function getActiveDiscountsByStore($storeName)
    {
        $validator = Validator::make(['store' => $storeName], [
            'store' => 'required|string'
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $store = Store::where('name', $storeName)->first();

        if (!$store) {
            return response()->json(['error' => 'Store not found'], 404);
        }

        $discounts = Discount::where('store_id', $store->id)
            ->where(function ($query) {
                $query->whereNull('end_at')
                    ->orWhere('end_at', '>=', date('Y-m-d H:i:s'));
            })
            ->with(['product'])
            ->orderBy('discount_percent', 'desc')
            ->get();

        return response()->json($discounts);
    }

    public function checkValidDiscount(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'store' => 'required|string',
            'url' => 'required|string',
            'discounted_price' => 'required|numeric|min:0'
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $store = Store::where('name', $request->store)->first();

        if (!$store) {
            return response()->json(['error' => 'Store not found'], 404);
        }

        $existingDiscount = Discount::where('store_id', $store->id)
            ->where('product_url', $request->url)
            ->where('discounted_price', $request->discounted_price)
            ->where(function ($query) {
                $query->whereNull('end_at')
                    ->orWhereDate('end_at', '>=', now()->toDateString());
            })
            ->first();

        $isValid = $existingDiscount !== null;

        if ($isValid && $existingDiscount->product) {
            $existingDiscount->touch();
        }

        return response()->json([
            'is_valid' => $isValid,
        ]);
    }

    public function storeFlyer(Request $request)
    {
        $validated = $request->validate([
            'store' => 'required|string',
            'title' => 'nullable|string',
            'catalog_name' => 'nullable|string',
            'issue_number' => 'nullable|string',
            // Some leaflets (e.g. Lidl's seasonal "Katalogai") never expose a
            // reliable end date from either the listing page or the leaflet's
            // own cover image — rather than dropping them, they're ingested
            // with no dates at all, treated as always-current with no
            // expiry, and ranked below dated ones on the frontend (see
            // StoreFlyer::scopeOrdered — valid_from DESC already sorts NULLs
            // last on MySQL).
            'valid_from' => 'nullable|date',
            'valid_to' => 'nullable|date',
            'pdf' => 'required|file|mimetypes:application/pdf|max:61440',
        ]);

        $store = Store::whereRaw('LOWER(name) = ?', [mb_strtolower($validated['store'])])->first();

        if (!$store) {
            return response()->json(['error' => 'Store not found'], 404);
        }

        if (!empty($validated['valid_to']) && Carbon::parse($validated['valid_to'])->lt(Carbon::today())) {
            return response()->json(['skipped' => true, 'reason' => 'expired']);
        }

        // Keying only on (store, valid_from, valid_to) isn't enough — Lidl's
        // weekly food and non-food leaflets share the exact same validity
        // week but are genuinely different leaflets, so title has to be part
        // of the identity too. Undated leaflets have no valid_from/valid_to
        // to key on at all, so title is the whole identity for those.
        $alreadyExists = StoreFlyer::where('store_id', $store->id)
            ->when(
                !empty($validated['valid_from']) && !empty($validated['valid_to']),
                fn ($query) => $query
                    ->whereDate('valid_from', $validated['valid_from'])
                    ->whereDate('valid_to', $validated['valid_to']),
                fn ($query) => $query->whereNull('valid_from')->whereNull('valid_to')
            )
            ->when(
                !empty($validated['title']),
                fn ($query) => $query->where('title', $validated['title']),
                fn ($query) => $query->whereNull('title')
            )
            ->exists();

        if ($alreadyExists) {
            return response()->json(['skipped' => true, 'reason' => 'duplicate']);
        }

        $flyer = new StoreFlyer([
            'store_id' => $store->id,
            'catalog_name' => $validated['catalog_name'] ?? null,
            'issue_number' => $validated['issue_number'] ?? null,
            'title' => $validated['title'] ?? null,
            'valid_from' => $validated['valid_from'] ?? null,
            'valid_to' => $validated['valid_to'] ?? null,
            'sort_order' => 0,
            'is_active' => true,
            'source' => 'scraper',
            'processing_status' => StoreFlyer::STATUS_PENDING,
        ]);
        $flyer->setRelation('store', $store);

        if (!$flyer->title) {
            $flyer->title = app(StoreFlyerTitleBuilder::class)->build($flyer, $store);
        }

        $flyer->slug = app(StoreFlyerSlugBuilder::class)->build(
            $store,
            $flyer->title,
            $flyer->valid_from?->format('Y-m-d'),
            $flyer->valid_to?->format('Y-m-d')
        );

        $flyer->save();

        // Scrapers only ever run on the local dev machine, but this app's DB
        // is shared with production over the network while local storage and
        // the queue are not. Deliberately leave pdf_url unset and don't
        // dispatch the page-split job here: setting it now would bake in
        // this machine's own APP_URL (nuolaidos.wip locally — unreachable
        // for real site visitors) and the job would run against a local
        // queue with no worker, against a file production can't see. The
        // PDF is saved at the normal conventional path; the calling
        // scraper's submitFlyer() (scrapers/flyers/_shared.js) pushes it to
        // production over SSH/rsync right after this response — NOT
        // deploy.sh, which excludes storage/app/public/ entirely. Once it
        // lands there, flyers:process-pages --pending finalizes pdf_url
        // (using production's own APP_URL) and dispatches the page-split
        // job from production's own queue worker; flyers:process-discounts
        // --pending independently reads the same file straight from this
        // row's pdf_url to run Gemini discount extraction — no separate
        // storage/app/flyers-incoming/ upload needed for either.
        $path = FlyerStorage::pdfPathForFlyer($store, $flyer->slug);
        Storage::disk('public')->put($path, file_get_contents($request->file('pdf')->getRealPath()));

        return response()->json($flyer, 201);
    }

    public function extractFlyerInfo(Request $request, FlyerCoverInfoExtractor $extractor)
    {
        $validated = $request->validate([
            'store' => 'required|string',
            'image' => 'required|file|image|max:20480',
        ]);

        $store = Store::whereRaw('LOWER(name) = ?', [mb_strtolower($validated['store'])])->first();

        if (!$store) {
            return response()->json(['error' => 'Store not found'], 404);
        }

        if (!$extractor->isConfigured()) {
            return response()->json(['error' => 'Gemini not configured'], 503);
        }

        $result = $extractor->extract(
            file_get_contents($request->file('image')->getRealPath()),
            $store->name
        );

        if (!$result || empty($result['valid_from']) || empty($result['valid_to'])) {
            return response()->json(['error' => 'Could not extract reliable dates'], 422);
        }

        return response()->json($result);
    }

    public function storeLocations(Request $request)
    {
        $validated = $request->validate([
            'store' => 'required|string',
            'locations' => 'required|array',
            'locations.*.externalId' => 'required|string',
            'locations.*.city' => 'required|string',
            'locations.*.address' => 'required|string',
            'locations.*.addressSlug' => 'nullable|string',
            'locations.*.lat' => 'nullable|numeric',
            'locations.*.lng' => 'nullable|numeric',
            'locations.*.phones' => 'nullable|array',
            'locations.*.workTimes' => 'nullable|array',
        ]);

        $store = Store::whereRaw('LOWER(name) = ?', [mb_strtolower($validated['store'])])->first();

        if (!$store) {
            return response()->json(['error' => 'Store not found'], 404);
        }

        $seenExternalIds = [];
        $created = 0;
        $updated = 0;

        foreach ($validated['locations'] as $location) {
            $storeLocation = StoreLocation::updateOrCreate(
                ['store_id' => $store->id, 'external_id' => $location['externalId']],
                [
                    'city' => $location['city'],
                    'address' => $location['address'],
                    'slug' => $location['addressSlug'] ?? null,
                    'lat' => $location['lat'] ?? null,
                    'lng' => $location['lng'] ?? null,
                    'phone' => $location['phones'][0] ?? null,
                    'hours' => WorkingHoursParser::parse($location['workTimes'] ?? []),
                    'is_active' => true,
                    'source' => 'nuolaidos.lt',
                ]
            );

            $storeLocation->wasRecentlyCreated ? $created++ : $updated++;
            $seenExternalIds[] = $location['externalId'];
        }

        // Anything for this store not present in this run's payload has
        // closed (or dropped off the source site) — deactivate rather than
        // delete, so history isn't lost.
        $deactivated = StoreLocation::where('store_id', $store->id)
            ->where('is_active', true)
            ->whereNotIn('external_id', $seenExternalIds)
            ->update(['is_active' => false]);

        return response()->json([
            'store' => $store->name,
            'created' => $created,
            'updated' => $updated,
            'deactivated' => $deactivated,
        ], 201);
    }
}
