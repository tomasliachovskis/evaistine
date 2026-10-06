<?php

namespace App\Support;

use App\Models\BlogPost;

// NewsArticle JSON-LD for /naujienos/{slug} — the article pages previously
// carried only a BreadcrumbList, so Google had no headline/date/publisher
// signal for them (Top stories / article rich results need these).
class NewsArticleSchema
{
    public static function build(BlogPost $post, string $canonical, string $description): array
    {
        $publisher = [
            '@type' => 'Organization',
            'name' => 'eVaistine.lt',
            'url' => CanonicalUrl::origin(),
            'logo' => [
                '@type' => 'ImageObject',
                'url' => CanonicalUrl::origin() . '/assets/logo.svg',
            ],
        ];

        $schema = [
            '@context' => 'https://schema.org',
            '@type' => 'NewsArticle',
            'headline' => mb_substr($post->title, 0, 110),
            'mainEntityOfPage' => ['@type' => 'WebPage', '@id' => $canonical],
            'url' => $canonical,
            'inLanguage' => 'lt-LT',
            'datePublished' => $post->published_at?->toIso8601String(),
            'dateModified' => ($post->updated_at ?? $post->published_at)?->toIso8601String(),
            'author' => $publisher,
            'publisher' => $publisher,
        ];

        if ($description !== '') {
            $schema['description'] = $description;
        }

        if ($image = $post->imageUrl()) {
            // Cover images can be stored root-relative; schema needs absolute.
            $schema['image'] = [str_starts_with($image, '/') ? CanonicalUrl::origin() . $image : $image];
        }

        return array_filter($schema, fn ($value) => $value !== null);
    }
}
