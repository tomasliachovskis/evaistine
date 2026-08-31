<?php

namespace App\Support;

// Ported from discount/src/lib/canonical-utils.ts. Canonical URLs only ever
// carry `page` (if != 1) and `order` (if != 'popular') — every other filter
// param is stripped to avoid duplicate-content canonicals. Search-result and
// filtered pages are noindexed the same way the Next.js frontend did.
class CanonicalUrl
{
    private const BASE_URL = 'https://superakcijos.lt';

    public static function origin(): string
    {
        return self::BASE_URL;
    }

    public static function build(string $path, array $query = []): string
    {
        $params = [];

        if (!empty($query['page']) && (string) $query['page'] !== '1') {
            $params['page'] = $query['page'];
        }

        if (!empty($query['order']) && $query['order'] !== 'popular') {
            $params['order'] = $query['order'];
        }

        $url = self::BASE_URL . $path;

        return $params === [] ? $url : $url . '?' . http_build_query($params);
    }

    public static function shouldIndex(string $path, array $query = []): bool
    {
        if (str_contains($path, '/paieska/')) {
            return false;
        }

        if (!empty($query['order'])) {
            return false;
        }

        foreach ($query as $key => $value) {
            if ($key !== 'page' && $value !== null && $value !== '') {
                return false;
            }
        }

        return true;
    }

    public static function robotsMeta(string $path, array $query = []): string
    {
        if (!self::shouldIndex($path, $query)) {
            return 'noindex, nofollow, noarchive, nosnippet';
        }

        if (!empty($query['page']) && (string) $query['page'] !== '1') {
            return 'noindex, follow';
        }

        return 'index, follow';
    }
}
