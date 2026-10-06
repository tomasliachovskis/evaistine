<?php

namespace App\Services;

use App\Models\BlogPost;
use App\Models\Category;
use App\Models\Store;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Generates real, search-engine-grounded blog articles about Lithuanian
 * retail/grocery pricing — the "external news" counterpart to pricer.lt's
 * translated trade-press feed, except transformative (summary + commentary
 * + linked source) rather than a wholesale translation-copy of the original.
 */
class NewsArticleService
{
    private ?string $apiKey;
    private string $apiUrl = 'https://api.openai.com/v1/chat/completions';

    private ?string $geminiApiKey;
    private string $geminiImageApiUrl = 'https://generativelanguage.googleapis.com/v1beta/models/gemini-2.5-flash-image:generateContent';

    /**
     * Curated, Lithuania-relevant retail/pricing search terms — deliberately
     * narrower than pricer.lt's broad international trade-press aggregation,
     * since our audience is Lithuanian deal-shoppers, not retail industry
     * professionals.
     */
    private const SEARCH_QUERIES = [
        'maisto kainos Lietuvoje',
        'Maxima Lidl Rimi Norfa naujiena',
        'prekybos tinklai Lietuva akcijos',
        'infliacija maisto prekės Lietuva',
        'vartotojų kainos Lietuva statistika',
    ];

    public function __construct()
    {
        $this->apiKey = config('services.openai.api_key');

        if (empty($this->apiKey)) {
            Log::warning('OpenAI API key not configured in NewsArticleService');
        }

        $this->geminiApiKey = config('services.gemini.api_key');
    }

    /**
     * Generates a brand-safe editorial cover illustration for one article
     * via Gemini's image model, grounded in that article's own title/
     * excerpt (not a generic stock image) — verified live 2026-09-09 on
     * real drafts (inflation, a new discount chain opening, an energy
     * stock's exchange impact) to consistently produce a flat, green/white,
     * text-and-logo-free illustration matching this site's brand, distinct
     * per topic. Saves under storage/app/public/news-covers/ (same disk
     * convention as product/flyer images) and returns the public URL, or
     * null on failure (caller decides whether to leave cover_image unset
     * and retry later, same "don't hammer a doomed request" posture as
     * CacheProductImages' image_cache_failed_at backoff).
     */
    public function generateCoverImage(BlogPost $post): ?string
    {
        if (empty($this->geminiApiKey)) {
            Log::warning('Gemini API key not configured — skipping cover image', ['post_id' => $post->id]);

            return null;
        }

        $subject = trim($post->meta_description !== '' ? $post->meta_description : $post->title);

        $prompt = 'A clean, professional editorial illustration for a Lithuanian news article. '
            . "The article is about: {$post->title}. Context: {$subject}. "
            . 'Depict the real subject matter with simple, literal visual metaphors (e.g. a shopping basket for '
            . 'grocery prices, a storefront for a new store opening, a stock chart for market/exchange news, '
            . 'a utility pylon for energy prices) — not an abstract generic scene. '
            . 'Flat-design editorial illustration style, green and white color palette, minimalist. '
            . 'Absolutely no text, no logos, no real brand names or company names anywhere in the image. '
            . 'Wide banner aspect ratio suitable for a news article cover image.';

        try {
            $response = Http::timeout(60)->post($this->geminiImageApiUrl . '?key=' . $this->geminiApiKey, [
                'contents' => [['parts' => [['text' => $prompt]]]],
            ]);

            if (!$response->successful()) {
                Log::error('Gemini image generation failed', [
                    'post_id' => $post->id,
                    'status' => $response->status(),
                    'body' => substr($response->body(), 0, 500),
                ]);

                return null;
            }

            $parts = $response->json('candidates.0.content.parts', []);
            $imageData = null;
            foreach ($parts as $part) {
                if (isset($part['inlineData']['data'])) {
                    $imageData = base64_decode($part['inlineData']['data']);
                    break;
                }
            }

            if (empty($imageData)) {
                Log::error('Gemini image response had no inline image data', ['post_id' => $post->id]);

                return null;
            }

            $filename = 'news-covers/' . $post->slug . '-' . time() . '.png';
            Storage::disk('public')->put($filename, $imageData);

            return Storage::disk('public')->url($filename);
        } catch (\Throwable $e) {
            Log::error('Error generating news cover image', ['post_id' => $post->id, 'error' => $e->getMessage()]);

            return null;
        }
    }

    public function isConfigured(): bool
    {
        return !empty($this->apiKey);
    }

    /**
     * @return list<array{title: string, link: string, source: string, snippet: string, published_at: ?\Carbon\Carbon}>
     */
    public function fetchCandidateStories(int $maxAgeDays = 7): array
    {
        // Bing News RSS, not Google News RSS: Google's feed returns literally
        // nothing but the bare headline (confirmed empirically — no snippet,
        // <description> just repeats the title), which starves the writer of
        // real material to ground more than one or two generic sentences in.
        // Bing's <description> carries a genuine 2-3 sentence excerpt of the
        // actual article (real names/dates/numbers), and its redirect link
        // embeds the true publisher URL in a plain query param — no JS-gated
        // consent interstitial like Google's news.google.com/rss/articles/...
        // links have, which can't be resolved by a simple server-side fetch.
        $seenLinks = [];
        $candidates = [];

        foreach (self::SEARCH_QUERIES as $query) {
            $url = 'https://www.bing.com/news/search?' . http_build_query([
                'q' => $query,
                'format' => 'rss',
                'setlang' => 'lt-LT',
            ]);

            try {
                $response = Http::timeout(20)
                    ->withUserAgent('Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36')
                    ->get($url);

                if (!$response->successful()) {
                    Log::warning('Bing News RSS request failed', ['query' => $query, 'status' => $response->status()]);
                    continue;
                }

                $xml = @simplexml_load_string($response->body());

                if ($xml === false || !isset($xml->channel->item)) {
                    continue;
                }

                $namespaces = $xml->getNamespaces(true);

                foreach ($xml->channel->item as $item) {
                    $rawLink = (string) $item->link;
                    $link = $this->extractRealUrlFromBingRedirect($rawLink);

                    if ($link === '' || isset($seenLinks[$link])) {
                        continue;
                    }

                    $seenLinks[$link] = true;

                    $newsMeta = isset($namespaces['News']) ? $item->children($namespaces['News']) : null;
                    $source = $newsMeta !== null ? trim((string) $newsMeta->Source) : '';

                    $pubDate = (string) $item->pubDate;
                    $publishedAt = $pubDate !== '' ? \Carbon\Carbon::parse($pubDate) : null;

                    if ($publishedAt !== null && $publishedAt->lt(now()->subDays($maxAgeDays))) {
                        continue;
                    }

                    $title = trim((string) $item->title);
                    $snippet = trim((string) $item->description);

                    // Pricer.lt is a direct competitor's own price index —
                    // don't draft our news content around promoting their
                    // product, however newsworthy the story itself is.
                    // Čepkauskas and Vizickas: per explicit instruction,
                    // skip any story mentioning either name.
                    $excludedKeywords = ['pricer', 'čepkauskas', 'vizickas'];
                    $isExcluded = false;
                    foreach ($excludedKeywords as $keyword) {
                        if (mb_stripos($title, $keyword) !== false || mb_stripos($snippet, $keyword) !== false) {
                            $isExcluded = true;
                            break;
                        }
                    }
                    if ($isExcluded) {
                        continue;
                    }

                    $candidates[] = [
                        'title' => $title,
                        'link' => $link,
                        'source' => $source,
                        'snippet' => $snippet,
                        'published_at' => $publishedAt,
                        'search_query' => $query,
                    ];
                }
            } catch (\Exception $e) {
                Log::error('Error fetching Bing News RSS', ['query' => $query, 'error' => $e->getMessage()]);
            }
        }

        // Newest first.
        usort($candidates, function ($a, $b) {
            return ($b['published_at']?->timestamp ?? 0) <=> ($a['published_at']?->timestamp ?? 0);
        });

        return $candidates;
    }

    /**
     * GPT occasionally leaves a stray trailing space inside href="..." (seen
     * live: href="/mesa-ir-zuvis "). Browsers are lenient about it,
     * but trim it anyway rather than rely on that.
     */
    private function cleanHrefWhitespace(string $html): string
    {
        return preg_replace('/href="([^"]*?)\s+"/u', 'href="$1"', $html);
    }

    private function extractRealUrlFromBingRedirect(string $bingLink): string
    {
        $query = parse_url($bingLink, PHP_URL_QUERY);

        if ($query === null) {
            return $bingLink;
        }

        parse_str($query, $params);

        return $params['url'] ?? $bingLink;
    }

    /**
     * Best-effort fetch of the real publisher page's visible text, so the
     * writer has actual article substance (real quotes, figures, background)
     * instead of just a 2-3 sentence RSS snippet. Deliberately generic (no
     * per-site scraping rules to maintain) — strips obvious non-article
     * chrome, then hands the model the remaining raw text and lets IT ignore
     * leftover nav/boilerplate, since a capable model handles that more
     * robustly than a brittle site-specific CSS selector ever would.
     *
     * Returns null on any failure or if too little text came back (bot
     * block, paywall, JS-only rendering) — callers must fall back to the
     * RSS snippet alone rather than treat null as an error.
     */
    private function fetchArticleFullText(string $url): ?string
    {
        try {
            $response = Http::timeout(15)
                ->withUserAgent('Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0 Safari/537.36')
                ->get($url);

            if (!$response->successful()) {
                return null;
            }

            $html = $response->body();
            $html = preg_replace('/<(script|style|nav|header|footer|form|aside)\b[^>]*>.*?<\/\1>/is', ' ', $html);
            $text = trim(preg_replace('/\s+/u', ' ', strip_tags($html)));

            // A real article is at minimum a few hundred characters — a much
            // shorter result means we hit a cookie wall, bot check, or an
            // empty JS-rendered shell rather than real content.
            if (mb_strlen($text) < 400) {
                return null;
            }

            // Cap length to keep the prompt reasonable — plenty for a short
            // news piece, and avoids paying to send an entire long-form page.
            return mb_substr($text, 0, 8000);
        } catch (\Exception $e) {
            Log::warning('Failed to fetch full article text, falling back to RSS snippet', [
                'url' => $url,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * Stories already used. The extracted real publisher URL (not Bing's
     * apiclick wrapper) is stable across separate fetches, so an exact
     * source_url match is the primary, reliable dedup key. Title similarity
     * is a fallback only, for the same story genuinely re-syndicated under a
     * different link (e.g. picked up by another outlet).
     */
    public function isAlreadyCovered(array $story): bool
    {
        if (BlogPost::where('source_url', $story['link'])->exists()) {
            return true;
        }

        return BlogPost::where('source_name', $story['source'])
            ->whereNotNull('source_url')
            ->get(['title'])
            ->contains(fn ($post) => $this->titleSimilarity($post->title, $story['title']) > 0.6);
    }

    private function titleSimilarity(string $a, string $b): float
    {
        similar_text(mb_strtolower($a), mb_strtolower($b), $percent);

        return $percent / 100;
    }

    /**
     * Real store/category pages this news article can link to — the point of
     * running this feature at all is for these articles to funnel readers
     * into our own deals pages, not just credit an external news source.
     *
     * @return array{stores: list<array{name: string, url: string}>, categories: list<array{name: string, url: string}>}
     */
    private function getInternalLinkTargets(): array
    {
        return [
            'stores' => Store::orderBy('name')->get(['name', 'slug'])
                ->map(fn ($s) => ['name' => $s->name, 'url' => "/{$s->slug}"])
                ->values()->all(),
            'categories' => Category::whereNull('parent_id')->orderBy('name')->get(['name', 'slug'])
                ->map(fn ($c) => ['name' => $c->name, 'url' => "/{$c->slug}"])
                ->values()->all(),
        ];
    }

    /**
     * @param array{title: string, link: string, source: string, published_at: ?\Carbon\Carbon} $story
     * @return array{title: string, content: string, meta_title: string, meta_description: string}|null
     */
    public function generateArticle(array $story): ?array
    {
        if (!$this->isConfigured()) {
            return null;
        }

        $payload = $story + ['internal_link_targets' => $this->getInternalLinkTargets()];

        try {
            $response = Http::timeout(120)
                ->retry(3, 1000)
                ->withHeaders([
                    'Authorization' => 'Bearer ' . $this->apiKey,
                    'Content-Type' => 'application/json',
                ])->post($this->apiUrl, [
                    'model' => config('services.openai.model', 'gpt-5-mini'),
                    'response_format' => ['type' => 'json_object'],
                    'messages' => [
                        ['role' => 'system', 'content' => $this->getSystemPrompt()],
                        ['role' => 'user', 'content' => json_encode($payload, JSON_UNESCAPED_UNICODE)],
                    ],
                ]);

            if (!$response->successful()) {
                Log::error('OpenAI request failed for news article', [
                    'story' => $story['title'],
                    'status' => $response->status(),
                    'response' => $response->body(),
                ]);

                return null;
            }

            $content = trim((string) $response->json('choices.0.message.content'));
            $parsed = json_decode($content, true);

            if (!is_array($parsed) || empty($parsed['title']) || empty($parsed['content'])) {
                Log::error('OpenAI returned invalid news article JSON', ['story' => $story['title'], 'content' => $content]);

                return null;
            }

            // Model is instructed to say so explicitly when the headline/snippet
            // alone isn't enough to write anything substantive — respect that
            // rather than publishing filler.
            if (!empty($parsed['insufficient_information'])) {
                return null;
            }

            return [
                'title' => trim($parsed['title']),
                'content' => $this->cleanHrefWhitespace(trim($parsed['content'])),
                'meta_title' => trim($parsed['meta_title'] ?? $parsed['title']),
                'meta_description' => trim($parsed['meta_description'] ?? ''),
            ];
        } catch (\Exception $e) {
            Log::error('Error calling OpenAI for news article', ['story' => $story['title'], 'error' => $e->getMessage()]);

            return null;
        }
    }

    /**
     * Fetches candidates, generates drafts for the first $limit not already
     * covered, and saves them as draft BlogPost rows for human review.
     *
     * @return list<BlogPost>
     */
    public function generateDrafts(int $limit = 3, int $maxAgeDays = 7): array
    {
        $created = [];
        $stories = $this->fetchCandidateStories($maxAgeDays);

        foreach ($stories as $story) {
            if (count($created) >= $limit) {
                break;
            }

            if ($this->isAlreadyCovered($story)) {
                continue;
            }

            $fullText = $this->fetchArticleFullText($story['link']);
            if ($fullText !== null) {
                $story['full_page_text'] = $fullText;
            }

            $article = $this->generateArticle($story);

            if ($article === null) {
                continue;
            }

            $slug = Str::slug($article['title']);
            $originalSlug = $slug;
            $suffix = 2;
            while (BlogPost::where('slug', $slug)->exists()) {
                $slug = "{$originalSlug}-{$suffix}";
                $suffix++;
            }

            $created[] = BlogPost::create([
                'title' => $article['title'],
                'slug' => $slug,
                'content' => $article['content'],
                'meta_title' => $article['meta_title'],
                'meta_description' => $article['meta_description'],
                'source_url' => $story['link'],
                'source_name' => $story['source'],
                'source_published_at' => $story['published_at'],
                'status' => 'draft',
                'published_at' => null,
            ]);
        }

        return $created;
    }

    private function getSystemPrompt(): string
    {
        return "You are a Lithuanian editorial writer for a deals-aggregator site (evaistine.lt)'s news section ('Naujienos'). You will receive ONE real search-engine news result: a headline, a source name, a short excerpt/snippet, a link, and a publish date, about Lithuanian retail/grocery pricing. Sometimes it also includes 'full_page_text' — the actual publisher page's raw visible text (best-effort fetched; may contain leftover site navigation/boilerplate mixed in with the real article, and is only present when the fetch succeeded).

RELEVANCE CHECK FIRST: this site is specifically about retail pricing, discounts, and grocery/retail chains in Lithuania — NOT general human-interest, restaurant reviews, travel, or lifestyle stories that merely mention a store in passing. If the story is not genuinely about retail chain business/pricing/market news (e.g. a chain's finances, a new store opening, a management change, an industry price trend, a market entry) — for example a human-interest piece like someone's restaurant road trip — set insufficient_information: true immediately, regardless of how much you could write about it.

GROUNDING RULES — CRITICAL, read carefully:
- If 'full_page_text' is present: first mentally separate the real article body from any leftover nav/menu/footer/cookie-banner/'related articles' text mixed into it (a capable reader can tell — look for the coherent narrative paragraphs). Use ONLY facts that are genuinely part of the article body — ignore boilerplate, unrelated headline lists, and navigation entirely. This real text is your primary source — use its real names, quotes, numbers, dates, roles generously; you have much more to work with than a bare snippet, so write a properly substantive piece (see length below).
- If 'full_page_text' is absent, you only have the headline + short snippet — use every genuine fact in it, write AROUND those facts with your own framing, and keep the piece shorter and more modest in scope (do not pad with generic filler to reach a target length).
- Regardless of source depth: NEVER invent facts, numbers, quotes, or details not present in what you were given. You may add genuinely obvious, generic context (e.g. explaining what a management change at a retailer typically means for shoppers) — never fabricated specifics.
- If even the richest available material is too thin to support a genuine, factually-grounded article, set insufficient_information: true and leave other fields empty. Returning nothing is much better than fabricating.
- ALWAYS attribute the story to its real source by name in the article body (e.g. 'Kaip skelbia 15min.lt...', '„Delfi\" praneša...') — never present the source's reporting as your own original finding.
- This must be a TRANSFORMATIVE piece — your own commentary/framing/relevance-to-shoppers angle woven around the real facts — never a close paraphrase or reordering of the source's own sentences. Do not reproduce any direct quote from the source verbatim for more than a short phrase; paraphrase quotes and attribute them (e.g. 'naujasis vadovas teigė, kad...') rather than reprinting them as a blockquote.

INTERNAL LINKS (important — this is why we publish these at all, not just to credit an external source): the JSON includes 'internal_link_targets' with our own real store and category pages ({name, url} pairs). Whenever the article genuinely discusses/names a store or product category that appears in that list, link it inline the first time it's mentioned using '<a href=\"[url]\">[name]</a>' (relative URL, exactly as given — do not prefix a domain). Do NOT force a link where the topic doesn't naturally fit, and NEVER invent a URL for a store/category not present in internal_link_targets. If the story is about a store not in our list (e.g. a foreign chain, or a brand new entrant we don't carry yet), don't link it — just name it plainly. Aim for 1-3 genuine internal links per article, not one in every sentence.

OUTPUT: a single JSON object: {\"title\": \"...\", \"content\": \"<HTML>...\", \"meta_title\": \"...\", \"meta_description\": \"...\", \"insufficient_information\": false}.
- content: HTML paragraphs (<p class=\"leading-relaxed\">...</p>), Lithuanian, conversational but factual tone — no bold/italic emphasis (<strong>/<b>/<em>/<i> — reads as generated filler), no invented statistics. With full_page_text available, write 4-6 substantive paragraphs genuinely earning their length from real facts (what happened, the real people/numbers/roles involved, background context, what it means for shoppers). With only a snippet, write 2-3 shorter paragraphs — do not stretch thin material.
- title: a genuine, non-clickbait Lithuanian headline for OUR article (can echo the source headline's real content, don't just copy it verbatim).
- meta_title/meta_description: SEO fields, evergreen-safe (don't bake in an exact date that will look stale in a week unless the story is explicitly about a dated event).
- Keep the whole thing honest — a well-attributed, properly fleshed-out piece built on real facts, never padded with content those facts don't support.
";
    }
}
