<?php

namespace App\Console\Commands;

use App\Models\CategoryMapper;
use App\Services\CategoryMappingService;
use Illuminate\Console\Command;

class MapCategoryMappers extends Command
{
    protected $signature = 'categories:map-mappers {--dry-run : Only report what would change, without writing}';
    protected $description = 'Deduplicate category_mappers rows and map the remaining unmapped store categories to existing root categories';

    private CategoryMappingService $mappingService;

    public function __construct(CategoryMappingService $mappingService)
    {
        parent::__construct();
        $this->mappingService = $mappingService;
    }

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');

        $this->deduplicate($dryRun);
        $this->mapUnmapped($dryRun);

        return self::SUCCESS;
    }

    private function deduplicate(bool $dryRun): void
    {
        $this->info('Looking for duplicate category_mappers rows...');

        $groups = CategoryMapper::orderBy('id')->get()
            ->groupBy(fn (CategoryMapper $mapper) => $mapper->store . '|' . trim($mapper->store_category));

        $removed = 0;

        foreach ($groups as $group) {
            if ($group->count() < 2) {
                continue;
            }

            $keep = $group->first(fn (CategoryMapper $mapper) => $mapper->category_id !== null) ?? $group->first();
            $toRemove = $group->reject(fn (CategoryMapper $mapper) => $mapper->id === $keep->id);

            $this->line("  store {$keep->store} \"{$keep->store_category}\": keeping #{$keep->id}, removing #" . $toRemove->pluck('id')->implode(', #'));

            if (!$dryRun) {
                CategoryMapper::whereIn('id', $toRemove->pluck('id'))->delete();
            }

            $removed += $toRemove->count();
        }

        $this->info(($dryRun ? '[dry-run] Would remove ' : 'Removed ') . "{$removed} duplicate row(s).");
    }

    private function mapUnmapped(bool $dryRun): void
    {
        if (!$this->mappingService->isConfigured()) {
            $this->warn('CategoryMappingService is not configured (missing OPENAI_API_KEY). Skipping mapping step.');
            return;
        }

        $before = CategoryMapper::whereNull('category_id')->count();
        $this->info("Found {$before} unmapped category_mappers row(s).");

        if ($before === 0) {
            return;
        }

        $result = $this->mappingService->mapCategoryMapperRows($dryRun);

        $this->info(($dryRun ? '[dry-run] Would map ' : 'Mapped ') . "{$result['mapped']} row(s) to a root category.");

        if (!empty($result['unresolved'])) {
            $this->warn('Left unresolved (needs manual mapping):');
            foreach ($result['unresolved'] as $text) {
                $this->line("  - {$text}");
            }
        }
    }
}
