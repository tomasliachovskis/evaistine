<?php

namespace App\Console\Commands;

use App\Models\CategoryMapper;
use App\Models\Store;
use Illuminate\Console\Command;

class DebugCategoryMapping extends Command
{
    protected $signature = 'debug:category-mapping {category} {store}';
    protected $description = 'Debug category mapping for a given category and store';

    public function handle()
    {
        $category = $this->argument('category');
        $storeName = $this->argument('store');

        $store = Store::where('name', $storeName)->first();

        if (!$store) {
            $this->error("Store not found: {$storeName}");
            return 1;
        }

        $this->info("Debugging category mapping for:");
        $this->info("Category: {$category}");
        $this->info("Store: {$storeName} (ID: {$store->id})");
        $this->line('');

        $result = $this->debugCategoryMapping($category, $store);

        if ($result) {
            $this->info("✅ Found mapping:");
            $this->info("Store Category: {$result->store_category}");
            $this->info("Mapped Category ID: {$result->category_id}");
        } else {
            $this->error("❌ No mapping found");
        }

        return 0;
    }

    private function debugCategoryMapping(string $storeCategory, Store $store)
    {
        $this->info("🔍 Testing mapping strategies...");
        $this->line('');

        // Try full category exact match first
        $this->info("1️⃣ Testing full category exact match: '{$storeCategory}'");

        $mapper = CategoryMapper::where('store', $store->id)
            ->where('store_category', $storeCategory)
            ->orderBy('id', 'asc')
            ->first();

        if ($mapper) {
            $this->info("   ✅ Exact match found!");
            return $mapper;
        } else {
            $this->info("   ❌ No exact match");
        }

        // Try LIKE match with full category
        $this->info("2️⃣ Testing full category LIKE match: '{$storeCategory}%'");

        $mapper = CategoryMapper::where('store', $store->id)
            ->where('store_category', 'LIKE', $storeCategory . '%')
            ->orderBy('id', 'asc')
            ->first();

        if ($mapper) {
            $this->info("   ✅ LIKE match found: '{$mapper->store_category}'");
            return $mapper;
        } else {
            $this->info("   ❌ No LIKE match");
        }

        // Clean up category (remove URLs like https://iki.lt/)
        $cleanCategory = str_replace(['https://iki.lt/'], '', $storeCategory);
        
        if ($cleanCategory !== $storeCategory) {
            $this->info("3️⃣ Testing cleaned category: '{$cleanCategory}'");
        }

        if (str_contains($cleanCategory, '/')) {
            $parts = explode('/', $cleanCategory);
            $this->info("Category contains '/', splitting into parts: " . implode(', ', $parts));
            $this->line('');

            // Try 2 parts first (if available)
            if (count($parts) >= 2) {
                $twoParts = $parts[0] . '/' . $parts[1];
                $this->info("4️⃣ Testing 2 parts: '{$twoParts}'");

                // Try exact match with 2 parts
                $mapper = CategoryMapper::where('store', $store->id)
                    ->where('store_category', $twoParts)
                    ->orderBy('id', 'asc')
                    ->first();

                if ($mapper) {
                    $this->info("   ✅ Exact match found!");
                    return $mapper;
                } else {
                    $this->info("   ❌ No exact match");
                }

                // Try LIKE match with 2 parts
                $mapper = CategoryMapper::where('store', $store->id)
                    ->where('store_category', 'LIKE', $twoParts . '%')
                    ->orderBy('id', 'asc')
                    ->first();

                if ($mapper) {
                    $this->info("   ✅ LIKE match found: '{$mapper->store_category}'");
                    return $mapper;
                } else {
                    $this->info("   ❌ No LIKE match");
                }
            }

            // Try 1 part (first part)
            $firstPart = $parts[0];
            $this->info("5️⃣ Testing 1 part: '{$firstPart}'");

            // Try exact match with 1 part
            $mapper = CategoryMapper::where('store', $store->id)
                ->where('store_category', $firstPart)
                ->orderBy('id', 'asc')
                ->first();

            if ($mapper) {
                $this->info("   ✅ Exact match found!");
                return $mapper;
            } else {
                $this->info("   ❌ No exact match");
            }

            // Try LIKE match with 1 part
            $mapper = CategoryMapper::where('store', $store->id)
                ->where('store_category', 'LIKE', $firstPart . '%')
                ->orderBy('id', 'asc')
                ->first();

            if ($mapper) {
                $this->info("   ✅ LIKE match found: '{$mapper->store_category}'");
                return $mapper;
            } else {
                $this->info("   ❌ No LIKE match");
            }
        }

        $this->line('');
        $this->info("📋 Available mappings for this store:");
        $availableMappings = CategoryMapper::where('store', $store->id)
            ->orderBy('store_category')
            ->get(['store_category', 'category_id']);

        if ($availableMappings->isEmpty()) {
            $this->warn("   No mappings found for this store");
        } else {
            foreach ($availableMappings as $mapping) {
                $this->line("   - {$mapping->store_category} → {$mapping->category_id}");
            }
        }

        return null;
    }
}
