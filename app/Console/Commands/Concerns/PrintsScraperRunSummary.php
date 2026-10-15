<?php

namespace App\Console\Commands\Concerns;

use App\Models\ScraperRun;
use Illuminate\Support\Collection;

// End-of-run summary for scrapers:run --all / flyers:scrape --all: one line per
// store (OK / WARN / FAILED / SKIPPED) plus the stderr lines it printed, so a
// nightly log can be checked at the bottom instead of read top to bottom.
trait PrintsScraperRunSummary
{
    /** @var array<int, array{store: string, run: ?ScraperRun, reason: string}> */
    private array $summaryRows = [];

    private const SUMMARY_MAX_LINES = 10;

    /** $run is null when the store didn't run; $reason then says why. */
    private function recordForSummary(string $store, ?ScraperRun $run, string $reason = 'another run in progress'): void
    {
        $this->summaryRows[] = ['store' => $store, 'run' => $run, 'reason' => $reason];
    }

    private function hasFailedRun(): bool
    {
        return collect($this->summaryRows)
            ->contains(fn ($row) => $row['run']?->status === ScraperRun::STATUS_FAILED);
    }

    /** $itemLabel names what items_count counts ("new flyer(s)" for leaflets). */
    private function printSummary(string $itemLabel = 'item(s)'): void
    {
        $this->line('');
        $this->line('=== Summary');

        $width = max(array_map(fn ($row) => mb_strlen($row['store']), $this->summaryRows) ?: [0]);

        foreach ($this->summaryRows as $row) {
            $run = $row['run'];
            $store = $row['store'] . str_repeat(' ', $width - mb_strlen($row['store']));

            if (!$run) {
                $this->line(sprintf('%-8s %s  %s', 'SKIPPED', $store, $row['reason']));

                continue;
            }

            $lines = $this->problemLines($run);

            $status = match (true) {
                $run->status === ScraperRun::STATUS_FAILED => 'FAILED',
                $lines->isNotEmpty() => 'WARN',
                default => 'OK',
            };

            $note = $run->items_count !== null ? "  {$run->items_count} {$itemLabel}" : '';
            if ($status === 'WARN') {
                $note .= "  {$lines->count()} problem line(s)";
            }
            $this->line(sprintf('%-8s %s  %s%s', $status, $store, $this->formatDuration($run), $note));

            foreach ($lines->take(self::SUMMARY_MAX_LINES) as $line) {
                $this->line('         - ' . mb_substr($line, 0, 200));
            }
            if ($lines->count() > self::SUMMARY_MAX_LINES) {
                $this->line('         - ... ' . ($lines->count() - self::SUMMARY_MAX_LINES) . ' more, see scraper_runs #' . $run->id);
            }
        }
    }

    /**
     * The run's stderr / error as lines, without the noise around an uncaught
     * Node error: stack frames, the source pointer, the error object's own
     * properties (`code: 'MODULE_NOT_FOUND',`) and the "Node.js v24" footer.
     */
    private function problemLines(ScraperRun $run): Collection
    {
        return collect(preg_split('/\R/', (string) $run->error))
            ->map(fn ($line) => trim($line))
            ->reject(fn ($line) => $line === ''
                || preg_match('/^(at .+:\d+(:\d+)?\)?( \{)?|\^+|[{}\[\]]+|throw \w+;|node:internal\S*|Node\.js v[\d.]+|\w+: [\'"\[{\d].*)$/', $line))
            ->values();
    }

    /** The most telling single line: the first "...Error:" / exception line, else the first line. */
    private function mainProblemLine(ScraperRun $run): string
    {
        $lines = $this->problemLines($run);

        return (string) ($lines->first(fn ($line) => preg_match('/(Error|Exception)\b/', $line)) ?? $lines->first());
    }

    private function formatDuration(ScraperRun $run): string
    {
        if (!$run->finished_at) {
            return '-';
        }

        $seconds = (int) $run->started_at->diffInSeconds($run->finished_at, true);

        return $seconds >= 3600
            ? sprintf('%dh%02dm', intdiv($seconds, 3600), intdiv($seconds % 3600, 60))
            : sprintf('%dm%02ds', intdiv($seconds, 60), $seconds % 60);
    }
}
