<?php

namespace App\Console\Commands;

use App\Models\Store;
use App\Models\Category;
use Illuminate\Console\Command;

class GetCategoriesStoresForArticles extends Command
{
    protected $signature = 'tmp:categories-stores-urls {--format=json : Output format (json, table)}';
    protected $description = 'Get all categories and stores with URLs and names for article generation with AI';

    public function handle()
    {
        $format = $this->option('format');
        
        $stores = Store::whereNotNull('slug')
            ->select('id', 'name', 'slug')
            ->get()
            ->map(function($store) {
                return [
                    'id' => $store->id,
                    'name' => $store->name,
                    'slug' => $store->slug,
                    'url' => "https://superakcijos.lt/akcijos/{$store->slug}",
                    'type' => 'store'
                ];
            });

        $categories = Category::whereNotNull('slug')
            ->where('hide', false)
            ->where(function($query) {
                $query->where('parent_id', 0)->orWhereNull('parent_id');
            })
            ->select('id', 'name', 'slug')
            ->get()
            ->map(function($category) {
                return [
                    'id' => $category->id,
                    'name' => $category->name,
                    'slug' => $category->slug,
                    'url' => "https://superakcijos.lt/akcijos/{$category->slug}",
                    'type' => 'category'
                ];
            });

        $storeCategoryCombinations = [];
        foreach ($stores as $store) {
            $storeModel = Store::find($store['id']);
            if ($storeModel) {
                $storeCategories = Category::whereHas('products.discounts', function($query) use ($storeModel) {
                    $query->where('store_id', $storeModel->id)
                        ->where('end_at', '>=', now());
                })
                ->whereNotNull('slug')
                ->where('hide', false)
                ->where(function($query) {
                    $query->where('parent_id', 0)->orWhereNull('parent_id');
                })
                ->select('id', 'name', 'slug')
                ->get()
                ->map(function($category) use ($store) {
                    return [
                        'store_id' => $store['id'],
                        'store_name' => $store['name'],
                        'store_slug' => $store['slug'],
                        'category_id' => $category->id,
                        'category_name' => $category->name,
                        'category_slug' => $category->slug,
                        'url' => "https://superakcijos.lt/akcijos/{$store['slug']}/{$category->slug}",
                        'type' => 'store-category'
                    ];
                });
                
                $storeCategoryCombinations = array_merge($storeCategoryCombinations, $storeCategories->toArray());
            }
        }

        $data = [
            'stores' => $stores->toArray(),
            'categories' => $categories->toArray(),
            'store_category_combinations' => $storeCategoryCombinations,
            'summary' => [
                'total_stores' => $stores->count(),
                'total_categories' => $categories->count(),
                'total_store_category_combinations' => count($storeCategoryCombinations)
            ]
        ];

        if ($format === 'table') {
            $this->displayAsTable($data);
        } else {
            $this->line(json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
        }

        return 0;
    }

    private function displayAsTable(array $data): void
    {
        $this->info('=== STORES ===');
        $this->table(
            ['ID', 'Name', 'Slug', 'URL'],
            collect($data['stores'])->map(function($item) {
                return [$item['id'], $item['name'], $item['slug'], $item['url']];
            })->toArray()
        );

        $this->info('=== CATEGORIES ===');
        $this->table(
            ['ID', 'Name', 'Slug', 'URL'],
            collect($data['categories'])->map(function($item) {
                return [$item['id'], $item['name'], $item['slug'], $item['url']];
            })->toArray()
        );

        $this->info('=== STORE-CATEGORY COMBINATIONS ===');
        $this->table(
            ['Store', 'Category', 'URL'],
            collect($data['store_category_combinations'])->map(function($item) {
                return ["{$item['store_name']} ({$item['store_slug']})", "{$item['category_name']} ({$item['category_slug']})", $item['url']];
            })->toArray()
        );

        $this->info('=== SUMMARY ===');
        $this->line("Total Stores: {$data['summary']['total_stores']}");
        $this->line("Total Categories: {$data['summary']['total_categories']}");
        $this->line("Total Store-Category Combinations: {$data['summary']['total_store_category_combinations']}");
    }
}








