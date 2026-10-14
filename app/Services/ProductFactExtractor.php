<?php

namespace App\Services;

use App\Models\ProductFact;
use Illuminate\Support\Facades\Http;
use RuntimeException;

// Turns a product page's text (product_facts.source_text + attributes) into
// structured facts with a language model: composition, amounts per dose,
// active substance, pack size. The model only restates what the page says;
// everything shown to visitors is built from these facts by
// App\Support\ProductFacts. See docs/product-facts.md.
class ProductFactExtractor
{
    public const SCHEMA_VERSION = 1;

    private const SYSTEM_PROMPT = <<<'PROMPT'
You extract facts about one product from a Lithuanian pharmacy's product page. Answer with one JSON object, nothing else.

Rules:
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
    public function extract(ProductFact $fact, ?string $model = null): array
    {
        $model ??= config('product_facts.model') ?: throw new RuntimeException('No PRODUCT_FACTS_MODEL set.');
        $user = $this->userMessage($fact);

        $result = str_starts_with($model, 'gemini-')
            ? $this->gemini($model, $user)
            : $this->openai($model, $user);

        $facts = json_decode($result['text'], true);
        if (!is_array($facts)) {
            throw new RuntimeException("{$model}: not JSON: " . mb_substr($result['text'], 0, 200));
        }
        $facts['schema'] = self::SCHEMA_VERSION;

        return ['facts' => $facts, 'input_tokens' => $result['input'], 'output_tokens' => $result['output'], 'model' => $model];
    }

    public static function cost(string $model, int $inputTokens, int $outputTokens): ?float
    {
        // Not config("product_facts.prices.{$model}"): the dots in
        // "gemini-3.5-flash-lite" would be read as nesting.
        $price = config('product_facts.prices')[$model] ?? null;

        return $price ? ($inputTokens * $price[0] + $outputTokens * $price[1]) / 1_000_000 : null;
    }

    private function userMessage(ProductFact $fact): string
    {
        $lines = [
            'Product name: ' . ($fact->page_name ?? $fact->product?->name ?? '(unknown)'),
            // The category keeps "kind" from drifting (gpt-5-nano filed
            // nappies and mascara as supplements without one). The page's own
            // breadcrumb: extraction runs locally, where new products aren't
            // in the DB.
            'Category: ' . ($fact->page_category ?? $fact->product?->category?->name ?? '(unknown)'),
        ];
        foreach ($fact->attributes ?? [] as $label => $value) {
            $lines[] = "Attribute {$label}: {$value}";
        }
        $lines[] = '';
        $lines[] = 'Page text:';
        $lines[] = mb_substr((string) $fact->source_text, 0, 12000);

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
            ->post("https://generativelanguage.googleapis.com/v1beta/models/{$model}:generateContent", [
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
            ]);

        if (!$response->successful()) {
            throw new RuntimeException("{$model}: HTTP {$response->status()}: " . mb_substr($response->body(), 0, 300));
        }

        return [
            'text' => (string) $response->json('candidates.0.content.parts.0.text'),
            'input' => (int) $response->json('usageMetadata.promptTokenCount'),
            'output' => (int) $response->json('usageMetadata.candidatesTokenCount') + (int) $response->json('usageMetadata.thoughtsTokenCount'),
        ];
    }
}
