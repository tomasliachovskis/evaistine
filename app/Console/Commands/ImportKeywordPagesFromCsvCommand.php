<?php

namespace App\Console\Commands;

use App\Services\KeywordImport\KeywordCsvGrouper;
use App\Services\KeywordImport\KeywordCsvImporter;
use App\Services\KeywordImport\KeywordCsvParser;
use App\Services\KeywordImport\KeywordPageGptService;
use Illuminate\Console\Command;

class ImportKeywordPagesFromCsvCommand extends Command
{
    protected $signature = 'keywords:import-csv
                            {file=kw-akcija.csv : Path to UTF-16 TSV export}
                            {--candidates= : Path to keyword-candidates.json from keywords:select-candidates}
                            {--dry-run : List groups only, no GPT}
                            {--apply : GPT + save to database}
                            {--priority=* : Only import these priorities (high, medium, low)}
                            {--group= : Process only one slug}';

    protected $description = 'Import keyword pages from CSV (use --candidates for filtered import)';

    public function handle(
        KeywordCsvParser $parser,
        KeywordCsvGrouper $grouper,
        KeywordCsvImporter $importer,
        KeywordPageGptService $gpt,
    ): int {
        $file = $this->argument('file');
        if (!str_starts_with($file, '/')) {
            $file = base_path($file);
        }

        if (!is_readable($file)) {
            $this->error("File not found: {$file}");

            return self::FAILURE;
        }

        $candidatesPath = $this->option('candidates');
        if ($candidatesPath && !str_starts_with($candidatesPath, '/')) {
            $candidatesPath = base_path($candidatesPath);
        }

        $dryRun = (bool) $this->option('dry-run');
        $apply = (bool) $this->option('apply');
        $onlyGroup = $this->option('group') ?: null;
        $priorities = $this->option('priority') ?: null;

        if (!$dryRun && !$gpt->isConfigured()) {
            $this->error('OPENAI_API_KEY not set.');

            return self::FAILURE;
        }

        if ($candidatesPath) {
            return $this->importFromCandidates($importer, $file, $candidatesPath, $apply, $dryRun, $onlyGroup, $priorities);
        }

        $this->warn('No --candidates file. Run: php artisan keywords:select-candidates kw-akcija.csv');

        return $this->importLegacy($parser, $grouper, $importer, $gpt, $file, $apply, $dryRun, $onlyGroup);
    }

    private function importFromCandidates(
        KeywordCsvImporter $importer,
        string $file,
        string $candidatesPath,
        bool $apply,
        bool $dryRun,
        ?string $onlyGroup,
        ?array $priorities,
    ): int {
        if (!is_readable($candidatesPath)) {
            $this->error("Candidates file not found: {$candidatesPath}");

            return self::FAILURE;
        }

        if (!$apply && !$dryRun) {
            $this->warn('Preview mode – GPT runs but DB not updated. Pass --apply to save.');
        }

        $data = json_decode(file_get_contents($candidatesPath), true);
        $count = count($data['candidates'] ?? []);
        $this->info("Importing from {$count} candidates…");

        $bar = $this->output->createProgressBar($count);
        $bar->start();

        $result = $importer->runFromCandidates(
            csvPath: $file,
            candidatesPath: $candidatesPath,
            apply: $apply,
            useGpt: !$dryRun,
            onlySlug: $onlyGroup,
            priorities: $priorities,
            onProgress: fn () => $bar->advance(),
        );

        $bar->finish();
        $this->newLine(2);

        $stats = $result['stats'];
        $this->info("Imported: {$stats['imported']} | Skipped: {$stats['skipped']} | Failed: {$stats['failed']} | Published: {$stats['published']}");

        if ($result['imported'] !== []) {
            $this->table(
                ['Slug', 'Type', 'H1', 'Primary', 'Matches', 'Rec.'],
                array_map(fn ($r) => [
                    $r['slug'], $r['page_type'] ?? '', $r['h1'], $r['primary'], $r['matches'], $r['recommendation'],
                ], $result['imported'])
            );
        }

        if ($apply) {
            $this->call('keywords:audit');
        }

        return self::SUCCESS;
    }

    private function importLegacy(
        KeywordCsvParser $parser,
        KeywordCsvGrouper $grouper,
        KeywordCsvImporter $importer,
        KeywordPageGptService $gpt,
        string $file,
        bool $apply,
        bool $dryRun,
        ?string $onlyGroup,
    ): int {
        $rows = $parser->parse($file);
        $groups = $grouper->group($rows);

        if ($onlyGroup) {
            $needle = mb_strtolower(trim($onlyGroup));
            $groups = array_filter($groups, fn ($g) => $g['slug'] === $needle);
        }

        $this->info('Groups: ' . count($groups));

        if ($dryRun) {
            $this->table(
                ['Slug', 'Volume', 'Keywords', 'Sources'],
                array_map(fn ($g) => [
                    $g['slug'], $g['total_volume'], count($g['keywords']),
                    implode(', ', $g['source_groups'] ?? []),
                ], $groups)
            );

            return self::SUCCESS;
        }

        $bar = $this->output->createProgressBar(count($groups));
        $bar->start();

        $result = $importer->run(
            path: $file,
            apply: $apply,
            useGpt: true,
            onlyGroup: $onlyGroup,
            onProgress: fn ($g) => $bar->advance(),
        );

        $bar->finish();
        $this->newLine(2);
        $this->info('Done. Imported: ' . $result['stats']['imported']);

        return self::SUCCESS;
    }
}
