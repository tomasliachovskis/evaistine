<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Discount;
use App\Models\Product;
use App\Models\ProductFact;
use App\Models\ProductFactBatch;
use App\Models\Store;
use App\Services\ProductFactExtractor;
use App\Support\ProductFacts;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

// docs/product-facts.md
class ProductFactsTest extends TestCase
{
    use RefreshDatabase;

    private Store $store;

    private Category $category;

    protected function setUp(): void
    {
        parent::setUp();
        $this->store = Store::firstOrCreate(['name' => 'Apotheka'], ['slug' => 'apotheka']);
        $this->category = Category::create(['name' => 'Vitaminai ir maisto papildai', 'slug' => 'vitaminai-test']);
    }

    private function product(string $name, string $url): Product
    {
        $product = Product::create(['name' => $name, 'slug' => \Illuminate\Support\Str::slug($name), 'category_id' => $this->category->id]);
        Discount::create([
            'product_id' => $product->id,
            'store_id' => $this->store->id,
            'product_url' => $url,
            'discounted_price' => 9.99,
            'start_at' => now()->startOfWeek(),
            'end_at' => now()->endOfWeek(),
        ]);

        return $product;
    }

    public function test_endpoint_stores_links_and_resets_facts_on_change(): void
    {
        $product = $this->product('Vitaminas D3 4000 TV N90', 'https://www.apotheka.lt/d3');
        $payload = ['store' => 'Apotheka', 'pages' => [
            ['product_url' => 'https://www.apotheka.lt/d3', 'text' => 'Sudedamosios dalys: aliejus', 'attributes' => ['Kilmės šalis' => 'Lietuva']],
            ['product_url' => 'https://www.apotheka.lt/unknown', 'text' => 'Kita prekė'],
        ]];

        $this->postJson('/api/scrapers/product-facts', $payload)->assertOk()->assertJson(['created' => 2]);

        $fact = ProductFact::where('product_url', 'https://www.apotheka.lt/d3')->first();
        $this->assertSame($product->id, $fact->product_id);
        $this->assertSame(['Kilmės šalis' => 'Lietuva'], $fact->attributes);
        $this->assertNull(ProductFact::where('product_url', 'https://www.apotheka.lt/unknown')->value('product_id'));

        $fact->update(['facts' => ['kind' => 'maisto_papildas'], 'extracted_at' => now()]);

        // The same page again keeps the facts; a changed page clears them.
        $this->postJson('/api/scrapers/product-facts', $payload)->assertJson(['created' => 0, 'changed' => 0]);
        $this->assertNotNull($fact->fresh()->facts);

        $payload['pages'][0]['text'] = 'Sudedamosios dalys: kitas aliejus';
        $this->postJson('/api/scrapers/product-facts', $payload)->assertJson(['changed' => 1]);
        $this->assertNull($fact->fresh()->facts);
        $this->assertNull($fact->fresh()->extracted_at);
    }

    public function test_production_stores_pushed_facts(): void
    {
        $product = $this->product('Vitaminas D3 4000 TV N90', 'https://www.apotheka.lt/d3');

        $this->postJson('/api/scrapers/product-facts', ['store' => 'Apotheka', 'pages' => [[
            'product_url' => 'https://www.apotheka.lt/d3', 'text' => 'Sudedamosios dalys: aliejus',
            'name' => 'OILESEN D3', 'category' => 'Prekės / Sveikata',
            'facts' => ['kind' => 'maisto_papildas', 'pack_units' => 60], 'extracted_with' => 'gpt-5-mini',
        ]]])->assertOk();

        $fact = ProductFact::first();
        $this->assertSame($product->id, $fact->product_id);
        $this->assertSame(60, $fact->facts['pack_units']);
        $this->assertSame('gpt-5-mini', $fact->extracted_with);
        $this->assertSame('Prekės / Sveikata', $fact->page_category);
        $this->assertNotNull($fact->extracted_at);
    }

    public function test_pending_lists_each_page_once(): void
    {
        $withFacts = $this->product('Turi faktus', 'https://www.apotheka.lt/turi');
        $otherStoreOnly = $this->product('Faktai tik iš kitos vaistinės', 'https://www.apotheka.lt/kita');
        $this->product('Bandyta, tuščia', 'https://www.apotheka.lt/tuscia');
        $this->product('Be faktų, be istorijos', 'https://www.apotheka.lt/be');
        $once = $this->product('Be faktų, viena akcija', 'https://www.apotheka.lt/viena');
        \App\Models\DiscountHistory::create(['product_id' => $once->id, 'store_id' => $this->store->id,
            'product_url' => 'https://www.apotheka.lt/viena', 'discounted_price' => 5, 'start_at' => now()->subWeek(), 'end_at' => now()->subWeek()->addDays(6)]);
        $twice = $this->product('Be faktų, dvi akcijos', 'https://www.apotheka.lt/dvi');
        foreach (range(1, 2) as $week) {
            \App\Models\DiscountHistory::create(['product_id' => $twice->id, 'store_id' => $this->store->id,
                'product_url' => 'https://www.apotheka.lt/dvi', 'discounted_price' => 5, 'start_at' => now()->subWeeks($week), 'end_at' => now()->subWeeks($week)->addDays(6)]);
        }
        $popular = $this->product('Be faktų, dažnai akcijoje', 'https://www.apotheka.lt/populiari');
        foreach (range(1, 3) as $week) {
            \App\Models\DiscountHistory::create(['product_id' => $popular->id, 'store_id' => $this->store->id,
                'product_url' => 'https://www.apotheka.lt/populiari', 'discounted_price' => 5, 'start_at' => now()->subWeeks($week), 'end_at' => now()->subWeeks($week)->addDays(6)]);
        }
        $other = Store::firstOrCreate(['name' => 'Camelia'], ['slug' => 'camelia']);
        $base = ['source_hash' => 'x', 'fetched_at' => now(), 'extracted_at' => now()];
        ProductFact::create($base + ['store_id' => $this->store->id, 'product_id' => $withFacts->id, 'facts' => ['schema' => 1, 'kind' => 'kosmetika'],
            'product_url' => 'https://www.apotheka.lt/turi', 'url_hash' => sha1('https://www.apotheka.lt/turi')]);
        ProductFact::create($base + ['store_id' => $other->id, 'product_id' => $otherStoreOnly->id, 'facts' => ['schema' => 1, 'kind' => 'kosmetika'],
            'product_url' => 'https://camelia.lt/p/kita', 'url_hash' => sha1('https://camelia.lt/p/kita')]);
        // Tried, the page had nothing: not again.
        ProductFact::create($base + ['store_id' => $this->store->id, 'facts' => ['schema' => 1],
            'product_url' => 'https://www.apotheka.lt/tuscia', 'url_hash' => sha1('https://www.apotheka.lt/tuscia')]);

        $this->getJson('/api/scrapers/product-facts/pending/Apotheka')
            ->assertOk()
            ->assertJson([
                'active_offers' => 7,
                'products' => 7,
                'products_with_facts' => 2,
                // Fewer than 2 history rows: not yet. More history first.
                'pending' => 2,
                'urls' => ['https://www.apotheka.lt/populiari', 'https://www.apotheka.lt/dvi'],
            ]);
    }

    public function test_push_sends_extracted_rows_once(): void
    {
        config(['services.scrapers.api_url' => 'https://evaistine.test/api/scrapers']);
        Http::fake(['evaistine.test/*' => Http::response(['ok' => true])]);
        $base = ['store_id' => $this->store->id, 'source_hash' => 'x', 'source_text' => 't'];
        ProductFact::create($base + ['product_url' => 'a', 'url_hash' => sha1('a'), 'facts' => ['kind' => 'kosmetika'], 'extracted_at' => now()]);
        ProductFact::create($base + ['product_url' => 'b', 'url_hash' => sha1('b')]);

        $this->artisan('product-facts:push')->assertSuccessful();
        Http::assertSentCount(1);
        Http::assertSent(fn ($request) => $request->url() === 'https://evaistine.test/api/scrapers/product-facts'
            && $request['store'] === 'Apotheka'
            && count($request['pages']) === 1
            && $request['pages'][0]['facts'] === ['kind' => 'kosmetika']);

        // Nothing new: nothing sent again.
        $this->artisan('product-facts:push')->assertSuccessful();
        Http::assertSentCount(1);
    }

    public function test_extract_command_uses_openai_and_gemini_answers(): void
    {
        // No product in the local DB: the page's own name and breadcrumb are enough.
        ProductFact::create([
            'store_id' => $this->store->id, 'product_url' => 'https://www.apotheka.lt/d3', 'url_hash' => sha1('https://www.apotheka.lt/d3'),
            'page_name' => 'OILESEN D3', 'page_category' => 'Prekės / Sveikata',
            'source_text' => 'Vienoje kapsulėje: vitaminas D3 100 µg', 'source_hash' => 'x',
        ]);
        Http::fake([
            'api.openai.com/*' => Http::response([
                'choices' => [['message' => ['content' => json_encode(['kind' => 'maisto_papildas', 'pack_units' => 90])]]],
                'usage' => ['prompt_tokens' => 1000, 'completion_tokens' => 100],
            ]),
            'generativelanguage.googleapis.com/*' => Http::response([
                'candidates' => [['content' => ['parts' => [['text' => json_encode(['kind' => 'maisto_papildas'])]]]]],
                'usageMetadata' => ['promptTokenCount' => 900, 'candidatesTokenCount' => 80],
            ]),
        ]);

        // A page that gave nothing is closed without a model call.
        ProductFact::create([
            'store_id' => $this->store->id, 'product_url' => 'gone', 'url_hash' => sha1('gone'), 'source_hash' => 'y',
        ]);

        $this->artisan('product-facts:extract', ['--model' => 'gpt-5-mini'])->assertSuccessful();

        Http::assertSentCount(1);
        $this->assertSame('none', ProductFact::where('product_url', 'gone')->value('extracted_with'));

        Http::assertSent(fn ($request) => str_contains($request->url(), 'openai')
            && str_contains($request['messages'][1]['content'], 'Category: Prekės / Sveikata'));
        $fact = ProductFact::where('product_url', 'https://www.apotheka.lt/d3')->first();
        $this->assertSame(90, $fact->facts['pack_units']);
        $this->assertSame('gpt-5-mini', $fact->extracted_with);

        $result = app(ProductFactExtractor::class)->extract($fact, 'gemini-3.5-flash-lite');
        $this->assertSame('maisto_papildas', $result['facts']['kind']);
        $this->assertSame(80, $result['output_tokens']);
    }

    public function test_highlights_from_merged_facts(): void
    {
        $product = $this->product('Vitaminas D3 4000 TV N90', 'https://www.apotheka.lt/d3');
        $base = ['store_id' => $this->store->id, 'product_id' => $product->id, 'source_hash' => 'x', 'extracted_at' => now()];
        ProductFact::create($base + ['product_url' => 'a', 'url_hash' => sha1('a'), 'facts' => [
            'kind' => 'maisto_papildas', 'form' => 'kapsulės', 'pack_units' => 90, 'daily_units' => 1, 'min_age_years' => 12,
            'amounts_basis' => '1 kapsulėje',
            'amounts' => [['name' => 'Vitaminas D3', 'amount' => 100, 'unit' => 'µg', 'alt' => '4000 TV', 'rmv_percent' => 2000]],
            'ingredients' => 'Alyvuogių aliejus, želatina, cholekalciferolis.',
            'origin_country' => null, 'active_substances' => [], 'allergens' => [],
            // Free-text lists as the model returned them in the sample.
            'free_from' => ['be parabenų', 'nekemša porų'],
            'suitable_for' => ['tinka ir ypač jautriai odai'],
            'manufacturer' => 'Pierre Fabre, Les Cauquilous, 81500 Lavaur',
        ]]);
        // A second pharmacy's page fills what the first lacks.
        ProductFact::create($base + ['product_url' => 'b', 'url_hash' => sha1('b'), 'facts' => [
            'kind' => 'maisto_papildas', 'origin_country' => 'Lietuva',
        ]]);

        $facts = ProductFacts::forProduct($product->id);

        $this->assertContains('Forma: kapsulės, pakuotėje 90 vnt.', $facts['highlights']);
        $this->assertContains('Pakuotės užtenka 90 dienų, vartojant pagal nurodytą paros dozę (1 per dieną).', $facts['highlights']);
        $this->assertContains('Skirta nuo 12 metų.', $facts['highlights']);
        $this->assertContains('Kilmės šalis: Lietuva.', $facts['highlights']);
        $this->assertContains('Gamintojas: Pierre Fabre.', $facts['highlights']);
        $this->assertContains('Tinka jautriai odai.', $facts['highlights']);
        $this->assertContains('Be parabenų.', $facts['highlights']);
        $this->assertSame(['name' => 'Vitaminas D3', 'amount' => '100 µg (4000 TV)', 'rmv' => '2000 %'], $facts['amounts']['rows'][0]);
        $this->assertSame('Alyvuogių aliejus, želatina, cholekalciferolis.', $facts['ingredients']);
        // Nothing about prices: those are the price signal, history and FAQ.
        $this->assertEmpty(array_filter($facts['highlights'], fn ($line) => str_contains($line, '€')));
    }

    public function test_medicine_lists_others_with_the_same_substance_and_strength(): void
    {
        $ibuprom = $this->product('IBUPROM 400 mg N20', 'https://www.apotheka.lt/ibuprom');
        $lannacher = $this->product('Ibuprofen Lannacher 400 mg N10', 'https://www.apotheka.lt/lannacher');
        $ibuprofen200 = $this->product('Ibuprofen 200 mg N10', 'https://www.apotheka.lt/200');
        foreach ([[$ibuprom, '400 mg'], [$lannacher, '400 MG'], [$ibuprofen200, '200 mg']] as $i => [$product, $strength]) {
            ProductFact::create([
                'store_id' => $this->store->id, 'product_id' => $product->id, 'product_url' => "u{$i}", 'url_hash' => sha1("u{$i}"),
                'source_hash' => 'x', 'extracted_at' => now(),
                'facts' => ['kind' => 'vaistas', 'form' => 'plėvele dengtos tabletės', 'daily_units' => 3,
                    'active_substances' => [['name' => 'Ibuprofenas', 'strength' => $strength]]],
            ]);
        }

        $facts = ProductFacts::forProduct($ibuprom->id);

        $this->assertContains('Veiklioji medžiaga: Ibuprofenas (400 mg).', $facts['highlights']);
        $this->assertSame(['Ibuprofen Lannacher 400 mg N10'], array_column($facts['same_substance']['products'], 'name'));
        // No dosing for medicines, even when the model filled it in.
        $this->assertEmpty(array_filter($facts['highlights'], fn ($line) => str_contains($line, 'užtenka')));
    }

    private function pageRow(string $url, string $hash = 'x'): ProductFact
    {
        return ProductFact::create([
            'store_id' => $this->store->id, 'product_url' => $url, 'url_hash' => sha1($url),
            'page_name' => "Page {$url}", 'page_category' => 'Prekės / Sveikata',
            'source_text' => 'Vienoje kapsulėje: vitaminas D3 100 µg', 'source_hash' => $hash,
        ]);
    }

    private string $batchState = 'JOB_STATE_PENDING';

    private string $batchResults = '';

    // The job's state and results are read when a request is made, so a test
    // can move the job on without a second Http::fake (stubs only append).
    private function fakeGeminiBatch(): void
    {
        Http::fake([
            'generativelanguage.googleapis.com/upload/*' => Http::response([], 200, ['x-goog-upload-url' => 'https://upload.test/session']),
            'upload.test/*' => Http::response(['file' => ['name' => 'files/in1']]),
            'generativelanguage.googleapis.com/v1beta/models/*' => Http::response(['name' => 'batches/b1', 'metadata' => ['state' => 'JOB_STATE_PENDING']]),
            'generativelanguage.googleapis.com/v1beta/batches/*' => fn () => Http::response(['name' => 'batches/b1', 'metadata' => ['state' => $this->batchState], 'response' => ['responsesFile' => 'files/out1']]),
            'generativelanguage.googleapis.com/download/*' => fn () => Http::response($this->batchResults),
            'generativelanguage.googleapis.com/v1beta/files/*' => Http::response([]),
        ]);
    }

    private function batchAnswer(ProductFact $row, ?array $facts, ?string $hash = null): string
    {
        $line = ['key' => $row->id . ':' . ($hash ?? $row->source_hash)];
        if ($facts === null) {
            $line['error'] = ['code' => 13, 'message' => 'internal'];
        } else {
            $line['response'] = [
                'candidates' => [['content' => ['parts' => [['text' => json_encode($facts)]]]]],
                'usageMetadata' => ['promptTokenCount' => 1000, 'candidatesTokenCount' => 100],
            ];
        }

        return json_encode($line);
    }

    public function test_batch_submit_claims_rows_and_collect_writes_facts(): void
    {
        $done = $this->pageRow('https://www.apotheka.lt/a');
        $changed = $this->pageRow('https://www.apotheka.lt/b');
        $failed = $this->pageRow('https://www.apotheka.lt/c');
        $missing = $this->pageRow('https://www.apotheka.lt/d');
        $this->fakeGeminiBatch();

        $this->artisan('product-facts:batch-submit', ['--model' => 'gemini-3.5-flash-lite'])->assertSuccessful();

        $batch = ProductFactBatch::sole();
        $this->assertSame('batches/b1', $batch->name);
        $this->assertSame(4, $batch->row_count);
        $this->assertSame(4, ProductFact::where('batch_id', $batch->id)->count());
        Http::assertSent(function ($request) use ($done) {
            if (!str_contains($request->url(), 'upload.test')) {
                return false;
            }
            $line = json_decode(explode("\n", $request->body())[0], true);

            return $line['key'] === "{$done->id}:x"
                && str_contains($line['request']['systemInstruction']['parts'][0]['text'], 'Lithuanian pharmacy')
                && str_contains($line['request']['contents'][0]['parts'][0]['text'], 'Category: Prekės / Sveikata');
        });
        Http::assertSent(fn ($request) => str_contains($request->url(), 'gemini-3.5-flash-lite:batchGenerateContent')
            && $request['batch']['input_config']['file_name'] === 'files/in1');

        // Claimed rows are left to the batch: no second job, no sync call.
        $this->artisan('product-facts:extract', ['--model' => 'gemini-3.5-flash-lite'])->assertSuccessful();
        $this->artisan('product-facts:batch-submit', ['--model' => 'gemini-3.5-flash-lite'])->assertSuccessful();
        $this->assertSame(1, ProductFactBatch::count());

        // Still running: nothing written.
        $this->artisan('product-facts:batch-collect')->assertSuccessful();
        $this->assertNull($batch->fresh()->finished_at);

        // The page of "b" changed while the job ran.
        $changed->update(['source_hash' => 'new']);
        $this->batchState = 'JOB_STATE_SUCCEEDED';
        $this->batchResults = implode("\n", [
            $this->batchAnswer($done, ['kind' => 'maisto_papildas', 'pack_units' => 60]),
            $this->batchAnswer($changed, ['kind' => 'kosmetika'], 'x'),
            $this->batchAnswer($failed, null),
        ]) . "\n";

        $this->artisan('product-facts:batch-collect')->assertSuccessful();

        $done->refresh();
        $this->assertSame(60, $done->facts['pack_units']);
        $this->assertSame(ProductFactExtractor::SCHEMA_VERSION, $done->facts['schema']);
        $this->assertSame('gemini-3.5-flash-lite', $done->extracted_with);
        $this->assertNull($done->batch_id);
        foreach ([$changed, $failed, $missing] as $row) {
            $row->refresh();
            $this->assertNull($row->batch_id);
            $this->assertNull($row->extracted_at);
        }
        $batch->refresh();
        $this->assertSame('JOB_STATE_SUCCEEDED', $batch->state);
        $this->assertNotNull($batch->finished_at);
        $this->assertSame(1000, $batch->input_tokens);
        $this->assertSame(100, $batch->output_tokens);
    }

    public function test_expired_batch_gives_its_rows_back(): void
    {
        $row = $this->pageRow('https://www.apotheka.lt/a');
        $this->batchState = 'JOB_STATE_EXPIRED';
        $this->fakeGeminiBatch();
        $this->artisan('product-facts:batch-submit', ['--model' => 'gemini-3.5-flash-lite'])->assertSuccessful();
        $this->assertNotNull($row->fresh()->batch_id);

        $this->artisan('product-facts:batch-collect')->assertSuccessful();

        $this->assertNull($row->fresh()->batch_id);
        $this->assertSame('JOB_STATE_EXPIRED', ProductFactBatch::sole()->state);
    }

    public function test_batch_submit_refuses_non_gemini_models(): void
    {
        $this->pageRow('https://www.apotheka.lt/a');
        Http::fake();

        $this->artisan('product-facts:batch-submit', ['--model' => 'gpt-5-mini'])->assertFailed();
        Http::assertNothingSent();
    }
}
