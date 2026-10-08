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
use Illuminate\Support\Facades\Http;
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
                // Date-only (midnight), not the exact current time — end_at
                // is a DATE stored at midnight ("valid through this day"),
                // so comparing against the current moment wrongly excluded
                // a discount expiring today for the rest of today.
                $query->whereNull('end_at')
                    ->orWhere('end_at', '>=', date('Y-m-d 00:00:00'));
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
            // Stable per-document ID the scraper already resolves from its
            // source platform (Yumpu/Issuu docId, dcatalog guid, resolved
            // PDF URL, issue number, ...) — an authoritative dedup key,
            // since it identifies the same underlying document across
            // scrape runs regardless of how its title gets reworded. Not
            // every scraper has one (a handful of single-catalog-page
            // sites don't), so it stays optional and the title/date match
            // below remains the fallback for those.
            'source_id' => 'nullable|string',
            'pdf' => 'required_without:pdf_source_url|file|mimetypes:application/pdf|max:61440',
            // PDFs over 40MB (Elimart's print masters are 54-65MB) come as
            // the store's own URL instead, downloaded below.
            'pdf_source_url' => 'nullable|url|starts_with:https://',
        ]);

        if (! $request->hasFile('pdf') && ! $this->isPublicHttpsUrl($validated['pdf_source_url'] ?? '')) {
            return response()->json(['error' => 'pdf_source_url must be a public https URL'], 422);
        }

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
        //
        // valid_from and valid_to are matched INDEPENDENTLY of each other,
        // not as an all-or-nothing pair — 18 scrapers (e.g. Senukai) only
        // ever expose an end date, never a start date. The old paired check
        // required BOTH dates present to do a date match, and fell back to
        // requiring BOTH null otherwise — a real valid_to with a null
        // valid_from matched neither branch, so $alreadyExists was always
        // false and every re-scrape created a fresh duplicate row for the
        // same leaflet (confirmed live 2026-09-18: Senukai's "Leidinys Nr.
        // 29" scraped into 3 separate StoreFlyer rows over two weeks).
        // LOWER(TRIM()) on both sides, not an exact match — a scraper
        // re-reading the same leaflet's title on a later run can pick up a
        // stray extra space or different capitalization from the source
        // site without the leaflet itself having changed at all; an exact
        // string compare would treat that as a "new" title and miss the
        // duplicate exactly like the valid_from/valid_to bug above did.
        $normalizedTitle = isset($validated['title']) ? mb_strtolower(trim($validated['title'])) : null;
        $sourceId = $validated['source_id'] ?? null;

        // source_id, when the scraper has one, is authoritative on its own
        // (same document ID = same physical leaflet, regardless of title
        // wording) — only fall back to the title/date match for the
        // scrapers with no natural per-document ID.
        $alreadyExists = $sourceId
            ? StoreFlyer::where('store_id', $store->id)->where('source_id', $sourceId)->exists()
            : StoreFlyer::where('store_id', $store->id)
                ->when(
                    !empty($validated['valid_from']),
                    fn ($query) => $query->whereDate('valid_from', $validated['valid_from']),
                    fn ($query) => $query->whereNull('valid_from')
                )
                ->when(
                    !empty($validated['valid_to']),
                    fn ($query) => $query->whereDate('valid_to', $validated['valid_to']),
                    fn ($query) => $query->whereNull('valid_to')
                )
                ->when(
                    !empty($normalizedTitle),
                    fn ($query) => $query->whereRaw('LOWER(TRIM(title)) = ?', [$normalizedTitle]),
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
            'source_id' => $sourceId,
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
        $pdfFile = $request->hasFile('pdf')
            ? $request->file('pdf')->getRealPath()
            : $this->downloadPdf($validated['pdf_source_url']);

        if ($pdfFile === null) {
            $flyer->delete();

            return response()->json(['error' => 'Could not download a PDF from pdf_source_url'], 422);
        }

        $path = FlyerStorage::pdfPathForFlyer($store, $flyer->slug);
        $stream = fopen($pdfFile, 'rb');
        Storage::disk('public')->put($path, $stream);
        if (is_resource($stream)) {
            fclose($stream);
        }

        if (! $request->hasFile('pdf')) {
            @unlink($pdfFile);
        }

        return response()->json($flyer, 201);
    }

    // This endpoint has no auth, so a URL to download must be https and
    // resolve only to public addresses (no localhost, private network or
    // cloud metadata IPs).
    private function isPublicHttpsUrl(string $url): bool
    {
        $parts = parse_url($url);
        if (($parts['scheme'] ?? null) !== 'https' || empty($parts['host'])) {
            return false;
        }

        $ips = gethostbynamel($parts['host']) ?: [];

        return $ips !== [] && collect($ips)->every(fn ($ip) => filter_var(
            $ip,
            FILTER_VALIDATE_IP,
            FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE
        ) !== false);
    }

    // Streams the PDF to a temp file. Redirects are not followed (they could
    // point somewhere private). Returns null unless it's a real PDF of at
    // most 200MB.
    private function downloadPdf(string $url): ?string
    {
        $tmp = tempnam(sys_get_temp_dir(), 'flyer_pdf_');

        try {
            $response = Http::timeout(300)
                ->withOptions(['allow_redirects' => false])
                ->sink($tmp)
                ->get($url);
        } catch (\Throwable $e) {
            @unlink($tmp);

            return null;
        }

        $handle = fopen($tmp, 'rb');
        $magic = $handle ? fread($handle, 5) : '';
        if ($handle) {
            fclose($handle);
        }

        if (! $response->successful() || $magic !== '%PDF-' || filesize($tmp) > 200 * 1024 * 1024) {
            @unlink($tmp);

            return null;
        }

        return $tmp;
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
            // Where the addresses came from (nuolaidos.lt, the chain's own
            // site, or the hand-checked manual file); see scrapers/hours/.
            'source' => 'nullable|string|max:255',
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
                    'source' => $validated['source'] ?? 'nuolaidos.lt',
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
