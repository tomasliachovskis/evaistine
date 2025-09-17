<?php

namespace App\Console\Commands;

use App\Models\Store;
use App\Models\Category;
use App\Services\DescriptionGenerationService;
use Illuminate\Console\Command;

class GenerateDescriptions extends Command
{
    protected $signature = 'descriptions:generate {type : store or category} {--id= : Specific ID to generate description for} {--all : Generate for all stores/categories}';
    protected $description = 'Generate descriptions for stores and categories using ChatGPT API';

    private DescriptionGenerationService $descriptionService;

    public function __construct(DescriptionGenerationService $descriptionService)
    {
        parent::__construct();
        $this->descriptionService = $descriptionService;
    }

    public function handle()
    {
        $type = $this->argument('type');
        $id = $this->option('id');
        $all = $this->option('all');

        if (!$this->descriptionService->isConfigured()) {
            $this->error('DescriptionGenerationService is not configured. Please set OPENAI_API_KEY environment variable.');
            return 1;
        }

        if ($type === 'store') {
            $this->handleStores($id, $all);
        } elseif ($type === 'category') {
            $this->handleCategories($id, $all);
        } else {
            $this->error('Invalid type. Use "store" or "category".');
            return 1;
        }

        return 0;
    }

    private function handleStores(?string $id, bool $all): void
    {
        if ($id) {
            $store = Store::find($id);
            if (!$store) {
                $this->error("Store with ID {$id} not found.");
                return;
            }
            $this->generateStoreDescription($store);
        } elseif ($all) {
            $stores = Store::all();
            $this->info("Generating descriptions for {$stores->count()} stores...");

            $bar = $this->output->createProgressBar($stores->count());
            $bar->start();

            foreach ($stores as $store) {
                $this->generateStoreDescription($store);
                $bar->advance();
                sleep(5);
            }

            $bar->finish();
            $this->newLine();
            $this->info('Completed generating descriptions for all stores.');
        } else {
            $this->error('Please specify --id or --all option.');
        }
    }

    private function handleCategories(?string $id, bool $all): void
    {
        if ($id) {
            $category = Category::find($id);
            if (!$category) {
                $this->error("Category with ID {$id} not found.");
                return;
            }

            $productCount = $category->products()->count();
            if ($productCount === 0) {
                $this->warn("Category '{$category->name}' has no products. Skipping description generation.");
                return;
            }

            $this->generateCategoryDescription($category);
        } elseif ($all) {
            $categoriesWithActiveDiscounts = Category::whereHas('products.discounts', function($query) {
                $query->where('end_at', '>=', now());
            })->get();
            $this->info("Generating descriptions for {$categoriesWithActiveDiscounts->count()} categories with active discounts...");

            $bar = $this->output->createProgressBar($categoriesWithActiveDiscounts->count());
            $bar->start();

            foreach ($categoriesWithActiveDiscounts as $category) {
                $this->generateCategoryDescription($category);
                $bar->advance();
                sleep(1);
            }

            $bar->finish();
            $this->newLine();
            $this->info('Completed generating descriptions for categories with products.');
        } else {
            $this->error('Please specify --id or --all option.');
        }
    }

    private function generateStoreDescription(Store $store): void
    {
        $this->info("Generating description for store: {$store->name}");

        try {
            $description = $this->descriptionService->generateStoreDescription($store);

            if ($description) {
                $store->update(['description' => $description]);
                $this->info("✓ Successfully generated description for {$store->name}");
                $this->line("Description: {$description}");
            } else {
                $this->warn("✗ Failed to generate description for {$store->name}");
            }
        } catch (\Exception $e) {
            $this->error("✗ Error generating description for {$store->name}: " . $e->getMessage());
        }
    }

    private function generateCategoryDescription(Category $category): void
    {
        $this->info("Generating description for category: {$category->name}");

        try {
            $activeDiscountsCount = $category->products()
                ->whereHas('discounts', function($query) {
                    $query->where('end_at', '>=', now());
                })
                ->count();

            if ($activeDiscountsCount === 0) {
                $this->warn("Category '{$category->name}' has no active discounts. Skipping description generation.");
                return;
            }

            $description = $this->descriptionService->generateCategoryDescription($category);

            if ($description) {
                $category->update(['description' => $description]);
                $this->info("✓ Successfully generated description for {$category->name}");
                $this->line("Description: {$description}");
            } else {
                $this->warn("✗ Failed to generate description for {$category->name}");
            }
        } catch (\Exception $e) {
            $this->error("✗ Error generating description for {$category->name}: " . $e->getMessage());
        }
    }
}
