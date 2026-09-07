<?php

namespace App\Services;

use App\Models\BlogPost;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Generates real, Google-News-grounded blog articles about Lithuanian
 * retail/grocery pricing — the "external news" counterpart to pricer.lt's
 * translated trade-press feed, except transformative (summary + commentary
 * + linked source) rather than a wholesale translation-copy of the original.
 */
class NewsArticleService
{
    private ?string $apiKey;
    private string $apiUrl = 'https://api.openai.com/v1/chat/completions';

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
    }

    public function isConfigured(): bool
    {
        return !empty($this->apiKey);
    }

    /**
     * @return list<array{title: string, link: string, source: string, published_at: ?\Carbon\Carbon}>
     */
    public function fetchCandidateStories(int $maxAgeDays = 3): array
    {
        $seenLinks = [];
        $candidates = [];

        foreach (self::SEARCH_QUERIES as $query) {
            $url = 'https://news.google.com/rss/search?' . http_build_query([
                'q' => "{$query} when:{$maxAgeDays}d",
                'hl' => 'lt',
                'gl' => 'LT',
                'ceid' => 'LT:lt',
            ]);

            try {
                $response = Http::timeout(20)->get($url);

                if (!$response->successful()) {
                    Log::warning('Google News RSS request failed', ['query' => $query, 'status' => $response->status()]);
                    continue;
                }

                $xml = @simplexml_load_string($response->body());

                if ($xml === false || !isset($xml->channel->item)) {
                    continue;
                }

                foreach ($xml->channel->item as $item) {
                    $link = (string) $item->link;

                    if ($link === '' || isset($seenLinks[$link])) {
                        continue;
                    }

                    $seenLinks[$link] = true;

                    // Titles arrive as "Headline - Source Name".
                    $rawTitle = trim((string) $item->title);
                    $source = '';
                    $title = $rawTitle;
                    if (preg_match('/^(.*)\s-\s([^-]+)$/u', $rawTitle, $m)) {
                        $title = trim($m[1]);
                        $source = trim($m[2]);
                    }

                    $pubDate = (string) $item->pubDate;
                    $publishedAt = $pubDate !== '' ? \Carbon\Carbon::parse($pubDate) : null;

                    $candidates[] = [
                        'title' => $title,
                        'link' => $link,
                        'source' => $source,
                        'published_at' => $publishedAt,
                        'search_query' => $query,
                    ];
                }
            } catch (\Exception $e) {
                Log::error('Error fetching Google News RSS', ['query' => $query, 'error' => $e->getMessage()]);
            }
        }

        // Newest first.
        usort($candidates, function ($a, $b) {
            return ($b['published_at']?->timestamp ?? 0) <=> ($a['published_at']?->timestamp ?? 0);
        });

        return $candidates;
    }

    /**
     * Stories already used. Google's per-article RSS redirect link is stable
     * across separate fetches (confirmed empirically — it is NOT a per-request
     * token), so an exact source_url match is the primary, reliable dedup key.
     * Title similarity is a fallback only, for the same story surfacing under
     * a genuinely different link (e.g. syndicated to another outlet).
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
     * @param array{title: string, link: string, source: string, published_at: ?\Carbon\Carbon} $story
     * @return array{title: string, content: string, meta_title: string, meta_description: string}|null
     */
    public function generateArticle(array $story): ?array
    {
        if (!$this->isConfigured()) {
            return null;
        }

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
                        ['role' => 'user', 'content' => json_encode($story, JSON_UNESCAPED_UNICODE)],
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
                'content' => trim($parsed['content']),
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
    public function generateDrafts(int $limit = 3): array
    {
        $created = [];
        $stories = $this->fetchCandidateStories();

        foreach ($stories as $story) {
            if (count($created) >= $limit) {
                break;
            }

            if ($this->isAlreadyCovered($story)) {
                continue;
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
        return "You are a Lithuanian editorial writer for a deals-aggregator site (superakcijos.lt)'s news section ('Naujienos'). You will receive ONE real Google News search result: a headline, a source name, a link, and a publish date, about Lithuanian retail/grocery pricing.

RELEVANCE CHECK FIRST: this site is specifically about retail pricing, discounts, and grocery/retail chains in Lithuania — NOT general human-interest, restaurant reviews, travel, or lifestyle stories that merely mention a store in passing. If the headline is not genuinely about retail chain business/pricing/market news (e.g. a chain's finances, a new store opening, a management change, an industry price trend, a market entry) — for example a human-interest piece like someone's restaurant road trip — set insufficient_information: true immediately, regardless of how much you could write about it.

CRITICAL — you do NOT have the full article text, only the headline and source/date. This means:
- Do NOT invent facts, numbers, quotes, or details beyond what the headline itself states or strongly implies. If the headline alone ('Maxima grupės pajamos pirmąjį pusmetį augo 2,8 proc. iki 2 mlrd. Eur') gives you a real, self-contained fact, you may write a short article ABOUT that fact, elaborating only with genuinely obvious context (e.g. explaining what EBITDA means, or generic industry context), never fabricated specifics (no invented executive quotes, no invented specific store names/products/dates not in the headline).
- If the headline is too vague/thin to support a genuine, factually-grounded article (e.g. a clickbait-style headline with no real content, or one where you'd have to guess at the substance), set insufficient_information: true and leave other fields empty. Returning nothing is much better than fabricating.
- ALWAYS attribute the story to its real source by name in the article body (e.g. 'Kaip skelbia LRT.lt...', '„Delfi\" praneša...') — never present the source's reporting as your own original finding.
- This must be a TRANSFORMATIVE piece — your own commentary/framing/relevance-to-shoppers angle — not a translation or close paraphrase of the headline into a full article pretending to have more detail than a headline provides.

OUTPUT: a single JSON object: {\"title\": \"...\", \"content\": \"<HTML>...\", \"meta_title\": \"...\", \"meta_description\": \"...\", \"insufficient_information\": false}.
- content: 2-4 short HTML paragraphs (<p class=\"leading-relaxed\">...</p>), Lithuanian, conversational but factual tone — no bold/italic emphasis (<strong>/<b>/<em>/<i> — reads as generated filler), no invented statistics.
- title: a genuine, non-clickbait Lithuanian headline for OUR article (can echo the source headline's real content, don't just copy it verbatim).
- meta_title/meta_description: SEO fields, evergreen-safe (don't bake in an exact date that will look stale in a week unless the story is explicitly about a dated event).
- Keep the whole thing honest and modest in scope — a short, well-attributed note about a real story, not a padded 'article' pretending to more substance than a headline supports.
";
    }
}
