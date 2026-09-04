<?php

namespace App\Console\Commands;

use App\Models\Category;
use App\Models\CategoryMapper;
use App\Models\Discount;
use App\Models\DiscountTemp;
use App\Models\Product;
use App\Models\ProductMapping;
use App\Models\Store;
use App\Rules\StoreRules\AibeRules;
use App\Rules\StoreRules\CiaRules;
use App\Rules\StoreRules\EpromoRules;
use App\Rules\StoreRules\ExpressMarketRules;
use App\Rules\StoreRules\GrusteRules;
use App\Rules\StoreRules\GulbeleRules;
use App\Rules\StoreRules\IkiRules;
use App\Rules\StoreRules\KoopsRules;
use App\Rules\StoreRules\KubasRules;
use App\Rules\StoreRules\LidlRules;
use App\Rules\StoreRules\MaximaRules;
use App\Rules\StoreRules\NorfaRules;
use App\Rules\StoreRules\RimiRules;
use App\Rules\StoreRules\SilasRules;
use App\Rules\StoreRules\ThomasPhilippsRules;
use App\Rules\StoreRules\VynotekaRules;
use App\Services\DealPoolRefresher;
use App\Support\NormalizesDiscountDates;
use App\Support\ProductPackSizeExtractor;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Intervention\Image\Drivers\Gd\Driver;
use Intervention\Image\ImageManager;
use Intervention\Image\Interfaces\ImageInterface;

class ProcessDiscounts extends Command
{
    use NormalizesDiscountDates;

    private const PRODUCT_IMAGE_MAX_BYTES = 102400;
    private const CHUNK_SIZE = 250;

    protected $signature = 'discounts:process
                            {--map-categories : Run ChatGPT bulk category mapping before processing}
                            {--skip-stores= : Comma-separated store names to ignore during processing}
                            {--only-store= : Only process this single store}';
    protected $description = 'Process new discounts from discount_temp table';

    /** @var array<string, Store> */
    private array $storesByName = [];

    /** @var array<string, int> */
    private array $categoriesByName = [];

    /** @var array<int, list<CategoryMapper>> */
    private array $mappersByStoreId = [];

    /** @var array<string, int> */
    private array $productMappingsByName = [];

    /** @var array<string, Product> */
    private array $productsBySlug = [];

    /** @var array<int, Product> */
    private array $productsById = [];

    /** @var array<string, bool> */
    private array $existingDiscountCache = [];

    /** @var array<string, bool> */
    private array $unmappedCategoryKeys = [];

    /** @var array<string, string> */
    private array $pageImageBinaryCache = [];

    private ?ImageManager $imageManager = null;

    /** @var list<string>|null */
    private ?array $skipStoreNames = null;

    /** @var array<int, true> store_id => true, for stores that actually got a new Discount row this run */
    private array $touchedStoreIds = [];

    public function handle(DealPoolRefresher $dealPoolRefresher): int
    {
        if ($this->option('map-categories')) {
            $this->info('Starting bulk category mapping...');
            $this->call('categories:bulk-map');
            $this->info('Bulk category mapping completed.');
        }

        $skip = $this->skipStoreNames();
        if ($skip !== []) {
            $this->info('Ignoring stores: ' . implode(', ', $skip));
        }

        $this->preloadLookups();
        $this->removeDuplicateTempRows();

        $processedIds = [];
        $query = DiscountTemp::query()
            ->where('processed', false)
            ->where('category', '!=', '');
        $this->applySkipStoresFilter($query);
        $this->applyOnlyStoreFilter($query);

        $total = (clone $query)->count();
        $this->info("Processing {$total} discount_temp row(s)...");

        $query->orderBy('id')
            ->chunkById(self::CHUNK_SIZE, function ($tempDiscounts) use (&$processedIds) {
                foreach ($tempDiscounts as $tempDiscount) {
                    if ($this->processTempDiscount($tempDiscount)) {
                        $processedIds[] = $tempDiscount->id;
                    }
                }

                $this->markTempProcessed($processedIds);
                $processedIds = [];
            });

        $this->info('Discounts processed successfully');

        if ($this->touchedStoreIds !== []) {
            $storeIds = array_keys($this->touchedStoreIds);
            $this->info('Refreshing curated deals for touched store(s): '.implode(',', $storeIds));
            $dealPoolRefresher->refreshAfterBatch($storeIds);
        }

        return self::SUCCESS;
    }

    /**
     * @return list<string>
     */
    private function skipStoreNames(): array
    {
        if ($this->skipStoreNames !== null) {
            return $this->skipStoreNames;
        }

        $option = $this->option('skip-stores');
        if (!$option) {
            $this->skipStoreNames = [];

            return $this->skipStoreNames;
        }

        $this->skipStoreNames = array_values(array_filter(array_map(
            fn (string $name) => mb_strtolower(trim($name)),
            explode(',', $option)
        )));

        return $this->skipStoreNames;
    }

    private function applySkipStoresFilter($query): void
    {
        $skip = $this->skipStoreNames();
        if ($skip === []) {
            return;
        }

        $placeholders = implode(',', array_fill(0, count($skip), '?'));
        $query->whereRaw("LOWER(store) NOT IN ({$placeholders})", $skip);
    }

    private function applyOnlyStoreFilter($query): void
    {
        $onlyStore = $this->option('only-store');
        if (!$onlyStore) {
            return;
        }

        $query->whereRaw('LOWER(store) = ?', [mb_strtolower(trim($onlyStore))]);
    }

    private function preloadLookups(): void
    {
        $this->storesByName = [];
        foreach (Store::all() as $store) {
            $this->storesByName[mb_strtolower($store->name)] = $store;
        }
        $this->categoriesByName = Category::pluck('id', 'name')->all();
        $this->productMappingsByName = ProductMapping::pluck('product_id', 'name')->all();

        $this->mappersByStoreId = [];
        foreach (CategoryMapper::orderBy('id')->get() as $mapper) {
            $this->mappersByStoreId[$mapper->store][] = $mapper;
        }
    }

    private function removeDuplicateTempRows(): void
    {
        $deleted = DB::delete('
            DELETE t1 FROM discount_temp t1
            INNER JOIN discount_temp t2
                ON t1.product_url = t2.product_url
                AND t1.id < t2.id
            WHERE t1.processed = 0
              AND t2.processed = 0
              AND t1.product_url IS NOT NULL
              AND t1.category != ?
              AND t2.category != ?
        ', ['', '']);

        if ($deleted > 0) {
            $this->info("Removed {$deleted} duplicate discount_temp row(s).");
        }
    }

    private function processTempDiscount(DiscountTemp $tempDiscount): bool
    {
        $store = $this->storesByName[mb_strtolower($tempDiscount->store)] ?? null;

        if (!$store) {
            $this->error("Store not found: {$tempDiscount->store}");
            return true;
        }

        $rules = $this->getStoreRules($store->name, $tempDiscount);

        $normalizedCondition = $this->normalizeCondition($tempDiscount->condition);

        $packResult = ProductPackSizeExtractor::extractAndStrip($tempDiscount->info);
        $packSize = $packResult['size'];
        $normalizedInfo = $packResult['info'];
        if (!empty($normalizedInfo)) {
            $normalizedInfo = str_replace(['"', "'"], '', $normalizedInfo);
            $normalizedInfo = $this->stripAsterisks($normalizedInfo) ?: null;
        }

        $normalizedOriginalPrice = $rules->normalizePrice($tempDiscount->original_price);
        $normalizedDiscountedPrice = $rules->normalizePrice($tempDiscount->discounted_price);

        $discountPercent = $tempDiscount->discount_percent;
        $normalizedDiscount = $rules->normalizeDiscount($discountPercent);

        if (!empty($discountPercent)) {
            $discountPercent = $normalizedDiscount;

            if ($normalizedOriginalPrice <= 0) {
                $discountPercent = 0;
            }
        }

        if (empty($discountPercent) && $normalizedOriginalPrice > 0 && $normalizedDiscountedPrice > 0) {
            $discountPercent = round((($normalizedOriginalPrice - $normalizedDiscountedPrice) / $normalizedOriginalPrice) * 100);
        } elseif (empty($discountPercent)) {
            $discountPercent = null;
        }

        if ($discountPercent < 0 || $discountPercent >= 100) {
            $discountPercent = 0;
        }

        if (empty($normalizedOriginalPrice) && empty($normalizedDiscountedPrice)) {
            $discountPercent = $normalizedDiscount;
        }

        if (!$rules->validate()) {
            return true;
        }

        $startAt = static::normalizeDiscountDate($tempDiscount->start_at);
        $endAt = static::normalizeDiscountDate($tempDiscount->end_at);

        if ($startAt === null && $endAt === null) {
            return true;
        }

        // Mirrors ArchiveExpiredDiscounts' own cutoff (end_at < start of
        // today) — without this, a row scraped late in a leaflet's last day
        // but not reached by the queued processing job until after midnight
        // gets a Discount created and then deleted again by
        // discounts:archive-expired moments later, in the same job run,
        // without ever being visible on the site. Skip creating it at all
        // instead of round-tripping through create-then-archive.
        if ($endAt !== null && $endAt < now()->startOfDay()->format('Y-m-d')) {
            return true;
        }

        $categoryId = $this->resolveCategoryId($tempDiscount, $store);
        if ($categoryId === false || $categoryId === null) {
            CategoryMapper::firstOrCreate(
                ['store_category' => $tempDiscount->category],
                ['store' => $store->id]
            );

            return true;
        }

        $normalizedProductName = $this->composeDisplayName($tempDiscount->name, $packSize);
        $productSlug = $this->generateProductSlug($normalizedProductName, $tempDiscount->brand, $store->name);

        $mappingProductId = $this->productMappingsByName[$normalizedProductName] ?? null;

        if ($mappingProductId) {
            $product = $this->findProductById($mappingProductId);
            if (!$product) {
                unset($this->productMappingsByName[$normalizedProductName]);
                $product = $this->findOrCreateProduct($normalizedProductName, $productSlug, $tempDiscount, $categoryId);
            }
        } else {
            $product = $this->findOrCreateProduct($normalizedProductName, $productSlug, $tempDiscount, $categoryId);
        }

        $isFlyerSource = !empty($tempDiscount->box) && !empty($tempDiscount->page_image_path);

        if ($product->wasRecentlyCreated && $isFlyerSource) {
            if (empty($product->image_url)) {
                $croppedImageUrl = $this->cropProductImage($tempDiscount, $product);
                if ($croppedImageUrl) {
                    $product->image_url = $croppedImageUrl;
                }
            }
            $product->image_from_flyer = true;
            $product->save();
        } elseif (!$product->wasRecentlyCreated && $product->image_from_flyer && !empty($tempDiscount->image_url)) {
            $product->image_url = $tempDiscount->image_url;
            $product->image_from_flyer = false;
            $product->save();
        }

        if ($this->discountExists($product->id, $store->id, $startAt, $endAt)) {
            return true;
        }

        Discount::withoutEvents(function () use (
            $product,
            $store,
            $tempDiscount,
            $normalizedOriginalPrice,
            $normalizedDiscountedPrice,
            $discountPercent,
            $normalizedCondition,
            $normalizedInfo,
            $startAt,
            $endAt
        ) {
            Discount::create([
                'product_id' => $product->id,
                'store_id' => $store->id,
                'product_url' => $tempDiscount->product_url,
                'original_price' => $normalizedOriginalPrice,
                'discounted_price' => $normalizedDiscountedPrice,
                'discount_percent' => $discountPercent,
                'condition' => $normalizedCondition,
                'info' => $normalizedInfo,
                'card' => $tempDiscount->card,
                'start_at' => $startAt,
                'end_at' => $endAt,
            ]);
        });

        $this->markDiscountExists($product->id, $store->id, $startAt, $endAt);
        $this->touchedStoreIds[$store->id] = true;

        return true;
    }

    private function findProductById(int $productId): ?Product
    {
        if (isset($this->productsById[$productId])) {
            return $this->productsById[$productId];
        }

        $product = Product::find($productId);
        if ($product) {
            $this->productsById[$productId] = $product;
            $this->productsBySlug[$product->slug] = $product;
        }

        return $product;
    }

    private function findOrCreateProduct(
        string $normalizedProductName,
        string $productSlug,
        DiscountTemp $tempDiscount,
        int $categoryId
    ): Product {
        if (isset($this->productsBySlug[$productSlug])) {
            return $this->productsBySlug[$productSlug];
        }

        $product = Product::firstOrCreate(
            ['slug' => $productSlug],
            [
                'name' => $normalizedProductName,
                'brand' => $tempDiscount->brand,
                'slug' => $productSlug,
                'description' => '',
                'category_id' => $categoryId,
                'image_url' => $tempDiscount->image_url,
            ]
        );

        $this->productsBySlug[$productSlug] = $product;
        $this->productsById[$product->id] = $product;

        if ($product->wasRecentlyCreated) {
            $this->productMappingsByName[$normalizedProductName] = $product->id;
        }

        return $product;
    }

    private function discountExists(int $productId, int $storeId, ?string $startAt, ?string $endAt): bool
    {
        $key = $this->discountCacheKey($productId, $storeId, $startAt, $endAt);

        if (array_key_exists($key, $this->existingDiscountCache)) {
            return $this->existingDiscountCache[$key];
        }

        $exists = Discount::query()
            ->where('product_id', $productId)
            ->where('store_id', $storeId)
            ->where('start_at', $startAt)
            ->where('end_at', $endAt)
            ->exists();

        $this->existingDiscountCache[$key] = $exists;

        return $exists;
    }

    private function markDiscountExists(int $productId, int $storeId, ?string $startAt, ?string $endAt): void
    {
        $this->existingDiscountCache[$this->discountCacheKey($productId, $storeId, $startAt, $endAt)] = true;
    }

    private function discountCacheKey(int $productId, int $storeId, ?string $startAt, ?string $endAt): string
    {
        return "{$productId}:{$storeId}:{$startAt}:{$endAt}";
    }

    /**
     * @param array<int> $ids
     */
    private function markTempProcessed(array $ids): void
    {
        if ($ids === []) {
            return;
        }

        DiscountTemp::whereIn('id', $ids)->update(['processed' => true]);
    }

    private function resolveCategoryId(DiscountTemp $tempDiscount, Store $store): int|false|null
    {
        if (empty($tempDiscount->category)) {
            return null;
        }

        $productName = strtolower($tempDiscount->name);
        $petKeywords = ['šunų', 'ėdalas', 'kačių', 'gyvūnų'];

        foreach ($petKeywords as $keyword) {
            if (mb_strpos($productName, $keyword) !== false) {
                return 619;
            }
        }

        $categoryName = $tempDiscount->category;
        $storeCategory = str_replace(['https://iki.lt/'], '', $categoryName);

        if (isset($this->categoriesByName[$categoryName])) {
            return $this->categoriesByName[$categoryName];
        }

        $mappers = $this->mappersByStoreId[$store->id] ?? [];

        $mapper = $this->findMapper($mappers, $categoryName, exact: true);
        if ($mapper && $mapper->category_id) {
            return $mapper->category_id;
        }

        $mapper = $this->findMapper($mappers, $categoryName, exact: false);
        if ($mapper && $mapper->category_id) {
            return $mapper->category_id;
        }

        if (str_contains($storeCategory, '/')) {
            $parts = explode('/', $storeCategory);

            if (count($parts) >= 2) {
                $twoParts = $parts[0] . '/' . $parts[1];

                $mapper = $this->findMapper($mappers, $twoParts, exact: true, allowTrailingSlash: true);
                if ($mapper && $mapper->category_id) {
                    return $mapper->category_id;
                }

                $mapper = $this->findMapper($mappers, $twoParts, exact: false);
                if ($mapper && $mapper->category_id) {
                    return $mapper->category_id;
                }
            }

            $firstPart = $parts[0];

            $mapper = $this->findMapper($mappers, $firstPart, exact: true);
            if ($mapper && $mapper->category_id) {
                return $mapper->category_id;
            }

            $mapper = $this->findMapper($mappers, $firstPart, exact: false);
            if ($mapper && $mapper->category_id) {
                return $mapper->category_id;
            }
        }

        $this->createCategoryAndMapping($tempDiscount->category, $store);

        return false;
    }

    /**
     * @param list<CategoryMapper> $mappers
     */
    private function findMapper(
        array $mappers,
        string $needle,
        bool $exact,
        bool $allowTrailingSlash = false
    ): ?CategoryMapper {
        foreach ($mappers as $mapper) {
            if ($exact) {
                if ($mapper->store_category === $needle) {
                    return $mapper;
                }
                if ($allowTrailingSlash && $mapper->store_category === $needle . '/') {
                    return $mapper;
                }
                continue;
            }

            if (str_starts_with($mapper->store_category, $needle)) {
                return $mapper;
            }
        }

        return null;
    }

    private function createCategoryAndMapping(string $storeCategory, Store $store): void
    {
        $key = $store->id . ':' . $storeCategory;
        if (isset($this->unmappedCategoryKeys[$key])) {
            return;
        }

        CategoryMapper::firstOrCreate(
            ['store_category' => $storeCategory, 'store' => $store->id],
            ['category_id' => null]
        );

        $this->unmappedCategoryKeys[$key] = true;
        $this->info("Added unmapped category: {$storeCategory}");
    }

    private function getStoreRules(string $storeName, DiscountTemp $tempDiscount)
    {
        switch ($storeName) {
            case 'Lidl':
                return new LidlRules($tempDiscount);
            case 'Maxima':
                return new MaximaRules($tempDiscount);
            case 'Rimi':
                return new RimiRules($tempDiscount);
            case 'Norfa':
                return new NorfaRules($tempDiscount);
            case 'Iki':
                return new IkiRules($tempDiscount);
            case 'Šilas':
                return new SilasRules($tempDiscount);
            case 'Aibė':
                return new AibeRules($tempDiscount);
            case 'Grustė':
                return new GrusteRules($tempDiscount);
            case 'Čia':
                return new CiaRules($tempDiscount);
            case 'Express Market':
                return new ExpressMarketRules($tempDiscount);
            case 'Kubas':
                return new KubasRules($tempDiscount);
            case 'Koops':
                return new KoopsRules($tempDiscount);
            case 'Gulbelė':
                return new GulbeleRules($tempDiscount);
            case 'Vynoteka':
                return new VynotekaRules($tempDiscount);
            case 'Thomas Philipps':
                return new ThomasPhilippsRules($tempDiscount);
            case 'ePromo':
                return new EpromoRules($tempDiscount);
            default:
                throw new \Exception("No rules found for store: {$storeName}");
        }
    }

    private function composeDisplayName(string $rawName, ?string $infoPackSize): string
    {
        $nameResult = ProductPackSizeExtractor::extractAndStrip($rawName);
        $nameSize = $nameResult['size'];
        $strippedName = $nameResult['info'];

        $baseName = ($strippedName !== null && trim($strippedName) !== '')
            ? trim($strippedName)
            : trim($rawName);

        $finalSize = $nameSize ?? $infoPackSize;

        $composed = ($finalSize === null || $finalSize === '')
            ? $baseName
            : $baseName . ', ' . $finalSize;

        return $this->stripAsterisks($composed);
    }

    private function generateProductSlug(string $productName, ?string $brand, string $storeName): string
    {
        $slugParts = [];

        $normalizedName = $this->normalizeForSlug($productName);
        if (!empty($normalizedName)) {
            $slugParts[] = $normalizedName;
        }

        return implode('-', $slugParts);
    }

    private function normalizeForSlug(string $text): string
    {
        $text = trim($text);
        if (empty($text)) {
            return '';
        }

        $lithuanianToLatin = [
            'ą' => 'a', 'č' => 'c', 'ę' => 'e', 'ė' => 'e', 'į' => 'i', 'š' => 's', 'ų' => 'u', 'ū' => 'u', 'ž' => 'z',
            'Ą' => 'A', 'Č' => 'C', 'Ę' => 'E', 'Ė' => 'E', 'Į' => 'I', 'Š' => 'S', 'Ų' => 'U', 'Ū' => 'U', 'Ž' => 'Z',
        ];

        $text = strtr($text, $lithuanianToLatin);
        $text = strtolower($text);
        $text = preg_replace('/[^a-z0-9\s-]/', '', $text);
        $text = preg_replace('/\s+/', '-', $text);
        $text = preg_replace('/-+/', '-', $text);

        return trim($text, '-');
    }

    private function imageManager(): ImageManager
    {
        if ($this->imageManager === null) {
            $this->imageManager = new ImageManager(new Driver());
        }

        return $this->imageManager;
    }

    private function readPageImage(string $pageImagePath): ?ImageInterface
    {
        if (!isset($this->pageImageBinaryCache[$pageImagePath])) {
            if (!file_exists($pageImagePath)) {
                return null;
            }
            $this->pageImageBinaryCache[$pageImagePath] = file_get_contents($pageImagePath);
        }

        return $this->imageManager()->read($this->pageImageBinaryCache[$pageImagePath]);
    }

    private function cropProductImage(DiscountTemp $tempDiscount, Product $product): ?string
    {
        if (empty($tempDiscount->box) || empty($tempDiscount->page_image_path)) {
            return null;
        }

        try {
            $box = json_decode($tempDiscount->box, true);
            if (!is_array($box) || count($box) !== 4) {
                return null;
            }

            $pageImagePath = storage_path('app/public/' . $tempDiscount->page_image_path);
            $image = $this->readPageImage($pageImagePath);
            if ($image === null) {
                return null;
            }

            $imageWidth = $image->width();
            $imageHeight = $image->height();

            list($ymin, $xmin, $ymax, $xmax) = $box;

            $currentWidth = $xmax - $xmin;
            $currentHeight = $ymax - $ymin;

            $paddingX = ($currentWidth < 200) ? 50 : 10;
            $paddingY = ($currentHeight < 200) ? 50 : 10;

            $xmin = max(0, $xmin - $paddingX);
            $xmax = min(1000, $xmax + $paddingX);
            $ymin = max(0, $ymin - $paddingY);
            $ymax = min(1000, $ymax + $paddingY);

            $x1 = (int) ($xmin * $imageWidth / 1000);
            $y1 = (int) ($ymin * $imageHeight / 1000);
            $x2 = (int) ($xmax * $imageWidth / 1000);
            $y2 = (int) ($ymax * $imageHeight / 1000);

            $cropWidth = max(1, $x2 - $x1);
            $cropHeight = max(1, $y2 - $y1);

            $croppedImage = $image->crop($cropWidth, $cropHeight, $x1, $y1);

            $productsDir = 'products';
            if (!Storage::disk('public')->exists($productsDir)) {
                Storage::disk('public')->makeDirectory($productsDir);
            }

            $filename = $product->slug . '-' . time() . '.jpg';
            $storagePath = $productsDir . '/' . $filename;
            $binary = $this->encodeCroppedProductImage($croppedImage);
            Storage::disk('public')->put($storagePath, $binary);

            return Storage::disk('public')->url($storagePath);
        } catch (\Exception $e) {
            Log::error('Error cropping product image', ['product_id' => $product->id, 'error' => $e->getMessage()]);

            return null;
        }
    }

    private function encodeCroppedProductImage(ImageInterface $image): string
    {
        $image->blendTransparency('ffffff');

        $quality = 84;

        while ($quality >= 58) {
            $binary = $image->toJpeg(quality: $quality)->toString();
            if (strlen($binary) <= self::PRODUCT_IMAGE_MAX_BYTES) {
                return $binary;
            }
            $quality -= 8;
        }

        $image->scaleDown(720, 720);
        $binary = $image->toJpeg(quality: 76)->toString();
        if (strlen($binary) <= self::PRODUCT_IMAGE_MAX_BYTES) {
            return $binary;
        }

        $image->scaleDown(480, 480);

        return $image->toJpeg(quality: 70)->toString();
    }

    private function normalizeCondition(?string $condition): ?string
    {
        if (empty($condition)) {
            return null;
        }

        if (preg_match('/[–-]\d+%/', $condition)) {
            return '';
        }

        if (str_starts_with($condition, '{') && str_ends_with($condition, '}')) {
            $jsonData = json_decode($condition, true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($jsonData)) {
                $parts = [];
                foreach ($jsonData as $key => $value) {
                    if ($key === 'shopH' && $value === 'shopH') {
                        $parts[] = 'H';
                    } elseif ($key === 'shopXxl' && $value === 'shopXxl') {
                        $parts[] = 'XXL';
                    } elseif ($key === 'shopXl' && $value === 'shopXl') {
                        $parts[] = 'XL';
                    }
                }

                if (!empty($parts)) {
                    if (count($parts) === 1) {
                        return $parts[0];
                    }

                    $last = array_pop($parts);

                    return implode(', ', $parts) . ' ir ' . $last;
                }
            }
        }

        $condition = str_replace(['"', "'"], '', $condition);
        $condition = str_replace(['Įsidėk 2 už', 'Pirk 2 už'], '1+1', $condition);

        return $condition;
    }

    private function stripAsterisks(string $text): string
    {
        $text = str_replace('*', '', $text);

        return trim(preg_replace('/\s+/u', ' ', $text));
    }
}
