<?php

namespace App\Console\Commands;

use App\Models\KeywordPage;
use App\Services\KeywordPageService;
use Illuminate\Console\Command;

class AuditKeywordPagesCommand extends Command
{
    protected $signature = 'keywords:audit';

    protected $description = 'Audit keyword pages: matching offer counts and publish recommendations';

    public function handle(KeywordPageService $keywordPageService): int
    {
        $pages = KeywordPage::query()
            ->orderBy('sort_order')
            ->orderBy('title')
            ->get();

        if ($pages->isEmpty()) {
            $this->warn('No keyword pages found.');

            return self::SUCCESS;
        }

        $rows = [];

        foreach ($pages as $page) {
            $count = $keywordPageService->countMatchingOffersForPage($page);
            $minRequired = $page->min_active_offers;
            $recommendation = $this->recommendation($page, $count, $minRequired);

            $rows[] = [
                $page->slug,
                $page->is_published ? 'yes' : 'no',
                $count,
                $minRequired,
                $recommendation,
            ];
        }

        $this->table(
            ['Slug', 'Published', 'Matches', 'Min required', 'Recommendation'],
            $rows
        );

        $unpublish = collect($rows)->filter(fn ($row) => str_starts_with($row[4], 'UNPUBLISH'))->count();
        $publish = collect($rows)->filter(fn ($row) => str_starts_with($row[4], 'PUBLISH'))->count();
        $ok = collect($rows)->filter(fn ($row) => $row[4] === 'OK')->count();

        $this->newLine();
        $this->info("Summary: {$ok} OK, {$publish} should publish, {$unpublish} should unpublish.");

        return self::SUCCESS;
    }

    private function recommendation(KeywordPage $page, int $count, int $minRequired): string
    {
        if ($count >= $minRequired && !$page->is_published) {
            return 'PUBLISH – enough matching offers';
        }

        if ($count < $minRequired && $page->is_published) {
            return 'UNPUBLISH – below min_active_offers';
        }

        if ($count < $minRequired) {
            return 'KEEP UNPUBLISHED – below min_active_offers';
        }

        return 'OK';
    }
}
