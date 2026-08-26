<?php

namespace App\Support;

// Ported from discount/src/lib/schema-utils.ts generateFaqSchema.
class FaqSchema
{
    public static function build(array $items): array
    {
        return [
            '@context' => 'https://schema.org',
            '@type' => 'FAQPage',
            'mainEntity' => collect($items)->map(fn ($item) => [
                '@type' => 'Question',
                'name' => $item['question'],
                'acceptedAnswer' => [
                    '@type' => 'Answer',
                    'text' => strip_tags($item['answer']),
                ],
            ])->all(),
        ];
    }
}
