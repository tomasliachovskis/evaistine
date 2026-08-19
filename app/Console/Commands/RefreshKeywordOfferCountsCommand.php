<?php

namespace App\Console\Commands;

use App\Models\KeywordPage;
use App\Services\KeywordPageService;
use App\Support\CacheVersion;
use Illuminate\Console\Command;

class RefreshKeywordOfferCountsCommand extends Command
{
    protected $signature = 'keywords:refresh-counts {slug? : Refresh a single keyword page slug}';

    protected $description = 'Refresh stored matching/displayed offer counts for keyword pages';

    public function handle(KeywordPageService $keywordPageService): int
    {
        $slug = $this->argument('slug');

        $pages = $slug
            ? KeywordPage::query()->where('slug', $slug)->get()
            : KeywordPage::query()->orderBy('sort_order')->orderBy('title')->get();

        if ($pages->isEmpty()) {
            $this->warn($slug ? "Keyword page \"{$slug}\" not found." : 'No keyword pages found.');

            return self::FAILURE;
        }

        $bar = $this->output->createProgressBar($pages->count());
        $bar->start();

        foreach ($pages as $page) {
            $keywordPageService->refreshOfferCounts($page);
            $bar->advance();
        }

        $bar->finish();
        $this->newLine(2);

        CacheVersion::bump('keywords');

        $this->info('Refreshed offer counts for ' . $pages->count() . ' keyword page(s).');

        return self::SUCCESS;
    }
}
