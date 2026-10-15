<?php

namespace Tests\Feature;

use App\Models\Store;
use App\Models\StoreFlyer;
use App\Services\CollectionHealthService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Tests\TestCase;

// Series below are real prod counts from 2026-09-28 → 2026-10-12 (see
// docs/collection-health-plan.md), so the rules are checked against the
// anomalies they were written for.
class CollectionHealthServiceTest extends TestCase
{
    use RefreshDatabase;

    private const LIDL = [149, 149, 149, 0, 144, 0, 0, 12, 23, 27, 62, 62, 0, 128, 68];

    private const IKI = [369, 369, 369, 0, 364, 364, 0, 383, 388, 388, 388, 388, 0, 388, 374];

    private const NORFA = [328, 331, 713, 0, 407, 407, 0, 380, 384, 388, 388, 404, 0, 402, 379];

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-10-12 12:00:00');

        // The real discount_temp has a plain `store` name column that no
        // migration creates (the create migration still has store_id), and
        // its prices and dates are varchar: scrapers' raw strings land as is.
        if (!Schema::hasColumn('discount_temp', 'store')) {
            Schema::table('discount_temp', function (Blueprint $table) {
                $table->string('store')->nullable();
                $table->unsignedBigInteger('store_id')->nullable()->change();
            });
        }
        if (Schema::getColumnType('discount_temp', 'start_at') !== 'varchar') {
            Schema::table('discount_temp', function (Blueprint $table) {
                foreach (['original_price', 'discounted_price', 'start_at', 'end_at'] as $column) {
                    $table->string($column)->nullable()->change();
                }
            });
        }

        config([
            'scrapers' => ['Lidl' => 'lidl.js', 'Iki' => 'iki.js', 'Norfa' => 'norfa.js'],
            'flyer_scrapers' => ['Jupoja' => 'jupoja.js', 'Čia' => 'cia.js', 'Kubas' => 'kubas.js', 'Grustė' => 'gruste.js', 'Maxima' => 'maxima.js'],
        ]);

        foreach (['Lidl', 'Iki', 'Norfa', 'Jupoja', 'Čia', 'Kubas', 'Grustė', 'Maxima'] as $name) {
            Store::firstOrCreate(['name' => $name], ['slug' => Str::slug($name)]);
        }

        // The column defaults to true; real leaflet-only stores have it off.
        Store::query()->update(['extract_discounts_from_flyer' => false]);

        $this->seedSeries('Lidl', self::LIDL);
        $this->seedSeries('Iki', self::IKI);
        // Norfa sends no product_url, and ran twice today: still one count
        // per product.
        $this->seedSeries('norfa', self::NORFA, withUrl: false);
        $this->seedRows('Norfa', 379, Carbon::today()->setTime(10, 0), withUrl: false);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_eshop_counts_unique_products_and_flags_lidl_drop(): void
    {
        $report = app(CollectionHealthService::class)->report();
        $rows = collect($report['eshop'])->keyBy('store');

        $this->assertSame(self::NORFA, $rows['Norfa']['series']);
        $this->assertFalse($rows['Iki']['is_anomaly']);
        $this->assertFalse($rows['Norfa']['is_anomaly']);

        $this->assertTrue($rows['Lidl']['is_anomaly']);
        $this->assertSame(68, $rows['Lidl']['latest']);
        $this->assertSame(149, $rows['Lidl']['baseline']);
        $this->assertSame('−54 %', CollectionHealthService::formatDelta($rows['Lidl']['delta']));

        $lidl = $this->issueFor($report, 'Lidl', 'E-shop');
        $this->assertSame(CollectionHealthService::WARNING, $lidl['severity']);
    }

    public function test_leaflet_rows_in_discount_temp_are_not_counted_as_eshop(): void
    {
        $flyer = $this->flyer('Lidl', '2026-10-12', '2026-10-18');
        $this->seedRows('Lidl', 425, Carbon::today()->setTime(9, 0), flyerId: $flyer->id);

        $rows = collect(app(CollectionHealthService::class)->report()['eshop'])->keyBy('store');

        $this->assertSame(68, $rows['Lidl']['latest']);
    }

    public function test_day_without_eshop_rows_is_a_skipped_run(): void
    {
        $issue = $this->issueFor(app(CollectionHealthService::class)->report(), 'visos e-shop');

        $this->assertSame(CollectionHealthService::CRITICAL, $issue['severity']);
        $this->assertStringStartsWith('10-10 duomenis atsiuntė tik 0 iš 3', $issue['message']);
        $this->assertStringContainsString('Taip pat 10-01, 10-04', $issue['message']);
    }

    public function test_store_without_recent_rows_is_critical(): void
    {
        DB::table('discount_temp')->where('store', 'Iki')->where('created_at', '>=', Carbon::today()->subDay())->delete();

        $issue = $this->issueFor(app(CollectionHealthService::class)->report(), 'Iki', 'neatėjo');

        $this->assertSame(CollectionHealthService::CRITICAL, $issue['severity']);
    }

    public function test_leaflet_rules(): void
    {
        $this->flyer('Jupoja', '2026-09-18', '2026-10-05', createdAt: '2026-09-18');
        $this->flyer('Čia', '2026-09-29', '2026-10-12');
        $this->flyer('Kubas', '2026-09-29', '2026-10-12');
        Store::where('name', 'Grustė')->update(['extract_discounts_from_flyer' => true]);
        $this->flyer('Grustė', '2026-10-11', '2026-10-20', processedAt: '2026-10-12 09:00:00');
        $this->flyer('Maxima', '2026-10-06', '2026-11-02');
        $this->flyer('Maxima', null, null);

        $report = app(CollectionHealthService::class)->report();

        $jupoja = $this->issueFor($report, 'Jupoja');
        $this->assertSame(CollectionHealthService::CRITICAL, $jupoja['severity']);
        $this->assertStringContainsString('baigė galioti 10-05', $jupoja['message']);

        // Same message for several stores is one row.
        $today = $this->issueFor($report, 'Čia, Kubas');
        $this->assertSame('Leidinys galioja tik iki šiandien, naujesnio nėra', $today['message']);

        $this->assertStringContainsString('Gemini apdorotas, bet 0 nuolaidų', $this->issueFor($report, 'Grustė', 'Gemini apdorotas')['message']);
        $this->assertStringContainsString('be valid_to', $this->issueFor($report, 'Maxima')['message']);

        $this->assertSame(CollectionHealthService::CRITICAL, $report['issues'][0]['severity']);
    }

    public function test_rows_without_readable_dates_are_critical(): void
    {
        // Norfa 2026-10-15: the site changed its date format and every row
        // came in as "Invalid Date", which discounts:process skips.
        DB::table('discount_temp')->where('store', 'Norfa')->where('created_at', '>=', Carbon::today()->setTime(9, 0))
            ->update(['start_at' => 'Invalid Date', 'end_at' => null]);

        $report = app(CollectionHealthService::class)->report();
        $norfa = collect($report['eshop'])->firstWhere('store', 'Norfa');

        $this->assertSame(379, $norfa['last_24h']['no_dates']);
        $issue = $this->issueFor($report, 'Norfa', 'be galiojimo datų');
        $this->assertSame(CollectionHealthService::CRITICAL, $issue['severity']);
        $this->assertStringStartsWith('Per 24 val. 379 iš 758', $issue['message']);
    }

    public function test_expired_and_priceless_rows_are_flagged(): void
    {
        DB::table('discount_temp')->where('store', 'Iki')->where('created_at', '>=', Carbon::today())
            ->update(['end_at' => '2025-10-19', 'discounted_price' => '0,00']);

        $report = app(CollectionHealthService::class)->report();

        $this->assertSame(CollectionHealthService::WARNING, $this->issueFor($report, 'Iki', 'jau pasibaigusios')['severity']);
        $this->assertSame(CollectionHealthService::CRITICAL, $this->issueFor($report, 'Iki', 'be kainos')['severity']);
        $this->assertNull(collect($report['issues'])->first(fn (array $i) => $i['store'] === 'Lidl' && str_contains($i['message'], 'Per 24 val.')));
    }

    public function test_arrived_vs_active_discounts(): void
    {
        $iki = Store::where('name', 'Iki')->value('id');
        $lidl = Store::where('name', 'Lidl')->value('id');
        $this->seedDiscounts($iki, 100, createdAt: now()->subDays(3));
        $this->seedDiscounts($lidl, 60, createdAt: now()->subHours(2));

        $report = app(CollectionHealthService::class)->report();
        $rows = collect($report['eshop'])->keyBy('store');

        $this->assertSame(['rows' => 374, 'products' => 374, 'no_dates' => 0, 'expired' => 0, 'no_price' => 0, 'created' => 0], $rows['Iki']['last_24h']);
        $this->assertSame(60, $rows['Lidl']['last_24h']['created']);

        $this->assertStringContainsString('atėjo 374 prekių, bet aktyvių e-shop nuolaidų tik 100 (27 %)', $this->issueFor($report, 'Iki', 'nepridedamos')['message']);
        // Lidl: 60 active of 68 arrived is fine.
        $this->assertNull(collect($report['issues'])->first(fn (array $i) => $i['store'] === 'Lidl' && str_contains($i['message'], 'nepridedamos')));
        // Norfa has no discounts at all.
        $this->assertSame(CollectionHealthService::CRITICAL, $this->issueFor($report, 'Norfa', '0 aktyvių e-shop nuolaidų')['severity']);
    }

    public function test_store_without_active_discounts_is_critical(): void
    {
        DB::table('discount_temp')->where('store', 'Iki')->where('created_at', '>=', Carbon::today()->subDay())->delete();
        Store::where('name', 'Grustė')->update(['extract_discounts_from_flyer' => true]);
        $this->flyer('Grustė', '2026-10-11', '2026-10-20');

        $report = app(CollectionHealthService::class)->report();

        $this->assertSame(CollectionHealthService::CRITICAL, $this->issueFor($report, 'Iki', 'Nėra nė vienos aktyvios e-shop nuolaidos')['severity']);
        $grouped = collect($report['issues'])->first(fn (array $i) => str_contains($i['message'], 'Gemini ištraukimas iš leidinių įjungtas'));
        $this->assertSame('Grustė', $grouped['store']);
        $this->assertSame(CollectionHealthService::CRITICAL, $grouped['severity']);
        // Leaflet-only store without extraction never has discounts.
        $this->assertNull(collect($report['issues'])->first(fn (array $i) => str_contains($i['store'], 'Jupoja') && str_contains($i['message'], 'aktyvios')));
    }

    private function seedDiscounts(int $storeId, int $count, Carbon $createdAt): void
    {
        $categoryId = DB::table('categories')->insertGetId(['name' => 'Maistas', 'slug' => 'maistas-' . $storeId]);
        $productIds = collect(range(1, $count))->map(fn (int $n) => DB::table('products')->insertGetId([
            'category_id' => $categoryId,
            'name' => "Prekė {$storeId}-{$n}",
            'slug' => "preke-{$storeId}-{$n}",
            'created_at' => $createdAt,
            'updated_at' => $createdAt,
        ]));

        DB::table('discounts')->insert($productIds->map(fn (int $id) => [
            'product_id' => $id,
            'store_id' => $storeId,
            'original_price' => 2,
            'discounted_price' => 1,
            'discount_percent' => 50,
            'start_at' => '2026-10-10',
            'end_at' => '2026-10-20',
            'created_at' => $createdAt,
            'updated_at' => $createdAt,
        ])->all());
    }

    private function issueFor(array $report, string $store, string $messagePart = ''): array
    {
        $issue = collect($report['issues'])->first(fn (array $i) => $i['store'] === $store && str_contains($i['message'], $messagePart));
        $this->assertNotNull($issue, "No issue for {$store}: " . json_encode(collect($report['issues'])->pluck('message', 'store')));

        return $issue;
    }

    private function seedSeries(string $store, array $series, bool $withUrl = true): void
    {
        foreach ($series as $i => $count) {
            $this->seedRows($store, $count, Carbon::today()->subDays(count($series) - 1 - $i)->setTime(8, 30), $withUrl);
        }
    }

    private function seedRows(string $store, int $count, Carbon $at, bool $withUrl = true, ?int $flyerId = null): void
    {
        $rows = [];
        for ($n = 0; $n < $count; $n++) {
            $rows[] = [
                'name' => "{$store} prekė {$n}" . ($flyerId ? " leidinys" : ''),
                'store' => $store,
                'product_url' => $withUrl ? "https://example.test/{$store}/" . ($flyerId ? 'f' : '') . $n : null,
                'store_flyer_id' => $flyerId,
                'category' => 'Maisto prekės',
                'original_price' => 2,
                'discounted_price' => 1,
                'discount_percent' => 50,
                'start_at' => $at,
                'end_at' => $at->copy()->addWeek(),
                'processed' => true,
                'created_at' => $at,
                'updated_at' => $at,
            ];
        }

        foreach (array_chunk($rows, 500) as $chunk) {
            DB::table('discount_temp')->insert($chunk);
        }
    }

    private function flyer(string $store, ?string $from, ?string $to, ?string $createdAt = null, ?string $processedAt = null): StoreFlyer
    {
        return StoreFlyer::forceCreate([
            'store_id' => Store::where('name', $store)->value('id'),
            'slug' => Str::random(10),
            'valid_from' => $from,
            'valid_to' => $to,
            'processing_status' => StoreFlyer::STATUS_READY,
            'discounts_processed_at' => $processedAt,
            'created_at' => $createdAt ?? $from ?? '2026-10-01',
            'updated_at' => $createdAt ?? $from ?? '2026-10-01',
        ]);
    }
}
