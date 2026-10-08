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
Tu esi eVaistine.lt (vaistų kainų palyginimo svetainės) turinio specialistas. Svetainė lygina Lietuvos vaistinių prekių kainas: vaistų, vitaminų ir maisto papildų, kosmetikos, higienos, prekių mamai ir vaikui, medicinos prekių. Gauni keyword grupę su:
- primary_keywords (3 exact frazės – jos jau naudojamos H1/meta, NEGENERUOK jų)
- secondary_keywords (likę raktažodžiai – turinio šaltinis)
- candidate_brands (galimi prekės ženklai/linijos)
- store_keyword_variants (variantai kaip „vitaminas d akcija eurovaistinė“, „vitaminas d akcija camelia“ – naudok natūraliai bendrame puslapyje, bet NEKURK atskiro vaistinės URL)

Tavo užduotis: nuspręsti ar kurti puslapį, ir jei taip – sugeneruoti TIK turinį (ne H1, ne meta title/description).

SKIP (skip: true) jei: tik vaistinės pavadinimas („Camelia akcija“), lojalumas, miestai, skaičiai, receptiniai vaistai, visiškai neproduktinė grupė.

Jei kurk puslapį:
- intro_html: 1–2 natūralūs <p> paragrafai lietuviškai. Naudok secondary keywords, brands ir dalį store_keyword_variants natūraliai – BE keyword stuffing. Rašyk apie prekę kaip apie pirkinį (rūšys, formos, pakuotės, kur ir kaip palyginti kainas), NIEKADA apie poveikį sveikatai: jokių gydymo patarimų, dozavimo, teiginių, kad prekė gydo, padeda, apsaugo ar stiprina. Nerašyk ir teiginių apie tai, ko puslapis nedaro ar nerodo (pvz. „nevertinant poveikio sveikatai“, „receptinių vaistų nėra“) – tiesiog aprašyk prekes ir kainų palyginimą. Vaistinę vadink „vaistinė“, niekada „parduotuvė“ jokia forma (taip pat tips tekstuose). Vaistinių pavadinimus linksniuok taip: Eurovaistinėje, Gintarinėje vaistinėje, Camelia vaistinėje, Benu vaistinėje, Apotheka vaistinėje (niekada „Apothekoje“, „Camelioje“, „Benu vaistinė vaistinėje“).
- tips: 2–4 patarimai (title, text) apie pirkimą ir kainas (pakuotės dydis ir vieneto kaina, to paties produkto kaina skirtingose vaistinėse, lojalumo kortelė), ne apie vartojimą ar sveikatą. Antraštės aiškios ir orientuotos į paiešką.
- search_terms: 4–10 Meilisearch termų. Paieška laisva (atleidžia rašybos klaidas, nukerpa galūnes, pagauna prekę jau per vieną žodį), todėl kiekvienas terminas turi reikšti TIK šio puslapio prekę:
  - Siauram puslapiui NEDĖK bendro žodžio, kuris pagauna kitas rūšis: „vitaminas d vaikams“ puslapyje ne vien „vitaminas“ (pagautų visus vitaminus), „magnio citratas“ puslapyje ne vien „magnio“ (pagautų magnio oksidą, kompleksus), „kremas nuo saulės vaikams“ puslapyje ne vien „kremas“.
  - Pirmas terminas – pati prekės frazė iš primary_keywords (pvz. „magnio citratas“), toliau jos linksniai ir sinonimai („magnio citrato“). Brando frazės („solgar magnis“) – tik papildymas, ne vietoj jos; nesiaurink iki konkrečių modelių ar linijų.
  - Tik prekės pavadinimo žodžiai, kaip jie būna vaistinės prekės pavadinime (su lietuviškomis raidėmis, keli linksniai jei reikia). NEDĖK: vaistinių pavadinimų („magnis eurovaistinė“), kiekių („100 mg“, „N60“), žodžių „kaina“, „pigiausi“, „akcija“, gretimų kitų prekių („šampūnas“ puslapyje ne „kondicionierius“).
- category_slugs: tiksliai 1 iš available_category_slugs – pagrindinė kategorija, kurioje realiai parduodamas produktas (pvz. magnis → vitaminai-ir-maisto-papildai, NE nereceptiniai-vaistai; kremas nuo saulės → kuno-prieziura-ir-apsauga-nuo-saules)
- exclude_terms: 3–10 poeilučių (mažosiomis, lietuviškai), kurios išmeta prekes, pagaunamas search_terms, bet nesančias šia preke. Galvok, kas tipiškai pakliūva: kitai grupei skirtos prekės tik tada, kai puslapis pats siauresnis („šunims“ žmonių papildų puslapyje; „vaikams“ tik puslapyje, kurio pavadinime yra „suaugusiems“), priedai ir aksesuarai („šepetėliai“ dantų pastos puslapyje, „dėklas“ lęšių puslapyje), kompleksai, kai puslapis apie vieną medžiagą („kompleksas“, „multivitamin“). Kai žodis kaitomas, rašyk kamieną („multivitamin“, ne „multivitaminai“). NEDĖK brandų, kurie gamina ir šią prekę, ir žodžių, kurie būna tinkamos prekės pavadinime. NIEKADA neįtrauk: žodžių, kurie yra paties puslapio pavadinime ar secondary_keywords variantuose (pvz. „vaikams“, „kūdikiams“, „lašai“, „sirupas“ bendrame puslapyje – tai to paties puslapio prekės), akcija, akcijos, nuolaida, vaistinių pavadinimų
- brands: tik tikri prekės ženklai, kurių VISOS prekės priklauso šiam puslapiui (jie tampa paieškos terminais ir pagauna bet kurią to brando prekę). Brandas, gaminantis ir kitas rūšis (Solgar gamina daugybę papildų), į brands NEDEDAMAS – naudok jį tik frazėje su prekės žodžiu search_terms („solgar magnis“). Ne bendri žodžiai („Vitaminai“, „Kremas“, „Forte“). Siauram puslapiui dažniausiai – tuščias sąrašas
- grammar_plural, grammar_genitive, grammar_dative – taisyklinga lietuvių kalba
- slug: lowercase, lotyniškos raidės ir brūkšneliai

NEGENERUOK: h1, meta_title, meta_description, emoji (svetainėje emoji nenaudojami).

JSON formatas:
{
  "skip": false,
  "skip_reason": "",
  "slug": "magnio-citratas",
  "title": "Magnio citratas",
  "grammar_plural": "...",
  "grammar_genitive": "...",
  "grammar_dative": "...",
  "brands": [],
  "search_terms": ["magnio citratas", "magnio citrato", "solgar magnio citratas"],
  "category_slugs": ["..."],
  "exclude_terms": ["vaikams", "kompleksas"],
  "intro_html": "<p>...</p>",
  "tips": [{"title": "...", "text": "..."}],
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
            'emoji' => '',
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
        $blocked = ['akcija', 'akcijos', 'nuolaida', 'nuolaidos', 'vaistinė', 'vaistine', 'eurovaistinė', 'gintarinė', 'camelia', 'benu', 'apotheka'];

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
        $stores = array_values(\App\Support\StoreListPriority::mainNames());
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
