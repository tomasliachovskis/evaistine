<?php

namespace App\Services;

use App\Models\ProductFact;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;
use RuntimeException;

// Turns a product page's text (product_facts.source_text + attributes) into
// structured facts with a language model: composition, amounts per dose,
// active substance, pack size. The model only restates what the page says;
// everything shown to visitors is built from these facts by
// App\Support\ProductFacts. A product sold by several pharmacies is one
// request with all their pages (2026-10-16: half of a night's rows were the
// same product from a second or third pharmacy), and the answer goes to every
// one of those rows. See docs/product-facts.md.
class ProductFactExtractor
{
    public const SCHEMA_VERSION = 1;

    private const SYSTEM_PROMPT = <<<'PROMPT'
You extract facts about one product from a Lithuanian pharmacy's product page. Answer with one JSON object, nothing else.

Rules:
- The message may hold the same product's pages from several pharmacies ("Page 1 (Gintarinė vaistinė)", "Page 2 (...)"). Give one answer for the product: combine what the pages say, and when they disagree take the most complete and specific statement.
- Use only what the page text and attributes say. Never add knowledge of your own, never guess. Unknown or not stated -> null (or [] for lists).
- Keep Lithuanian wording from the page for names and values. Do not translate.
- No health claims, no advice, no descriptions of what the product does for the body.
- Numbers as numbers (comma decimals -> dot).

Fields:
- "kind": one of "vaistas" (medicine with a package leaflet), "maisto_papildas", "kosmetika", "medicinos_priemone", "maistas", "kita". Use the category: medicines with a package leaflet -> "vaistas"; vitamins and food supplements -> "maisto_papildas"; skin, hair, make-up, hygiene, oral care and sun care -> "kosmetika"; medical goods, devices, orthopedics, nappies -> "medicinos_priemone"; teas, sports and special food -> "maistas".
- "form": product form in Lithuanian, lower case, plural as on the page (e.g. "kapsulės", "plėvele dengtos tabletės", "nosies purškalas", "kremas", "milteliai"). null if not stated.
- "pack_units": number of dose units in the pack (capsules, tablets, sachets, ampoules) if stated (e.g. "N60", "60 kapsulių" -> 60). null for creams, liquids and anything not counted.
- "pack_unit": singular name of that unit (e.g. "kapsulė", "tabletė", "paketėlis"). null if pack_units is null.
- "net_quantity": net amount as written (e.g. "21,6 g", "30 ml"), else null.
- "daily_units": supplements and foods only: how many units the page says to take per day for adults or for everyone (e.g. "po 1 kapsulę per dieną" -> 1; "2 kartus per dieną po 2 tabletes" -> 4). If it is a range, take the lower number. null if not stated or if it is a medicine.
- "min_age_years": the youngest age the page says the product is for (e.g. "nuo 12 metų" -> 12, "vaikams nuo 3 m." -> 3). null if not stated.
- "amounts_basis": what the amounts below are given per, as written (e.g. "1 kapsulėje", "paros dozėje (2 tabletės)", "100 g"). null if no amounts.
- "amounts": list of the vitamins, minerals and other declared active ingredients with their amount per that basis: {"name": as on the page (e.g. "Vitaminas D3"), "amount": number, "unit": as on the page ("µg", "mg", "g", "TV", "mlrd. KSV"), "alt": other notation if given (e.g. "4000 TV") else null, "rmv_percent": the % RMV/RVN the page gives for it, else null}. Only supplements and foods. [] if none.
- "active_substances": medicines only: [{"name": active substance in Lithuanian as in the leaflet (e.g. "ibuprofenas"), "strength": per unit as written (e.g. "400 mg", "1 mg/ml")}]. [] otherwise.
- "ingredients": the full ingredient / composition list exactly as written ("Sudedamosios dalys", "Sudėtis", INCI). For medicines the excipients sentence is not needed: null. null if absent.
- "key_ingredients": cosmetics only: up to 5 active ingredients the page itself highlights in its description (not every INCI entry, not water, glycerin, waxes or preservatives), in Lithuanian in the nominative case if the page names them so (e.g. "niacinamidas", "hialurono rūgštis", "retinolis", "pantenolis"; not "niacinu"), else the INCI name. [] otherwise.
- "spf": SPF number if stated, else null.
- "free_from": only explicit "be ..." / "sudėtyje nėra ..." statements about an ingredient, each written as "be <ingredient>" in at most 4 words, lower case (e.g. "be parabenų", "be kvapiklių", "be glitimo", "be laktozės"). Not effects ("nekemša porų"). [].
- "suitable_for": only explicit statements of who or what skin it is for, as the bare target in at most 4 words without "tinka", lower case (e.g. "jautriai odai", "nėščiosioms", "veganams", "diabetikams"). Ages go to min_age_years, not here. [].
- "allergens": allergens the page declares (often written in capitals in the ingredients, or "Sudėtyje yra ..."), lower case Lithuanian nouns (e.g. "pienas", "soja", "žuvis"). [].
- "origin_country": country of origin / manufacture if stated (attribute "Kilmės šalis" or text), else null.
- "manufacturer": manufacturer company name only (no address, never a country), if stated, else null.
PROMPT;

    /**
     * @return array{facts: array, input_tokens: int, output_tokens: int, model: string}
     */
    public function extract(ProductFact|Collection $rows, ?string $model = null): array
    {
        $model ??= config('product_facts.model') ?: throw new RuntimeException('No PRODUCT_FACTS_MODEL set.');
        $user = $this->userMessage(Collection::wrap($rows));

        $result = str_starts_with($model, 'gemini-')
            ? $this->gemini($model, $user)
            : $this->openai($model, $user);

        return ['facts' => self::parseFacts($result['text'], $model), 'input_tokens' => $result['input'], 'output_tokens' => $result['output'], 'model' => $model];
    }

    /**
     * One line of a Gemini Batch API input file (product-facts:batch-submit)
     * for one product's rows: the same request extract() sends. The key lists
     * each row as id:source_hash, so product-facts:batch-collect can tell a
     * page that changed meanwhile.
     */
    public function geminiBatchLine(Collection $rows, string $model): array
    {
        return [
            'key' => $rows->map(fn (ProductFact $row) => "{$row->id}:{$row->source_hash}")->implode(','),
            'request' => $this->geminiRequest($model, $this->userMessage($rows)),
        ];
    }

    /** @return list<array{0: string, 1: ?string}> [id, source_hash] per row of a batch line's key */
    public static function parseBatchKey(string $key): array
    {
        return array_map(fn ($part) => array_pad(explode(':', $part, 2), 2, null), array_filter(explode(',', $key)));
    }

    /**
     * Rows of the same product, one request each. A row without a product
     * (not linked locally) is its own group.
     *
     * @return Collection<int, Collection<int, ProductFact>>
     */
    public static function groupByProduct(Collection $rows): Collection
    {
        return $rows->groupBy(fn (ProductFact $row) => $row->product_id ? "p{$row->product_id}" : "r{$row->id}")->values();
    }

    /** The model's answer as facts; throws when it isn't a JSON object. */
    public static function parseFacts(string $text, string $model): array
    {
        $facts = json_decode($text, true);
        if (!is_array($facts)) {
            throw new RuntimeException("{$model}: not JSON: " . mb_substr($text, 0, 200));
        }
        $facts['schema'] = self::SCHEMA_VERSION;

        return $facts;
    }

    /**
     * A page that gave no text is tried once and then done: empty facts, no
     * model call, pushed like the rest so production won't list it again.
     */
    public static function closeEmptyPages(): int
    {
        return ProductFact::whereNull('extracted_at')
            ->whereNull('batch_id')
            ->whereNull('source_text')
            ->where(fn ($q) => $q->whereNull('attributes')->orWhereRaw('JSON_LENGTH(attributes) = 0'))
            ->update(['facts' => json_encode(['schema' => self::SCHEMA_VERSION]), 'extracted_with' => 'none', 'extracted_at' => now()]);
    }

    public static function cost(string $model, int $inputTokens, int $outputTokens, bool $batch = false): ?float
    {
        // Not config("product_facts.prices.{$model}"): the dots in
        // "gemini-3.5-flash-lite" would be read as nesting.
        $price = config('product_facts.prices')[$model] ?? null;
        if (!$price) {
            return null;
        }
        $cost = ($inputTokens * $price[0] + $outputTokens * $price[1]) / 1_000_000;

        return $batch ? $cost * (float) config('product_facts.batch_discount', 0.5) : $cost;
    }

    private function userMessage(Collection $rows): string
    {
        $first = $rows->first();
        $lines = [
            'Product name: ' . ($first->page_name ?? $first->product?->name ?? '(unknown)'),
            // The category keeps "kind" from drifting (gpt-5-nano filed
            // nappies and mascara as supplements without one). The page's own
            // breadcrumb: extraction runs locally, where new products aren't
            // in the DB.
            'Category: ' . ($first->page_category ?? $first->product?->category?->name ?? '(unknown)'),
        ];
        foreach ($rows->values() as $i => $row) {
            $lines[] = '';
            if ($rows->count() > 1) {
                $lines[] = 'Page ' . ($i + 1) . ' (' . ($row->store?->name ?? 'pharmacy') . ')' . ($row->page_name && $row->page_name !== $first->page_name ? ": {$row->page_name}" : '');
            }
            foreach ($row->attributes ?? [] as $label => $value) {
                $lines[] = "Attribute {$label}: {$value}";
            }
            $lines[] = 'Page text:';
            $lines[] = mb_substr((string) $row->source_text, 0, 12000);
        }

        return implode("\n", $lines);
    }

    /** @return array{text: string, input: int, output: int} */
    private function openai(string $model, string $user): array
    {
        $response = Http::timeout(120)->retry(2, 2000, throw: false)
            ->withToken(config('services.openai.api_key'))
            ->post('https://api.openai.com/v1/chat/completions', [
                'model' => $model,
                'messages' => [
                    ['role' => 'system', 'content' => self::SYSTEM_PROMPT],
                    ['role' => 'user', 'content' => $user],
                ],
                'response_format' => ['type' => 'json_object'],
                // Extraction needs no deliberation; reasoning tokens are billed as output.
                'reasoning_effort' => 'minimal',
            ]);

        if (!$response->successful()) {
            throw new RuntimeException("{$model}: HTTP {$response->status()}: " . mb_substr($response->body(), 0, 300));
        }

        return [
            'text' => (string) $response->json('choices.0.message.content'),
            'input' => (int) $response->json('usage.prompt_tokens'),
            'output' => (int) $response->json('usage.completion_tokens'),
        ];
    }

    /** @return array{text: string, input: int, output: int} */
    private function gemini(string $model, string $user): array
    {
        $response = Http::timeout(120)->retry(2, 2000, throw: false)
            ->withHeaders(['x-goog-api-key' => config('services.gemini.api_key')])
            ->post("https://generativelanguage.googleapis.com/v1beta/models/{$model}:generateContent", $this->geminiRequest($model, $user));

        if (!$response->successful()) {
            throw new RuntimeException("{$model}: HTTP {$response->status()}: " . mb_substr($response->body(), 0, 300));
        }

        return self::geminiResult($response->json() ?? []);
    }

    /** The generateContent body, the same for a single call and a batch line. */
    private function geminiRequest(string $model, string $user): array
    {
        return [
            'systemInstruction' => ['parts' => [['text' => self::SYSTEM_PROMPT]]],
            'contents' => [['role' => 'user', 'parts' => [['text' => $user]]]],
            'generationConfig' => [
                'responseMimeType' => 'application/json',
                'temperature' => 0,
                // Hidden thinking is billed as output; 2.5 turns it off with a
                // zero budget, 3.x only takes a level.
                'thinkingConfig' => str_starts_with($model, 'gemini-2')
                    ? ['thinkingBudget' => 0]
                    : ['thinkingLevel' => 'minimal'],
            ],
        ];
    }

    /**
     * Gemini's answer text and tokens, from generateContent or one line of a
     * batch's results (same response shape).
     *
     * @return array{text: string, input: int, output: int}
     */
    public static function geminiResult(array $response): array
    {
        return [
            'text' => (string) data_get($response, 'candidates.0.content.parts.0.text'),
            'input' => (int) data_get($response, 'usageMetadata.promptTokenCount'),
            'output' => (int) data_get($response, 'usageMetadata.candidatesTokenCount') + (int) data_get($response, 'usageMetadata.thoughtsTokenCount'),
        ];
    }
}
