<?php

namespace App\Support;

class ProductPackSizeExtractor
{
    private const UNIT_ALT = 'kg|g|ml|l|vnt\.?|rink\.?|rit\.?|pak\.?|kompl\.?|pora|kaps\.?|skard\.?|dėž\.?|skalb\.?|mm|cm|km|m';

    private const UNITS_STRIP_TRAILING_DOT = 'mm|cm|km|kg|g|ml|l|vnt|m';

    public static function extract(?string $info): ?string
    {
        return self::extractAndStrip($info)['size'];
    }

    public static function extractAndStrip(?string $info): array
    {
        if ($info === null) {
            return ['size' => null, 'info' => null];
        }

        if (trim($info) === '') {
            return ['size' => null, 'info' => null];
        }

        $paren = self::findParenthesizedSize($info);
        if ($paren !== null) {
            return self::buildResult($info, $paren['normalized'], $paren['offset'], $paren['length']);
        }

        $candidates = self::findCandidates($info);
        if (count($candidates) === 0) {
            return ['size' => null, 'info' => $info];
        }

        if (count($candidates) >= 2) {
            $last = $candidates[count($candidates) - 1];
            if ($last['normalized'] === '1 kg' || $last['normalized'] === '1 l') {
                array_pop($candidates);
            }
        }

        $first = $candidates[0];

        return self::buildResult($info, $first['normalized'], $first['offset'], $first['length']);
    }

    private static function buildResult(string $original, string $size, int $offset, int $length): array
    {
        $stripped = substr_replace($original, '', $offset, $length);
        $stripped = self::cleanupStripped($stripped);

        return [
            'size' => $size,
            'info' => $stripped === '' ? null : $stripped,
        ];
    }

    private static function findParenthesizedSize(string $info): ?array
    {
        if (!preg_match_all('/\(([^)]+)\)/u', $info, $matches, PREG_OFFSET_CAPTURE)) {
            return null;
        }

        foreach ($matches[1] as $group) {
            [$content, $innerOffset] = $group;
            $candidates = self::findCandidates($content);
            foreach ($candidates as $candidate) {
                if (!self::isMassOrVolume($candidate['normalized'])) {
                    continue;
                }

                return [
                    'normalized' => $candidate['normalized'],
                    'offset' => $innerOffset + $candidate['offset'],
                    'length' => $candidate['length'],
                ];
            }
        }

        return null;
    }

    private static function findCandidates(string $text): array
    {
        $unit = self::UNIT_ALT;
        $pattern = '/(~)?(\d+(?:[,.]\d+)?)(\s*[x×]\s*\d+(?:[,.]\d+)?)?\s*(?:' . $unit . ')(?!\p{L})(\s*[-–—]\s*\d+(?:[,.]\d+)?\s*(?:' . $unit . ')(?!\p{L}))?/ui';

        if (!preg_match_all($pattern, $text, $matches, PREG_OFFSET_CAPTURE)) {
            return [];
        }

        $results = [];
        foreach ($matches[0] as $match) {
            [$raw, $offset] = $match;
            if ($offset < 0 || $raw === '') {
                continue;
            }

            $results[] = [
                'raw' => $raw,
                'offset' => $offset,
                'length' => strlen($raw),
                'normalized' => self::normalizeSize($raw),
            ];
        }

        return $results;
    }

    private static function normalizeSize(string $raw): string
    {
        $s = trim($raw);
        $s = preg_replace('/(\d),(\d)/u', '$1.$2', $s);
        $s = preg_replace('/(\d)\s*[x×]\s*(\d)/ui', '$1 x $2', $s);
        $s = preg_replace('/(\d)(mm|cm|km|kg|g|ml|l|vnt|rink|rit|pak|kompl|pora|kaps|skard|dėž|skalb|m)\b/ui', '$1 $2', $s);
        $s = preg_replace('/[ \t]+/u', ' ', $s);
        $s = mb_strtolower(trim($s), 'UTF-8');
        if (preg_match('/ (?:' . self::UNITS_STRIP_TRAILING_DOT . ')\.*$/u', $s)) {
            $s = rtrim($s, '.');
        }

        return $s;
    }

    private static function isMassOrVolume(string $normalized): bool
    {
        return (bool) preg_match('/\s(?:kg|g|ml|l)$/u', $normalized);
    }

    private static function cleanupStripped(string $s): string
    {
        $s = preg_replace('/\(\s*\)/u', '', $s);
        $s = preg_replace('/[ \t]+/u', ' ', $s);
        $s = preg_replace('/\s*,(\s*,)+\s*/u', ', ', $s);
        $s = preg_replace('/\s*;(\s*;)+\s*/u', '; ', $s);
        $s = preg_replace('/\s+([,;])/u', '$1', $s);
        $s = preg_replace('/^[\s,;|\/–—-]+/u', '', $s);
        $s = preg_replace('/[\s,;|\/–—-]+$/u', '', $s);

        if (preg_match('/^[\s,;|\/=–—-]*\d+(?:[,.]\d+)?\s*€\.?\s*$/u', $s)) {
            return '';
        }

        return trim($s);
    }
}
