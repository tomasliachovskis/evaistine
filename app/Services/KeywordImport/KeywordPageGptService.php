<?php

namespace App\Services\KeywordImport;

use App\Services\KeywordPageCategoryResolver;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class KeywordPageGptService
{
    private ?string $apiKey;

    private string $apiUrl = 'https://api.openai.com/v1/chat/completions';

    public function __construct(
        private KeywordPageCategoryResolver $categoryResolver,
    ) {
        $this->apiKey = config('services.openai.api_key');
    }

    public function isConfigured(): bool
    {
        return !empty($this->apiKey);
    }

    /**
     * @param  array{term_group: string, slug: string, keywords: list<array>, total_volume: int, source_groups?: list<string>}  $group
     * @param  array{primary_keywords: list<string>, secondary_keywords: list<array>, h1: string, candidate_brands: list<string>}  $analysis
     * @param  list<array{slug: string, name: string}>  $categories
     * @return array{skip: bool, skip_reason?: string, page?: array<string, mixed>}|null
     */
    public function generateContent(array $group, array $analysis, array $categories): ?array
    {
        if (!$this->isConfigured()) {
            Log::warning('KeywordPageGptService: OpenAI API key not configured');

            return null;
        }

        $payload = [
            'term_group' => $group['term_group'],
            'slug' => $group['slug'],
            'source_groups' => $group['source_groups'] ?? [$group['term_group']],
            'primary_keywords' => $analysis['primary_keywords'],
            'secondary_keywords' => array_map(fn ($k) => [
                'keyword' => $k['keyword'],
                'volume' => $k['volume'],
                'category' => $k['category'],
            ], $analysis['secondary_keywords']),
            'candidate_brands' => $analysis['candidate_brands'],
            'store_keyword_variants' => $this->buildStoreKeywordVariants($analysis['primary_keywords']),
            'available_category_slugs' => $categories,
        ];

        try {
            $response = Http::timeout(120)
                ->retry(2, 2000)
                ->withHeaders([
                    'Authorization' => 'Bearer ' . $this->apiKey,
                    'Content-Type' => 'application/json',
                ])
                ->post($this->apiUrl, [
                    'model' => config('services.openai.model', 'gpt-5-mini'),
                    'response_format' => ['type' => 'json_object'],
                    'messages' => [
                        ['role' => 'system', 'content' => $this->systemPrompt()],
                        ['role' => 'user', 'content' => json_encode($payload, JSON_UNESCAPED_UNICODE)],
                    ],
                ]);

            if (!$response->successful()) {
                Log::error('KeywordPageGptService API failed', [
                    'term_group' => $group['term_group'],
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);

                return null;
            }

            $content = trim((string) $response->json('choices.0.message.content'));
            $parsed = json_decode($content, true);

            if (!is_array($parsed)) {
                Log::error('KeywordPageGptService: invalid JSON', ['content' => $content]);

                return null;
            }

            return $this->normalizeResponse($parsed, $group['slug'], $categories);
        } catch (\Throwable $e) {
            Log::error('KeywordPageGptService exception', [
                'term_group' => $group['term_group'],
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    private function systemPrompt(): string
    {
        return <<<'PROMPT'
Tu esi SuperAkcijos.lt turinio specialistas. Gauni keyword grupę su:
- primary_keywords (3 exact frazės – jos jau naudojamos H1/meta, NEGENERUOK jų)
- secondary_keywords (likę raktažodžiai – turinio šaltinis)
- candidate_brands (galimi prekės ženklai/linijos)
- store_keyword_variants (variantai kaip „kava akcija maxima“, „kava akcija norfa“ - naudok natūraliai bendrame puslapyje, bet NEKURK store atskiro URL)

Tavo užduotis: nuspręsti ar kurti puslapį, ir jei taip – sugeneruoti TIK turinį (ne H1, ne meta title/description).

SKIP (skip: true) jei: tik parduotuvės pavadinimas (IKI akcija), lojalumas, miestai, skaičiai, visiškai neproduktinė grupė.

Jei kurk puslapį:
- intro_html: 1–2 natūralūs <p> paragrafai lietuviškai. Naudok secondary keywords, brands ir dalį store_keyword_variants natūraliai – BE keyword stuffing
- tips: 2–4 patarimai (icon emoji, title, text)
- faq: 3–5 unikalūs klausimai, atsakymai naudingi vartotojui
- search_terms: 8–15 Meilisearch termų (stiebai, variantai; be žodžio „akcija“)
- category_slugs: tiksliai 1 iš available_category_slugs – pagrindinė kategorija, kurioje realiai parduodamas produktas (pvz. citrinos → vaisiai-ir-darzoves, NE gerimai-kava-arbata)
- exclude_terms: netinkami match žodžiai. NIEKADA neįtrauk: akcija, akcijos, nuolaida, iki, maxima, lidl
- brands: patvirtintas/papildytas brandų sąrašas iš candidate_brands
- grammar_plural, grammar_genitive, grammar_dative – taisyklinga lietuvių kalba
- slug: lowercase, lotyniškos raidės ir brūkšneliai

NEGENERUOK: h1, meta_title, meta_description.

JSON formatas:
{
  "skip": false,
  "skip_reason": "",
  "slug": "lego",
  "title": "Lego",
  "emoji": "🧱",
  "grammar_plural": "...",
  "grammar_genitive": "...",
  "grammar_dative": "...",
  "brands": ["Friends", "Duplo"],
  "search_terms": ["lego", "friends", "duplo"],
  "category_slugs": ["..."],
  "exclude_terms": [],
  "intro_html": "<p>...</p>",
  "tips": [{"icon": "🧱", "title": "...", "text": "..."}],
  "faq": [{"question": "...", "answer": "..."}],
  "related_slugs": []
}

Jei skip: {"skip": true, "skip_reason": "..."}
PROMPT;
    }

    /**
     * @return array{skip: bool, skip_reason?: string, page?: array<string, mixed>}
     */
    private function normalizeResponse(array $parsed, string $fallbackSlug, array $categories): array
    {
        if (!empty($parsed['skip'])) {
            return [
                'skip' => true,
                'skip_reason' => (string) ($parsed['skip_reason'] ?? 'GPT skip'),
            ];
        }

        $slug = preg_replace('/[^a-z0-9-]/', '', mb_strtolower((string) ($parsed['slug'] ?? $fallbackSlug)));
        if ($slug === '') {
            $slug = $fallbackSlug;
        }

        $allowedCategorySlugs = array_column($categories, 'slug');

        $brands = array_values(array_filter((array) ($parsed['brands'] ?? [])));
        $searchTerms = array_values(array_unique(array_filter(array_merge(
            (array) ($parsed['search_terms'] ?? []),
            $brands,
        ))));

        $page = [
            'slug' => $slug,
            'title' => (string) ($parsed['title'] ?? ucfirst($slug)),
            'emoji' => (string) ($parsed['emoji'] ?? '🏷️'),
            'grammar_plural' => (string) ($parsed['grammar_plural'] ?? $parsed['title'] ?? $slug),
            'grammar_genitive' => (string) ($parsed['grammar_genitive'] ?? $parsed['title'] ?? $slug),
            'grammar_dative' => (string) ($parsed['grammar_dative'] ?? $parsed['title'] ?? $slug),
            'brands' => $brands,
            'search_terms' => $searchTerms,
            'category_slugs' => $this->categoryResolver->resolveListingCategorySlugs(
                array_values(array_intersect(
                    array_filter((array) ($parsed['category_slugs'] ?? [])),
                    $allowedCategorySlugs,
                )),
            ),
            'exclude_terms' => $this->sanitizeExcludeTerms((array) ($parsed['exclude_terms'] ?? [])),
            'intro_html' => (string) ($parsed['intro_html'] ?? ''),
            'tips' => array_values((array) ($parsed['tips'] ?? [])),
            'faq' => array_values((array) ($parsed['faq'] ?? [])),
            'related_slugs' => array_values(array_filter((array) ($parsed['related_slugs'] ?? []))),
            'min_active_offers' => 1,
            'is_published' => false,
        ];

        return ['skip' => false, 'page' => $page];
    }

    /**
     * @param  list<string>  $terms
     * @return list<string>
     */
    private function sanitizeExcludeTerms(array $terms): array
    {
        $blocked = ['akcija', 'akcijos', 'nuolaida', 'nuolaidos', 'iki', 'maxima', 'lidl', 'rimi', 'norfa'];

        return array_values(array_filter($terms, function ($term) use ($blocked) {
            $term = mb_strtolower(trim((string) $term));

            return $term !== '' && !in_array($term, $blocked, true);
        }));
    }

    /**
     * @param  list<string>  $primaryKeywords
     * @return list<string>
     */
    private function buildStoreKeywordVariants(array $primaryKeywords): array
    {
        $stores = ['Maxima', 'Norfa', 'Rimi', 'Lidl', 'Iki'];
        $variants = [];

        foreach (array_slice($primaryKeywords, 0, 2) as $keyword) {
            $base = trim($keyword);
            if ($base === '') {
                continue;
            }

            foreach ($stores as $store) {
                $variants[] = $base . ' ' . $store;
            }
        }

        return array_values(array_unique($variants));
    }
}
