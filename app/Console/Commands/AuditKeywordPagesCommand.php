<?php

namespace App\Console\Commands;

use App\Models\KeywordPage;
use App\Services\KeywordPageService;
use Illuminate\Console\Command;

class AuditKeywordPagesCommand extends Command
{
    protected $signature = 'keywords:audit {--refresh : Recompute offer counts before auditing}';

    protected $description = 'Audit keyword pages: matching offer counts and publish recommendations';

    public function handle(KeywordPageService $keywordPageService): int
    {
        if ($this->option('refresh')) {
            $this->info('Refreshing stored offer counts...');
            $keywordPageService->refreshAllOfferCounts();
            $this->newLine();
        }

        $pages = KeywordPage::query()
            ->orderBy('sort_order')
            ->orderBy('title')
            ->get();

        if ($pages->isEmpty()) {
            $this->warn('No keyword pages found.');

            return self::SUCCESS;
        }

        $rows = [];
        $broken = [];

        foreach ($pages as $page) {
            $count = (int) $page->matching_offers_count;
            $displayed = (int) $page->displayed_offers_count;
            $minRequired = $page->min_active_offers;
            $recommendation = $this->recommendation($page, $count, $displayed, $minRequired);

            if ($count > 0 && $displayed === 0) {
                $broken[] = $page->slug;
            }

            $rows[] = [
                $page->slug,
                $page->is_published ? 'yes' : 'no',
                $count,
                $displayed,
                $minRequired,
                $page->offers_counted_at?->format('Y-m-d H:i') ?? '—',
                $recommendation,
            ];
        }

        $this->table(
            ['Slug', 'Published', 'Matches', 'Displayed', 'Min required', 'Counted at', 'Recommendation'],
            $rows
        );

        $unpublish = collect($rows)->filter(fn ($row) => str_starts_with($row[6], 'UNPUBLISH'))->count();
        $publish = collect($rows)->filter(fn ($row) => str_starts_with($row[6], 'PUBLISH'))->count();
        $ok = collect($rows)->filter(fn ($row) => $row[6] === 'OK')->count();
        $brokenCount = collect($rows)->filter(fn ($row) => str_starts_with($row[6], 'BROKEN'))->count();
        $chipEligible = KeywordPage::query()->where('is_chip', true)->count();

        $this->newLine();
        $this->info("Summary: {$ok} OK, {$publish} should publish, {$unpublish} should unpublish, {$brokenCount} broken, {$chipEligible} chips marked.");

        if (!$this->option('refresh') && collect($rows)->contains(fn ($row) => $row[5] === '—')) {
            $this->newLine();
            $this->warn('Some pages have no stored counts. Run: sail artisan keywords:refresh-counts');
        }

        if ($broken !== []) {
            $this->newLine();
            $this->error('Broken pages (API returns 404 despite matches): ' . implode(', ', $broken));
        }

        return self::SUCCESS;
    }

    private function recommendation(KeywordPage $page, int $count, int $displayed, int $minRequired): string
    {
        if ($count > 0 && $displayed === 0) {
            return 'BROKEN – matches found but all filtered out (check exclude_terms)';
        }

        if ($count >= $minRequired && !$page->is_published) {
            return 'PUBLISH – enough matching offers';
        }

        // Published pages are never taken offline for a lack of offers: they
        // show the empty state and recover on their own.
        if ($count < $minRequired && $page->is_published) {
            return 'KEEP PUBLISHED – no offers now, shows the empty state';
        }

        if ($count < $minRequired) {
            return 'KEEP UNPUBLISHED – below min_active_offers';
        }

        return 'OK';
    }
}
