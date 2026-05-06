<?php

namespace App\Console\Commands;

use App\Models\Category;
use App\Models\CategoryMapper;
use App\Models\Discount;
use App\Models\DiscountHistory;
use App\Models\DiscountTemp;
use App\Models\Product;
use App\Models\Store;
use App\Rules\StoreRules\AibeRules;
use App\Rules\StoreRules\CiaRules;
use App\Rules\StoreRules\GrusteRules;
use App\Rules\StoreRules\IkiRules;
use App\Rules\StoreRules\LidlRules;
use App\Rules\StoreRules\MaximaRules;
use App\Rules\StoreRules\NorfaRules;
use App\Rules\StoreRules\RimiRules;
use App\Rules\StoreRules\SilasRules;
use App\Support\ProductPackSizeExtractor;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;
use Intervention\Image\ImageManager;
use Intervention\Image\Drivers\Gd\Driver;
use Intervention\Image\Interfaces\ImageInterface;

class ProcessDiscounts extends Command
{
    private const PRODUCT_IMAGE_MAX_BYTES = 102400;

    protected $signature = 'discounts:process';
    protected $description = 'Process new discounts from discount_temp table';

    public function __construct()
    {
        parent::__construct();
    }

    public function handle()
    {
        $this->info('Starting bulk category mapping...');
        $this->call('categories:bulk-map');
        $this->info('Bulk category mapping completed.');

        $duplicates = DiscountTemp::whereNotNull('product_url')
            ->where('processed', false)
            ->select('product_url')
            ->groupBy('product_url')
            ->havingRaw('COUNT(*) > 1')
            ->pluck('product_url');

        foreach ($duplicates as $url) {
            $records = DiscountTemp::where('product_url', $url)
                ->where('processed', false)
                ->where('category', '!=', '')
                ->orderBy('id', 'desc')
                ->get();

            $firstRecord = $records->shift();
            foreach ($records as $record) {
                $record->delete();
            }
        }

        $tempDiscounts = DiscountTemp::where('processed', false)->where('category', '!=', '')->get();

        foreach ($tempDiscounts as $tempDiscount) {
            $store = Store::where('name', $tempDiscount->store)->first();

            if (!$store) {
                $this->error("Store not found: {$tempDiscount->store}");
                continue;
            }

            $rules = $this->getStoreRules($store->name, $tempDiscount);

            $normalizedCondition = $this->normalizeCondition($tempDiscount->condition);

            $packResult = ProductPackSizeExtractor::extractAndStrip($tempDiscount->info);
            $packSize = $packResult['size'];
            $normalizedInfo = $packResult['info'];
            if (!empty($normalizedInfo)) {
                $normalizedInfo = str_replace(['"', "'"], '', $normalizedInfo);
            }

            $normalizedOriginalPrice = $rules->normalizePrice($tempDiscount->original_price);
            $normalizedDiscountedPrice = $rules->normalizePrice($tempDiscount->discounted_price);

            $discountPercent = $tempDiscount->discount_percent;
            $normalizedDiscount = $rules->normalizeDiscount($discountPercent);

            if (!empty($discountPercent)) {
//                $discountPercent = strtolower(trim($discountPercent));
//                $discountPercent = preg_replace('/[^0-9-]/', '', $discountPercent);
//                $discountPercent = str_replace('-', '', $discountPercent);
//                $discountPercent = !empty($discountPercent) ? (int)$discountPercent : null;
//                if ($discountPercent < 0) {
//                    $discountPercent = 0;
//                }

                $discountPercent = $normalizedDiscount;

                if ($normalizedOriginalPrice <= 0) {
                    $discountPercent = 0;
                }
            }

            if (empty($discountPercent) && $normalizedOriginalPrice > 0 && $normalizedDiscountedPrice > 0) {
                $discountPercent = round((($normalizedOriginalPrice - $normalizedDiscountedPrice) / $normalizedOriginalPrice) * 100);
            } else if (empty($discountPercent)) {
                $discountPercent = null;
            }

            if ($discountPercent < 0 || $discountPercent >= 100) {
                $discountPercent = 0;
            }

            if (empty($normalizedOriginalPrice) && empty($normalizedDiscountedPrice)) {
                $discountPercent = $normalizedDiscount;
            }

            if (!$rules->validate()) {
                $this->error("Invalid discount data for product: {$tempDiscount->id}");
                $tempDiscount->update(['processed' => true]);
                continue;
            }

            $startAt = $tempDiscount->start_at && strtotime($tempDiscount->start_at) ? $tempDiscount->start_at : null;
            $endAt = $tempDiscount->end_at && strtotime($tempDiscount->end_at) ? $tempDiscount->end_at : null;

            if ($startAt === null && $endAt === null) {
                $this->error("Invalid discount data for product: {$tempDiscount->id}");
                $tempDiscount->update(['processed' => true]);
                continue;
            }

            $categoryId = $this->assignCategory($tempDiscount, $store);
            if ($categoryId === false || $categoryId === null) {
                $this->error("Category not mapped for product: {$tempDiscount->id}");

                CategoryMapper::firstOrCreate(
                    ['store_category' => $tempDiscount->category],
                    ['store' => $store->id]
                );

                $tempDiscount->update(['processed' => true]);
                continue;
            }

            $normalizedProductName = $this->composeDisplayName($tempDiscount->name, $packSize);
            $productSlug = $this->generateProductSlug($normalizedProductName, $tempDiscount->brand, $store->name);

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

            $existingMainDiscount = Discount::where('product_id', $product->id)
                ->where('store_id', $store->id)
                ->where('start_at', $startAt)
                ->where('end_at', $endAt)
                ->first();

            if (!$existingMainDiscount) {
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
            } else {
                $existingMainDiscount->touch();
            }

            $tempDiscount->update(['processed' => true]);
        }

        $this->info('Discounts processed successfully');
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
            default:
                throw new \Exception("No rules found for store: {$storeName}");
        }
    }

    private function assignCategory(DiscountTemp $tempDiscount, Store $store)
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

        $category = Category::where('name', $categoryName)->first();
        if ($category) {
            return $category->id;
        }

        // Try full category exact match
        $mapper = CategoryMapper::where('store', $store->id)
            ->where('store_category', $categoryName)
            ->orderBy('id', 'asc')
            ->first();

        if ($mapper) {
            return $mapper->category_id;
        }

        // Try LIKE match with full category
        $mapper = CategoryMapper::where('store', $store->id)
            ->where('store_category', 'LIKE', $categoryName . '%')
            ->orderBy('id', 'asc')
            ->first();

        if ($mapper) {
            return $mapper->category_id;
        }

        if (str_contains($storeCategory, '/')) {
            $parts = explode('/', $storeCategory);

            // Try 2 parts first (if available)
            if (count($parts) >= 2) {
                $twoParts = $parts[0] . '/' . $parts[1];

                // Try exact match with 2 parts
                $mapper = CategoryMapper::where('store', $store->id)
                    ->where(function ($query) use ($twoParts) {
                        $query->where('store_category', $twoParts)
                            ->orWhere('store_category', $twoParts . '/');
                    })
                    ->orderBy('id', 'asc')
                    ->first();

                if ($mapper) {
                    return $mapper->category_id;
                }

                // Try LIKE match with 2 parts
                $mapper = CategoryMapper::where('store', $store->id)
                    ->where('store_category', 'LIKE', $twoParts . '%')
                    ->orderBy('id', 'asc')
                    ->first();

                if ($mapper) {
                    return $mapper->category_id;
                }
            }

            // Try 1 part (first part)
            $firstPart = $parts[0];

            // Try exact match with 1 part
            $mapper = CategoryMapper::where('store', $store->id)
                ->where('store_category', $firstPart)
                ->orderBy('id', 'asc')
                ->first();

            if ($mapper) {
                return $mapper->category_id;
            }

            // Try LIKE match with 1 part
            $mapper = CategoryMapper::where('store', $store->id)
                ->where('store_category', 'LIKE', $firstPart . '%')
                ->orderBy('id', 'asc')
                ->first();

            if ($mapper) {
                return $mapper->category_id;
            }
        }

        $this->createCategoryAndMapping($tempDiscount->category, $store);

        return false;
    }

    private function createCategoryAndMapping(string $storeCategory, Store $store)
    {
        CategoryMapper::create([
            'category_id' => null,
            'store_category' => $storeCategory,
            'store' => $store->id,
        ]);

        $this->info("Added unmapped category: {$storeCategory}");
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

        if ($finalSize === null || $finalSize === '') {
            return $baseName;
        }

        return $baseName . ', ' . $finalSize;
    }

    private function generateProductSlug(string $productName, ?string $brand, string $storeName): string
    {
        $slugParts = [];

//        if (!empty($brand) && $storeName !== 'maxima') {
//            $normalizedBrand = $this->normalizeForSlug($brand);
//            if (!empty($normalizedBrand)) {
//                $slugParts[] = $normalizedBrand;
//            }
//        }

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
            'Ą' => 'A', 'Č' => 'C', 'Ę' => 'E', 'Ė' => 'E', 'Į' => 'I', 'Š' => 'S', 'Ų' => 'U', 'Ū' => 'U', 'Ž' => 'Z'
        ];

        $text = strtr($text, $lithuanianToLatin);
        $text = strtolower($text);
        $text = preg_replace('/[^a-z0-9\s-]/', '', $text);
        $text = preg_replace('/\s+/', '-', $text);
        $text = preg_replace('/-+/', '-', $text);

        return trim($text, '-');
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
            if (!file_exists($pageImagePath)) return null;

            $manager = new ImageManager(new Driver());
            $image = $manager->read($pageImagePath);

            $imageWidth = $image->width();
            $imageHeight = $image->height();

            list($ymin, $xmin, $ymax, $xmax) = $box;

            $currentWidth = $xmax - $xmin;
            $currentHeight = $ymax - $ymin;

            // --- ADAPTYVI LOGIKA ---
            // Jei produktas mažas (pvz., < 200), didiname 50 vienetų (saugiau).
            // Jei produktas didelis (> 200), didiname tik 10 vienetų (minimaliai).
            $paddingX = ($currentWidth < 200) ? 50 : 10;
            $paddingY = ($currentHeight < 200) ? 50 : 10;

            $xmin = max(0, $xmin - $paddingX);
            $xmax = min(1000, $xmax + $paddingX);
            $ymin = max(0, $ymin - $paddingY);
            $ymax = min(1000, $ymax + $paddingY);
            // -----------------------

            $x1 = (int)($xmin * $imageWidth / 1000);
            $y1 = (int)($ymin * $imageHeight / 1000);
            $x2 = (int)($xmax * $imageWidth / 1000);
            $y2 = (int)($ymax * $imageHeight / 1000);

            $cropWidth = max(1, $x2 - $x1);
            $cropHeight = max(1, $y2 - $y1);

            $croppedImage = $image->crop($cropWidth, $cropHeight, $x1, $y1);

            $productsDir = 'products';
            if (!Storage::disk('public')->exists($productsDir)) Storage::disk('public')->makeDirectory($productsDir);

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
        $binary = '';

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
                    } else {
                        $last = array_pop($parts);
                        return implode(', ', $parts) . ' ir ' . $last;
                    }
                }
            }
        }

        $condition = str_replace(['"', "'"], '', $condition);

        $condition = str_replace(['Įsidėk 2 už', 'Pirk 2 už'], '1+1', $condition);

        return $condition;
    }
}
