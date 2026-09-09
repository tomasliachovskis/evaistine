<?php

namespace App\Console\CommandsQuarantine;

use App\Services\KeywordImport\KeywordCandidateSelector;
use Illuminate\Console\Command;

class SelectKeywordPageCandidatesCommand extends Command
{
    protected $signature = 'keywords:select-candidates
                            {file=kw-akcija.csv : Path to Ahrefs UTF-16 TSV export}
                            {--output= : Output file path (default: storage/app/keyword-candidates.json)}
                            {--format=json : Output format: json or csv}
                            {--rejected= : Rejected keywords output path}';

    protected $description = 'Select SEO landing page candidates from Ahrefs keyword CSV';

    public function handle(KeywordCandidateSelector $selector): int
    {
        $file = $this->argument('file');
        if (!str_starts_with($file, '/')) {
            $file = base_path($file);
        }

        if (!is_readable($file)) {
            $this->error("File not found: {$file}");

            return self::FAILURE;
        }

        $this->info('Selecting candidates…');
        $result = $selector->select($file);
        $stats = $result['stats'];

        $this->newLine();
        $this->info("Rows: {$stats['csv_rows']} | Accepted: {$stats['accepted']} | Rejected: {$stats['rejected']}");
        $this->info("Candidates: {$stats['candidates']} (high: {$stats['high']}, medium: {$stats['medium']}, low: {$stats['low']})");

        if ($result['candidates'] !== []) {
            $this->table(
                ['URL', 'Type', 'Priority', 'Volume', 'KWs', 'Main keyword'],
                array_map(fn ($c) => [
                    $c['page_url'],
                    $c['page_type'],
                    $c['priority'],
                    $c['total_volume'],
                    $c['keyword_count'],
                    mb_substr($c['main_keyword'], 0, 40),
                ], array_slice($result['candidates'], 0, 40))
            );

            if (count($result['candidates']) > 40) {
                $this->comment('… and ' . (count($result['candidates']) - 40) . ' more (see output file)');
            }
        }

        $format = $this->option('format');
        $outputPath = $this->option('output') ?: storage_path('app/keyword-candidates.json');

        if ($format === 'csv') {
            $this->writeCsv($outputPath, $result['candidates']);
        } else {
            $export = array_map(fn ($c) => [
                'page_url' => $c['page_url'],
                'page_type' => $c['page_type'],
                'main_keyword' => $c['main_keyword'],
                'slug' => $c['slug'],
                'total_volume' => $c['total_volume'],
                'keyword_count' => $c['keyword_count'],
                'included_keywords' => $c['included_keywords'],
                'priority' => $c['priority'],
                'reason' => $c['reason'],
                'term_groups' => $c['term_groups'],
            ], $result['candidates']);

            file_put_contents(
                $outputPath,
                json_encode(['candidates' => $export, 'stats' => $stats], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT)
            );
        }

        $rejectedPath = $this->option('rejected') ?: storage_path('app/keyword-rejected.json');
        file_put_contents(
            $rejectedPath,
            json_encode(['rejected' => $result['rejected'], 'count' => count($result['rejected'])], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT)
        );

        $this->newLine();
        $this->info("Candidates saved: {$outputPath}");
        $this->info("Rejected saved: {$rejectedPath}");

        return self::SUCCESS;
    }

    /**
     * @param  list<array>  $candidates
     */
    private function writeCsv(string $path, array $candidates): void
    {
        $handle = fopen($path, 'w');
        fputcsv($handle, [
            'page_url', 'page_type', 'main_keyword', 'slug', 'total_volume',
            'keyword_count', 'included_keywords', 'priority', 'reason',
        ]);

        foreach ($candidates as $c) {
            fputcsv($handle, [
                $c['page_url'],
                $c['page_type'],
                $c['main_keyword'],
                $c['slug'],
                $c['total_volume'],
                $c['keyword_count'],
                implode(' | ', $c['included_keywords']),
                $c['priority'],
                $c['reason'],
            ]);
        }

        fclose($handle);
    }
}
