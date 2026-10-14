<?php

namespace App\Support;

use App\Models\ProductFact;
use Illuminate\Support\Collection;

// The product page's "Apie produktą" block, built from the facts a model
// extracted from the pharmacies' product pages (product_facts.facts,
// App\Services\ProductFactExtractor). Only facts about the product itself:
// what and how much is in it, who it is for, where it is made. Nothing about
// prices, the page already covers those (price signal, history, FAQ).
// See docs/product-facts.md.
class ProductFacts
{
    private const KIND_MEDICINE = 'vaistas';

    /**
     * @return array{
     *     highlights: list<string>,
     *     amounts: ?array{basis: ?string, rows: list<array{name: string, amount: string, rmv: ?string}>},
     *     ingredients: ?string,
     *     same_substance: ?array{label: string, products: list<array{name: string, url: string}>},
     *     sources: list<string>
     * }|null
     */
    public static function forProduct(int $productId): ?array
    {
        $rows = ProductFact::with('store:id,name')
            ->where('product_id', $productId)
            ->whereNotNull('facts')
            ->get();

        if ($rows->isEmpty()) {
            return null;
        }

        $facts = self::merge($rows);
        $isMedicine = ($facts['kind'] ?? null) === self::KIND_MEDICINE;
        $highlights = [];

        if ($isMedicine && $facts['active_substances'] !== []) {
            $highlights[] = 'Veiklioji medžiaga: ' . collect($facts['active_substances'])
                ->map(fn ($s) => trim($s['name'] . (empty($s['strength']) ? '' : " ({$s['strength']})")))
                ->implode(', ') . '.';
        }
        if (!empty($facts['form'])) {
            $highlights[] = self::sentence('Forma: ' . $facts['form'] . self::packSuffix($facts));
        }
        if (!$isMedicine && ($days = self::packDays($facts)) !== null) {
            $highlights[] = "Pakuotės užtenka {$days} " . self::plural($days, 'dienai', 'dienoms', 'dienų')
                . ', vartojant pagal nurodytą paros dozę (' . self::number($facts['daily_units']) . ' per dieną).';
        }
        if (!$isMedicine && !empty($facts['min_age_years'])) {
            $highlights[] = 'Skirta nuo ' . self::number($facts['min_age_years']) . ' metų.';
        }
        if (!empty($facts['spf'])) {
            $highlights[] = 'Apsaugos nuo saulės faktorius SPF ' . self::number($facts['spf']) . '.';
        }
        if ($facts['key_ingredients'] !== []) {
            $highlights[] = 'Pagrindiniai ingredientai: ' . implode(', ', array_slice($facts['key_ingredients'], 0, 6)) . '.';
        }
        // Not for medicines: "tinka ..." next to a medicine reads as advice
        // ("Tinka cukriniu diabetu" on a cough syrup, 2026-10-14 sample).
        if (!$isMedicine && $facts['suitable_for'] !== []) {
            $highlights[] = self::ucfirst('tinka ' . implode(', ', $facts['suitable_for'])) . '.';
        }
        if ($facts['free_from'] !== []) {
            $highlights[] = self::ucfirst(implode(', ', $facts['free_from'])) . '.';
        }
        if ($facts['allergens'] !== []) {
            $highlights[] = 'Sudėtyje yra alergenų: ' . implode(', ', $facts['allergens']) . '.';
        }
        $made = array_filter([
            !empty($facts['origin_country']) ? 'kilmės šalis ' . $facts['origin_country'] : null,
            !empty($facts['manufacturer']) ? 'gamintojas ' . $facts['manufacturer'] : null,
        ]);
        if ($made !== []) {
            $highlights[] = self::ucfirst(implode(', ', $made)) . '.';
        }

        $amounts = null;
        if (!$isMedicine && $facts['amounts'] !== []) {
            $amounts = [
                'basis' => $facts['amounts_basis'] ?? null,
                'rows' => collect($facts['amounts'])->map(fn ($a) => [
                    'name' => (string) $a['name'],
                    'amount' => trim(self::number($a['amount']) . ' ' . ($a['unit'] ?? '') . (empty($a['alt']) ? '' : " ({$a['alt']})")),
                    'rmv' => isset($a['rmv_percent']) && is_numeric($a['rmv_percent']) ? self::number($a['rmv_percent']) . ' %' : null,
                ])->all(),
            ];
        }

        $result = [
            'highlights' => $highlights,
            'amounts' => $amounts,
            'ingredients' => $facts['ingredients'] ?? null,
            'same_substance' => $isMedicine ? self::sameSubstance($productId, $facts['active_substances']) : null,
            'sources' => $rows->pluck('store.name')->filter()->unique()->values()->all(),
        ];

        return $highlights === [] && $amounts === null && empty($result['ingredients']) ? null : $result;
    }

    /**
     * One set of facts from several pharmacies' pages: the fullest row wins
     * per field, lists are taken from the row that has them.
     */
    public static function merge(Collection $rows): array
    {
        $ordered = $rows->sortByDesc(fn ($row) => count(array_filter($row->facts ?? [], fn ($v) => $v !== null && $v !== [] && $v !== '')));
        $merged = [];
        $listFields = ['amounts', 'active_substances', 'key_ingredients', 'free_from', 'suitable_for', 'allergens'];

        foreach ($ordered as $row) {
            foreach ($row->facts ?? [] as $key => $value) {
                $empty = $value === null || $value === '' || $value === [];
                if (!$empty && (!array_key_exists($key, $merged) || $merged[$key] === null || $merged[$key] === [] || $merged[$key] === '')) {
                    $merged[$key] = $value;
                }
            }
        }
        foreach ($listFields as $field) {
            $merged[$field] = array_values(array_filter((array) ($merged[$field] ?? []), fn ($v) => $v !== null && $v !== ''));
        }

        return self::tidy($merged);
    }

    /**
     * The model's lists are free text; keep only what reads as a short fact
     * (seen in the 2026-10-14 sample: "nekemša porų" under free_from,
     * "tinka ir ypač jautriai odai" under suitable_for, a full postal address
     * as manufacturer, a country as manufacturer).
     */
    private static function tidy(array $facts): array
    {
        $short = fn (string $v, int $max) => mb_strlen($v) <= $max;

        $facts['free_from'] = collect($facts['free_from'])
            ->map(fn ($v) => mb_strtolower(trim((string) $v)))
            ->filter(fn ($v) => preg_match('/^be [\p{L}\- ]{3,30}$/u', $v))
            ->unique()->take(4)->values()->all();

        $facts['suitable_for'] = collect($facts['suitable_for'])
            ->map(fn ($v) => trim(preg_replace('/^(tinka|skirta|skirtas)\s+(ir\s+)?(ypač\s+)?/iu', '', trim((string) $v))))
            // Ages are min_age_years ("Skirta nuo 3 metų"), not a second line.
            ->filter(fn ($v) => $v !== '' && $short($v, 40) && !preg_match('/\d/', $v))
            ->map(fn ($v) => mb_strtolower($v))
            ->unique()->take(4)->values()->all();

        $facts['key_ingredients'] = collect($facts['key_ingredients'])
            // "Ricinus Communis Seed Oil / Ricinus Communis (Castor) Seed Oil" -> first name only
            ->map(fn ($v) => trim(preg_split('/\s+\/\s+/', (string) $v)[0]))
            ->filter(fn ($v) => $v !== '' && $short($v, 40))
            ->unique(fn ($v) => mb_strtolower($v))->take(5)->values()->all();

        // Food allergens mean something for food and supplements only; on
        // cosmetics the models listed "pienas", "molis", "kvepalai" (sample).
        $facts['allergens'] = !in_array($facts['kind'] ?? null, ['maistas', 'maisto_papildas'], true) ? [] : collect($facts['allergens'])
            ->map(fn ($v) => mb_strtolower(trim((string) $v)))
            ->filter(fn ($v) => $v !== '' && $short($v, 30))
            ->unique()->values()->all();

        if (!empty($facts['manufacturer'])) {
            // Drop an address after the company name.
            $parts = explode(',', (string) $facts['manufacturer'], 2);
            $name = trim(isset($parts[1]) && preg_match('/\d/', $parts[1]) ? $parts[0] : (string) $facts['manufacturer']);
            $isCountry = !empty($facts['origin_country']) && mb_strtolower($name) === mb_strtolower((string) $facts['origin_country']);
            $facts['manufacturer'] = $name !== '' && $short($name, 60) && !$isCountry ? $name : null;
        }
        if (!empty($facts['form'])) {
            $facts['form'] = mb_strtolower(trim((string) $facts['form']));
        }

        return $facts;
    }

    /** Days one pack lasts at the stated daily dose, when both are known. */
    public static function packDays(array $facts): ?int
    {
        $units = $facts['pack_units'] ?? null;
        $daily = $facts['daily_units'] ?? null;
        if (!is_numeric($units) || !is_numeric($daily) || $daily <= 0 || $units <= 0) {
            return null;
        }
        $days = (int) floor($units / $daily);

        return $days >= 2 ? $days : null;
    }

    /** "ibuprofenas|400mg" for matching the same medicine across products. */
    public static function substanceKey(array $activeSubstances): ?string
    {
        $parts = collect($activeSubstances)
            ->map(fn ($s) => mb_strtolower(trim((string) ($s['name'] ?? ''))) . '|' . mb_strtolower(preg_replace('/\s+/', '', (string) ($s['strength'] ?? ''))))
            ->filter(fn ($p) => !str_starts_with($p, '|') && !str_ends_with($p, '|'))
            ->sort()
            ->values();

        return $parts->isEmpty() ? null : $parts->implode(';');
    }

    /** Other products with the same active substances and strengths. */
    private static function sameSubstance(int $productId, array $activeSubstances): ?array
    {
        $key = self::substanceKey($activeSubstances);
        if ($key === null) {
            return null;
        }

        $productIds = ProductFact::query()
            ->whereNotNull('facts')
            ->whereNotNull('product_id')
            ->where('product_id', '!=', $productId)
            ->whereRaw("JSON_UNQUOTE(JSON_EXTRACT(facts, '$.kind')) = ?", [self::KIND_MEDICINE])
            ->get(['product_id', 'facts'])
            ->filter(fn ($row) => self::substanceKey($row->facts['active_substances'] ?? []) === $key)
            ->pluck('product_id')
            ->unique()
            ->take(8);

        if ($productIds->isEmpty()) {
            return null;
        }

        $products = \App\Models\Product::whereIn('id', $productIds)
            ->whereHas('discount')
            ->orderBy('name')
            ->get(['id', 'name', 'slug'])
            ->map(fn ($p) => ['name' => $p->name, 'url' => PageUrl::product($p->slug)])
            ->all();

        return $products === [] ? null : [
            'label' => 'Ta pati veiklioji medžiaga ir stiprumas',
            'products' => $products,
        ];
    }

    private static function packSuffix(array $facts): string
    {
        if (is_numeric($facts['pack_units'] ?? null)) {
            return ', pakuotėje ' . self::number($facts['pack_units']) . ' vnt.';
        }

        return !empty($facts['net_quantity']) ? ', ' . $facts['net_quantity'] : '';
    }

    private static function number(mixed $value): string
    {
        if (!is_numeric($value)) {
            return (string) $value;
        }
        $value = (float) $value;

        return floor($value) == $value ? (string) (int) $value : str_replace('.', ',', rtrim(rtrim(number_format($value, 3, '.', ''), '0'), '.'));
    }

    // Lithuanian count forms: 1, 21 -> one; 2-9, 22-29 -> few; 10-20, 30 -> many.
    private static function plural(int $n, string $one, string $few, string $many): string
    {
        $teen = $n % 100 >= 10 && $n % 100 <= 20;

        return match (true) {
            !$teen && $n % 10 === 1 => $one,
            !$teen && $n % 10 >= 2 => $few,
            default => $many,
        };
    }

    private static function sentence(string $text): string
    {
        return str_ends_with($text, '.') ? $text : "{$text}.";
    }

    private static function ucfirst(string $text): string
    {
        return mb_strtoupper(mb_substr($text, 0, 1)) . mb_substr($text, 1);
    }
}
