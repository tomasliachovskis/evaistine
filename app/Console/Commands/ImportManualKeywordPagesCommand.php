<?php

namespace App\Console\Commands;

use App\Services\KeywordImport\KeywordManualImporter;
use App\Services\KeywordImport\KeywordPageGptService;
use Illuminate\Console\Command;

class ImportManualKeywordPagesCommand extends Command
{
    protected $signature = 'keywords:import-manual
                            {file=database/data/bulk-keyword-pages-2026.json : Path to JSON keyword list}
                            {--dry-run : List keywords only, no GPT}
                            {--apply : GPT + save to database}
                            {--slug= : Process only one slug}
                            {--force : Re-import even if slug already exists}';

    protected $description = 'Import keyword pages from a manual JSON list with GPT content generation';

    public function handle(
        KeywordManualImporter $importer,
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

        $data = json_decode(file_get_contents($file), true);
        $entries = $data['keywords'] ?? [];

        if ($entries === []) {
            $this->error('No keywords found in file.');

            return self::FAILURE;
        }

        $dryRun = (bool) $this->option('dry-run');
        $apply = (bool) $this->option('apply');
        $onlySlug = $this->option('slug') ?: null;
        $force = (bool) $this->option('force');

        if (!$dryRun && !$apply) {
            $this->warn('Preview mode – GPT runs but DB not updated. Pass --apply to save.');
        }

        if (!$dryRun && !$gpt->isConfigured()) {
            $this->error('OPENAI_API_KEY not set.');

            return self::FAILURE;
        }

        $count = count($entries);
        if ($onlySlug) {
            $this->info("Importing slug \"{$onlySlug}\" from {$count} entries…");
        } else {
            $this->info("Importing from {$count} keywords…");
        }

        $bar = $this->output->createProgressBar($onlySlug ? 1 : $count);
        $bar->start();

        $result = $importer->run(
            entries: $entries,
            apply: $apply,
            useGpt: !$dryRun,
            force: $force,
            onlySlug: $onlySlug,
            onProgress: fn () => $bar->advance(),
        );

        $bar->finish();
        $this->newLine(2);

        $stats = $result['stats'];
        $this->info("Imported: {$stats['imported']} | Skipped: {$stats['skipped']} | Failed: {$stats['failed']} | Published: {$stats['published']}");

        if ($result['skipped'] !== []) {
            $this->newLine();
            $this->comment('Skipped:');
            $this->table(
                ['Slug', 'Title', 'Reason'],
                array_map(fn ($r) => [
                    $r['slug'] ?? '',
                    $r['title'] ?? '',
                    $r['reason'] ?? '',
                ], array_slice($result['skipped'], 0, 20))
            );
            if (count($result['skipped']) > 20) {
                $this->comment('… and ' . (count($result['skipped']) - 20) . ' more');
            }
        }

        if ($result['imported'] !== []) {
            $this->newLine();
            $this->table(
                ['Slug', 'H1', 'Primary', 'Matches', 'Rec.'],
                array_map(fn ($r) => [
                    $r['slug'],
                    $r['h1'],
                    $r['primary'],
                    $r['matches'],
                    $r['recommendation'],
                ], $result['imported'])
            );
        }

        if ($result['failed'] !== []) {
            $this->newLine();
            $this->error('Failed:');
            $this->table(
                ['Slug', 'Title', 'Reason'],
                array_map(fn ($r) => [$r['slug'], $r['title'], $r['reason']], $result['failed'])
            );
        }

        if ($apply) {
            $this->call('keywords:audit');
        }

        return self::SUCCESS;
    }
}
