<?php

namespace App\Console\Commands;

use App\Services\NewsArticleService;
use Illuminate\Console\Command;

class GenerateNewsArticles extends Command
{
    protected $signature = 'news:generate {--limit=3 : Max number of draft articles to create} {--max-age-days=7 : Only consider stories published within this many days}';

    protected $description = 'Research real Lithuanian retail/pricing news via Bing News RSS and draft attributed blog articles for review';

    public function handle(NewsArticleService $service): int
    {
        if (!$service->isConfigured()) {
            $this->error('NewsArticleService is not configured. Please set OPENAI_API_KEY environment variable.');

            return 1;
        }

        $limit = (int) $this->option('limit');
        $maxAgeDays = (int) $this->option('max-age-days');

        $this->info("Fetching real candidate stories from Bing News RSS (last {$maxAgeDays} days)...");
        $posts = $service->generateDrafts($limit, $maxAgeDays);

        if (empty($posts)) {
            $this->warn('No draft articles created — either no new stories were found, or all candidates were already covered or too thin to write about.');

            return 0;
        }

        $this->info("Created " . count($posts) . " draft article(s):");
        foreach ($posts as $post) {
            $this->line("  - [{$post->id}] {$post->title} (source: {$post->source_name})");
        }

        $this->comment('These are saved as drafts (status=draft, published_at=null) — review at /naujienos admin or via tinker before publishing.');

        return 0;
    }
}
