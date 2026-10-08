<?php

namespace App\Console\Commands;

use App\Models\KeywordPage;
use App\Models\KeywordPageProduct;
use App\Services\KeywordPageProductMapper;
use App\Services\ProductSearchIndex;
use App\Support\CacheVersion;
use Illuminate\Console\Command;

// Rebuilds keyword_page_products, the keyword page -> product mapping every
// keyword page reads (grid, counts, teasers, product-page links). Matching
// lives in KeywordPageProductMapper. Every page is mapped, published or not,
// so keywords:refresh-counts/audit can tell when a draft has enough offers.
class MapProductsToKeywordPages extends Command
{
    protected $signature = 'keywords:map-products {--page= : Only rebuild for this one keyword page slug}';

    protected $description = 'Rebuild keyword_page_products through the Meilisearch products index';

    public function handle(KeywordPageProductMapper $mapper): int
    {
        if (! ProductSearchIndex::enabled()) {
            $this->error('MEILISEARCH_HOST is not set; keyword pages map their products through Meilisearch.');

            return self::FAILURE;
        }

        $query = KeywordPage::query();
        if ($slug = $this->option('page')) {
            $query->where('slug', $slug);
        }

        $pages = $query->orderBy('slug')->get();
        $totalRows = 0;

        foreach ($pages as $page) {
            $rows = $mapper->mapPage($page);
            $totalRows += $rows;
            $this->line("{$page->slug}: {$rows} products");
        }

        // Prune rows of deleted pages only on a full run.
        if ($this->option('page') === null) {
            KeywordPageProduct::whereNotIn('keyword_page_id', $pages->pluck('id'))->delete();
        }

        CacheVersion::bump('keywords');
        $this->info("Done. {$totalRows} rows across {$pages->count()} pages.");

        return self::SUCCESS;
    }
}
