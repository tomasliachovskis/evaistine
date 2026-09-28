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
                    // Answers are HTML (rendered with {!! !!}), so dynamic
                    // text in them is entity-escaped ("Cash&amp;Carry") —
                    // decode after stripping tags so JSON-LD gets plain text.
                    'text' => html_entity_decode(strip_tags($item['answer']), ENT_QUOTES | ENT_HTML5, 'UTF-8'),
                ],
            ])->all(),
        ];
    }
}
