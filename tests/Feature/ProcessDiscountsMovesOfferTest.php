<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\CategoryMapper;
use App\Models\Discount;
use App\Models\DiscountTemp;
use App\Models\Product;
use App\Models\Store;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

// A row that matches a different product than this week's existing offer for
// the same URL (it now carries an EAN) moves the offer, never adds a second one
// (2026-10-14: 2 320 such pairs in production after the first night).
class ProcessDiscountsMovesOfferTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_offer_moves_to_the_ean_matched_product(): void
    {
        Carbon::setTestNow('2026-10-14 08:00:00');

        $store = Store::firstOrCreate(['name' => 'Apotheka'], ['slug' => 'apotheka']);
        $category = Category::create(['name' => 'Medicinos prekės', 'slug' => 'medicinos-prekes-test']);
        CategoryMapper::create(['store_category' => 'Diagnostika', 'category_id' => $category->id, 'store' => $store->id]);

        $url = 'https://www.apotheka.lt/accu-check-instant-n50';
        $nameMatched = Product::create(['name' => 'ACCU-CHECK juostelės INSTANT, N50', 'slug' => 'accu-check-juosteles-instant-n50', 'category_id' => $category->id]);
        $eanProduct = Product::create(['name' => 'DIAGN. JUOST. ACCU-CHECK INSTANT N50', 'slug' => 'diagn-juost-accu-check-instant-n50', 'ean' => '4015630067480', 'category_id' => $category->id]);

        Discount::create([
            'product_id' => $nameMatched->id,
            'store_id' => $store->id,
            'product_url' => $url,
            'discounted_price' => 20.00,
            'start_at' => '2026-10-12 00:00:00',
            'end_at' => '2026-10-18 00:00:00',
        ]);

        DiscountTemp::create([
            'name' => 'ACCU-CHECK juostelės INSTANT, N50',
            'category' => 'Diagnostika',
            'product_url' => $url,
            'store' => 'Apotheka',
            'discounted_price' => '19.50',
            'ean' => '4015630067480',
            'start_at' => '2026-10-12',
            'end_at' => '2026-10-18',
        ]);

        $this->artisan('discounts:process', ['--only-store' => 'Apotheka'])->assertSuccessful();

        $offers = Discount::where('store_id', $store->id)->where('product_url', $url)->get();
        $this->assertCount(1, $offers);
        $this->assertSame($eanProduct->id, $offers->first()->product_id);
        $this->assertEquals(19.50, (float) $offers->first()->discounted_price);
    }
}
