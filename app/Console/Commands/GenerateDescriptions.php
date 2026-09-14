<?php

namespace App\Console\Commands;

use App\Models\Store;
use App\Models\Category;
use App\Models\Discount;
use App\Services\DescriptionGenerationService;
use Illuminate\Console\Command;

class GenerateDescriptions extends Command
{
    protected $signature = 'descriptions:generate {type : store, category, faq, store-faq, or store-category} {--id= : Specific ID to generate description for} {--all : Generate for all stores/categories} {--store-category : Generate top products tables for store+category combinations}';
    protected $description = 'Generate descriptions or FAQ for stores and categories using ChatGPT API, or top products tables for store+category combinations';

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
        } elseif ($type === 'faq') {
            $this->handleCategoryFaqs($id, $all);
        } elseif ($type === 'store-faq') {
            $this->handleStoreFaqs($id, $all);
        } elseif ($type === 'store-category') {
            $this->handleStoreCategories($id, $all);
        } else {
            $this->handleStores($id, $all);
            $this->handleCategories($id, $all);
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
            $categoriesWithProducts = Category::whereHas('products')->get();
            $this->info("Generating descriptions for {$categoriesWithProducts->count()} categories with products (rich GPT description for those with active discounts, minimal fallback otherwise)...");

            $bar = $this->output->createProgressBar($categoriesWithProducts->count());
            $bar->start();

            foreach ($categoriesWithProducts as $category) {
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

    private function handleCategoryFaqs(?string $id, bool $all): void
    {
        if ($id) {
            $category = Category::find($id);
            if (!$category) {
                $this->error("Category with ID {$id} not found.");
                return;
            }

            $this->generateCategoryFaq($category);
        } elseif ($all) {
            $categoriesWithActiveDiscounts = Category::whereHas('products.discounts', function ($query) {
                // now()->startOfDay(): end_at is a DATE stored at midnight
                // ("valid through this day") — plain now() wrongly excluded
                // a discount expiring today for the rest of today.
                $query->where('end_at', '>=', now()->startOfDay());
            })->get();
            $this->info("Generating FAQ for {$categoriesWithActiveDiscounts->count()} categories with active discounts...");

            $bar = $this->output->createProgressBar($categoriesWithActiveDiscounts->count());
            $bar->start();

            foreach ($categoriesWithActiveDiscounts as $category) {
                $this->generateCategoryFaq($category);
                $bar->advance();
                sleep(1);
            }

            $bar->finish();
            $this->newLine();
            $this->info('Completed generating FAQ for categories with active discounts.');
        } else {
            $this->error('Please specify --id or --all option.');
        }
    }

    private function handleStoreFaqs(?string $id, bool $all): void
    {
        if ($id) {
            $store = Store::find($id);
            if (!$store) {
                $this->error("Store with ID {$id} not found.");
                return;
            }

            $this->generateStoreFaq($store);
        } elseif ($all) {
            $stores = Store::whereHas('discounts')->get();
            $this->info("Generating FAQ for {$stores->count()} stores with active discounts...");

            $bar = $this->output->createProgressBar($stores->count());
            $bar->start();

            foreach ($stores as $store) {
                $this->generateStoreFaq($store);
                $bar->advance();
                sleep(1);
            }

            $bar->finish();
            $this->newLine();
            $this->info('Completed generating FAQ for stores with active discounts.');
        } else {
            $this->error('Please specify --id or --all option.');
        }
    }

    private function generateStoreFaq(Store $store): void
    {
        $this->info("Generating FAQ for store: {$store->name}");

        try {
            $faq = $this->descriptionService->generateStoreFaq($store);

            if ($faq) {
                $store->update(['faq' => $faq]);
                $this->info("✓ Successfully generated " . count($faq) . " FAQ item(s) for {$store->name}");
            } else {
                $this->warn("✗ Failed to generate FAQ for {$store->name}");
            }
        } catch (\Exception $e) {
            $this->error("✗ Error generating FAQ for {$store->name}: " . $e->getMessage());
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
                    // now()->startOfDay(): see comment above in the --all branch.
                    $query->where('end_at', '>=', now()->startOfDay());
                })
                ->count();

            if ($activeDiscountsCount === 0) {
                $category->update([
                    'description' => "{$category->name} akcijos ir nuolaidos – palyginkite kainas skirtingose parduotuvėse. Naujos akcijos šioje kategorijoje atsiranda kiekvieną savaitę.",
                    'meta_description' => "{$category->name} akcijos ir nuolaidos vienoje vietoje – SuperAkcijos.lt.",
                ]);
                $this->warn("Category '{$category->name}' has no active discounts. Wrote minimal fallback description instead of calling GPT.");
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

    private function generateCategoryFaq(Category $category): void
    {
        $this->info("Generating FAQ for category: {$category->name}");

        try {
            $faq = $this->descriptionService->generateCategoryFaq($category);

            if ($faq) {
                $category->update(['faq' => $faq]);
                $this->info("✓ Successfully generated " . count($faq) . " FAQ item(s) for {$category->name}");
            } else {
                $this->warn("✗ Failed to generate FAQ for {$category->name}");
            }
        } catch (\Exception $e) {
            $this->error("✗ Error generating FAQ for {$category->name}: " . $e->getMessage());
        }
    }

    private function handleStoreCategories(?string $id, bool $all): void
    {
        if ($all) {
            $combinations = Discount::where(function ($query) {})
                ->with(['store', 'product.category'])
                ->get()
                ->groupBy(function($discount) {
                    return $discount->store_id . '-' . $discount->product->category_id;
                })
                ->map(function($discounts) {
                    $first = $discounts->first();
                    return [
                        'store' => $first->store,
                        'category' => $first->product->category,
                    ];
                })
                ->filter(function($item) {
                    return $item['store'] && $item['category'];
                })
                ->values();

            $this->info("Generating intros for {$combinations->count()} store+category combinations...");

            $bar = $this->output->createProgressBar($combinations->count());
            $bar->start();

            foreach ($combinations as $combination) {
                $this->generateStoreCategoryIntro($combination['store'], $combination['category']);
                $bar->advance();
                sleep(2);
            }

            $bar->finish();
            $this->newLine();
            $this->info('Completed generating intros for all store+category combinations.');
        } else {
            $this->error('Please specify --all option for store-category type.');
        }
    }

    private function generateStoreCategoryIntro(Store $store, Category $category): void
    {
        try {
            $success = $this->descriptionService->saveStoreCategoryIntro($store, $category);

            if ($success) {
                $this->info("✓ Successfully generated intro for {$store->name} + {$category->name}");
            } else {
                $this->warn("✗ No intro generated for {$store->name} + {$category->name}");
            }
        } catch (\Exception $e) {
            $this->error("✗ Error generating intro for {$store->name} + {$category->name}: " . $e->getMessage());
        }
    }
}
