<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Discount;
use App\Models\Product;
use App\Models\ProductMapping;
use App\Models\Store;
use App\Services\ProductDuplicateQueryService;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class MergeDuplicateProductsCommandTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config(['database.default' => 'sqlite']);
        config(['database.connections.sqlite.database' => ':memory:']);

        $this->createSchema();
    }

    protected function tearDown(): void
    {
        Schema::dropIfExists('product_mapping');
        Schema::dropIfExists('product_favorites');
        Schema::dropIfExists('discount_histories');
        Schema::dropIfExists('discounts');
        Schema::dropIfExists('products');
        Schema::dropIfExists('stores');
        Schema::dropIfExists('categories');

        parent::tearDown();
    }

    public function test_dry_run_does_not_merge_or_create_mapping(): void
    {
        ['base' => $base, 'duplicate' => $duplicate] = $this->createDuplicatePair();

        $this->mockDuplicatePairs($base, $duplicate);

        $this->artisan('products:merge-duplicates', ['--dry-run' => true])
            ->assertExitCode(0)
            ->expectsOutputToContain('Dry run');

        $this->assertDatabaseHas('products', ['id' => $duplicate->id]);
        $this->assertDatabaseMissing('product_mapping', [
            'name' => $duplicate->name,
            'product_id' => $base->id,
        ]);
    }

    public function test_merge_moves_discount_and_creates_mapping(): void
    {
        ['base' => $base, 'duplicate' => $duplicate, 'store' => $store] = $this->createDuplicatePair();

        $discount = Discount::withoutEvents(function () use ($duplicate, $store) {
            return Discount::create([
                'product_id' => $duplicate->id,
                'store_id' => $store->id,
                'product_url' => 'https://example.com/product',
                'original_price' => 10.00,
                'discounted_price' => 8.00,
                'discount_percent' => 20,
                'start_at' => now()->subDay(),
                'end_at' => now()->addWeek(),
            ]);
        });

        $this->mockDuplicatePairs($base, $duplicate);

        $this->artisan('products:merge-duplicates')
            ->assertExitCode(0)
            ->expectsOutputToContain('Merged 1');

        $this->assertDatabaseMissing('products', ['id' => $duplicate->id]);
        $this->assertDatabaseHas('product_mapping', [
            'name' => $duplicate->name,
            'product_id' => $base->id,
        ]);
        $this->assertDatabaseHas('discounts', [
            'id' => $discount->id,
            'product_id' => $base->id,
        ]);
    }

    public function test_merge_prefers_non_flyer_older_product_as_base(): void
    {
        ['base' => $base, 'duplicate' => $duplicate] = $this->createDuplicatePair();

        $this->mockDuplicatePairs($duplicate, $base);

        $this->artisan('products:merge-duplicates')
            ->assertExitCode(0);

        $this->assertDatabaseHas('product_mapping', [
            'name' => $duplicate->name,
            'product_id' => $base->id,
        ]);
        $this->assertDatabaseMissing('products', ['id' => $duplicate->id]);
    }

    public function test_cross_source_keeps_older_flyer_product_with_web_image_and_web_offer(): void
    {
        $category = Category::create(['name' => 'Šaldyti', 'slug' => 'saldyti']);
        $store = Store::forceCreate(['name' => 'Rimi', 'slug' => 'rimi', 'url' => 'https://rimi.lt']);

        $flyer = Product::create([
            'name' => 'Šaldytos bulvių lazdelės NATALI, 1 kg',
            'slug' => 'saldytos-bulviu-lazdeles-natali-1-kg',
            'category_id' => $category->id,
            'image_from_flyer' => true,
            'image_url' => 'https://evaistine.lt/storage/flyer-crop.jpg',
            'created_at' => now()->subDays(10),
        ]);

        $web = Product::create([
            'name' => 'Šald.bulvių lazdelės NATALI (2 rūš.), 1 kg',
            'slug' => 'saldbulviu-lazdeles-natali-2-rus-1-kg',
            'category_id' => $category->id,
            'image_from_flyer' => false,
            'image_url' => 'https://rimibaltic-res.cloudinary.com/natali.jpg',
            'created_at' => now()->subDay(),
        ]);

        $offer = [
            'store_id' => $store->id,
            'original_price' => 2.49,
            'discounted_price' => 1.36,
            'discount_percent' => 45,
            'start_at' => now()->startOfWeek(),
            'end_at' => now()->endOfWeek(),
        ];

        [$flyerDiscount, $webDiscount] = Discount::withoutEvents(fn () => [
            Discount::create($offer + ['product_id' => $flyer->id, 'product_url' => null, 'info' => '(2 rūšių)']),
            Discount::create($offer + ['product_id' => $web->id, 'product_url' => 'https://rimi.lt/natali', 'info' => null]),
        ]);

        // Stale rows from earlier merges: one pointing the web name at the
        // web product, one mapping an older name to it.
        ProductMapping::create(['name' => $web->name, 'product_id' => $web->id]);
        ProductMapping::create(['name' => 'Old web name', 'product_id' => $web->id]);

        $this->artisan('products:merge-duplicates', ['--cross-source' => true])
            ->assertExitCode(0)
            ->expectsOutputToContain('Merged 1');

        $this->assertDatabaseMissing('products', ['id' => $web->id]);
        $this->assertDatabaseHas('products', [
            'id' => $flyer->id,
            'image_url' => 'https://rimibaltic-res.cloudinary.com/natali.jpg',
            'image_from_flyer' => false,
        ]);
        // The web name abbreviates ("Šald."), so the flyer wording stays,
        // and both source names resolve to the survivor.
        $this->assertDatabaseHas('products', ['id' => $flyer->id, 'name' => 'Šaldytos bulvių lazdelės NATALI, 1 kg']);
        $this->assertDatabaseHas('product_mapping', ['name' => $web->name, 'product_id' => $flyer->id]);
        $this->assertDatabaseHas('product_mapping', ['name' => $flyer->name, 'product_id' => $flyer->id]);
        $this->assertDatabaseHas('product_mapping', ['name' => 'Old web name', 'product_id' => $flyer->id]);
        $this->assertDatabaseMissing('product_mapping', ['name' => $web->name, 'product_id' => $web->id]);
        // The web offer is the one that stays visible; it picks up the
        // flyer's "(2 rūšių)" the survivor's name no longer carries.
        $this->assertDatabaseHas('discounts', ['id' => $webDiscount->id, 'product_id' => $flyer->id, 'info' => '(2 rūšių)']);
        // The leftover same-week flyer offer is dropped by
        // DuplicateDiscountRemover in FinalizeScrapedStoresJob (MySQL-only
        // SQL, not runnable on this sqlite schema).
        $this->assertDatabaseHas('discounts', ['id' => $flyerDiscount->id, 'product_id' => $flyer->id]);
    }

    private function createSchema(): void
    {
        Schema::create('categories', function ($table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->timestamps();
        });

        Schema::create('stores', function ($table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('url');
            $table->timestamps();
        });

        Schema::create('products', function ($table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->foreignId('category_id')->constrained('categories');
            $table->boolean('image_from_flyer')->default(false);
            $table->string('image_url')->nullable();
            $table->timestamp('image_cache_failed_at')->nullable();
            $table->timestamps();
        });

        Schema::create('discounts', function ($table) {
            $table->id();
            $table->foreignId('product_id')->constrained('products');
            $table->foreignId('store_id')->constrained('stores');
            $table->string('product_url')->nullable();
            $table->decimal('original_price', 10, 2);
            $table->decimal('discounted_price', 10, 2);
            $table->integer('discount_percent');
            $table->string('info')->nullable();
            $table->timestamp('start_at');
            $table->timestamp('end_at');
            $table->timestamps();
        });

        Schema::create('discount_histories', function ($table) {
            $table->id();
            $table->foreignId('product_id')->constrained('products');
            $table->foreignId('store_id')->constrained('stores');
            $table->string('product_url')->nullable();
            $table->decimal('original_price', 10, 2)->nullable();
            $table->decimal('discounted_price', 10, 2)->nullable();
            $table->integer('discount_percent')->nullable();
            $table->timestamp('start_at')->nullable();
            $table->timestamp('end_at')->nullable();
            $table->timestamps();
        });

        Schema::create('product_favorites', function ($table) {
            $table->id();
            $table->foreignId('product_id')->constrained('products');
            $table->unsignedBigInteger('user_id');
            $table->timestamps();
            $table->unique(['product_id', 'user_id']);
        });

        Schema::create('product_mapping', function ($table) {
            $table->id();
            $table->string('name');
            $table->foreignId('product_id')->constrained('products');
            $table->timestamps();
        });
    }

    private function createDuplicatePair(): array
    {
        $category = Category::create([
            'name' => 'Kava',
            'slug' => 'kava',
        ]);

        $store = Store::forceCreate([
            'name' => 'Maxima',
            'slug' => 'maxima',
            'url' => 'https://maxima.lt',
        ]);

        $base = Product::create([
            'name' => 'Tirpi kava, nequick',
            'slug' => 'tirpi-kava-nequick-base',
            'category_id' => $category->id,
            'image_from_flyer' => false,
            'created_at' => now()->subDays(2),
        ]);

        $duplicate = Product::create([
            'name' => 'Tirpioji kava, nequick',
            'slug' => 'tirpioji-kava-nequick-dup',
            'category_id' => $category->id,
            'image_from_flyer' => true,
            'created_at' => now()->subDay(),
        ]);

        return compact('base', 'duplicate', 'store', 'category');
    }

    private function mockDuplicatePairs(Product $first, Product $second): void
    {
        $id1 = min($first->id, $second->id);
        $id2 = max($first->id, $second->id);

        $this->mock(ProductDuplicateQueryService::class, function ($mock) use ($id1, $id2, $first, $second) {
            $mock->shouldReceive('getDuplicatePairs')
                ->once()
                ->andReturn(collect([(object) [
                    'id1' => $id1,
                    'id2' => $id2,
                    'name1' => $id1 === $first->id ? $first->name : $second->name,
                    'name2' => $id2 === $second->id ? $second->name : $first->name,
                ]]));
        });
    }
}
