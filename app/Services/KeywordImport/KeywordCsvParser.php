<?php

namespace App\Services\KeywordImport;

class KeywordCsvParser
{
    /**
     * @return list<array{
     *   term_group: string,
     *   keyword: string,
     *   volume: int,
     *   category: string,
     *   intents: string,
     *   difficulty: int,
     *   cpc: string,
     *   parent_keyword: string,
     * }>
     */
    public function parse(string $path): array
    {
        if (!is_readable($path)) {
            throw new \InvalidArgumentException("CSV file not readable: {$path}");
        }

        $raw = file_get_contents($path);
        $utf8 = $this->toUtf8($raw);

        $stream = fopen('php://memory', 'r+');
        fwrite($stream, $utf8);
        rewind($stream);

        $header = fgetcsv($stream, 0, "\t");
        if ($header === false) {
            fclose($stream);

            return [];
        }

        $header = array_map(fn ($col) => trim((string) $col), $header);
        $rows = [];

        while (($line = fgetcsv($stream, 0, "\t")) !== false) {
            if (count($line) < 2) {
                continue;
            }

            $assoc = [];
            foreach ($header as $index => $column) {
                $assoc[$column] = trim((string) ($line[$index] ?? ''));
            }

            $keyword = $assoc['Keyword'] ?? '';
            if ($keyword === '') {
                continue;
            }

            $rows[] = [
                'term_group' => $assoc['Term group'] ?? '',
                'keyword' => $keyword,
                'volume' => (int) preg_replace('/\D/', '', $assoc['Volume'] ?? '0'),
                'category' => $assoc['Category'] ?? '',
                'intents' => $assoc['Intents'] ?? '',
                'difficulty' => (int) preg_replace('/\D/', '', $assoc['Difficulty'] ?? '0'),
                'cpc' => $assoc['CPC'] ?? '',
                'parent_keyword' => $assoc['Parent Keyword'] ?? '',
            ];
        }

        fclose($stream);

        return $rows;
    }

    private function toUtf8(string $raw): string
    {
        if (str_starts_with($raw, "\xFF\xFE")) {
            return mb_convert_encoding($raw, 'UTF-8', 'UTF-16LE');
        }

        if (str_starts_with($raw, "\xFE\xFF")) {
            return mb_convert_encoding($raw, 'UTF-8', 'UTF-16BE');
        }

        return $raw;
    }
}
