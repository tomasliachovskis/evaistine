<?php

namespace App\Console\CommandsQuarantine;

use App\Support\ProductPackSizeExtractor;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class PackSizePreview extends Command
{
    protected $signature = 'pack-size:preview
        {--limit=0 : Limit rows (0 = no limit)}
        {--only-missing : Show only rows where size could not be extracted}
        {--csv= : Write output to CSV file at given path}';

    protected $description = 'Preview ProductPackSizeExtractor against historical info table values';

    public function handle(): int
    {
        $limit = (int) $this->option('limit');
        $onlyMissing = (bool) $this->option('only-missing');
        $csvPath = $this->option('csv');

        $query = DB::table('info')->select('id', 'val')->orderBy('id');
        if ($limit > 0) {
            $query->limit($limit);
        }

        $rows = $query->get();

        $csvHandle = null;
        if ($csvPath) {
            $csvHandle = fopen($csvPath, 'w');
            if ($csvHandle === false) {
                $this->error("Failed to open CSV file: {$csvPath}");
                return self::FAILURE;
            }
            fputcsv($csvHandle, ['id', 'val', 'size', 'stripped_info']);
        }

        $total = 0;
        $withSize = 0;
        $printRows = [];

        foreach ($rows as $row) {
            $total++;
            $result = ProductPackSizeExtractor::extractAndStrip($row->val);
            $size = $result['size'];
            $stripped = $result['info'];

            if ($size !== null) {
                $withSize++;
            }

            if ($onlyMissing && $size !== null) {
                continue;
            }

            $printRows[] = [
                'id' => $row->id,
                'val' => $row->val,
                'size' => $size ?? '',
                'stripped_info' => $stripped ?? '',
            ];

            if ($csvHandle) {
                fputcsv($csvHandle, [$row->id, $row->val, $size ?? '', $stripped ?? '']);
            }
        }

        if ($csvHandle) {
            fclose($csvHandle);
            $this->info("CSV written to: {$csvPath}");
        }

        if (count($printRows) > 0) {
            if (count($printRows) <= 60) {
                $this->table(['id', 'val', 'size', 'stripped_info'], $printRows);
            } else {
                foreach ($printRows as $r) {
                    $this->line(sprintf('%6s | %-70s | %-14s | %s',
                        $r['id'],
                        mb_strimwidth($r['val'], 0, 70, '…'),
                        mb_strimwidth($r['size'], 0, 14, '…'),
                        $r['stripped_info']
                    ));
                }
            }
        }

        $missing = $total - $withSize;
        $coverage = $total > 0 ? round($withSize / $total * 100, 1) : 0.0;
        $this->info("Total: {$total} | with size: {$withSize} ({$coverage}%) | missing: {$missing}");

        return self::SUCCESS;
    }
}
