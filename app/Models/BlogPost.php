<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class BlogPost extends Model
{
    use HasFactory;

    protected $fillable = [
        'title',
        'slug',
        'content',
        'published_at',
        'meta_title',
        'meta_description',
        'status',
        'review_image',
    ];

    protected $casts = [
        'published_at' => 'datetime',
    ];

    public function scopePublished($query)
    {
        return $query->where('status', 'published')
            ->whereNotNull('published_at')
            ->where('published_at', '<=', now());
    }

    // Ported from discount/src/lib/blog-utils.ts getBlogPostImageUrl — first
    // <img> src found in the post's rich-text content, used as the card thumbnail.
    public function imageUrl(): ?string
    {
        if (preg_match('/<img[^>]+src="([^"]+)"/i', (string) $this->content, $matches)) {
            return $matches[1];
        }

        return null;
    }

    // Ported from discount/src/lib/blog-utils.ts getBlogPostExcerpt.
    public function excerpt(int $maxLength = 220): ?string
    {
        $meta = trim((string) $this->meta_description);
        if ($meta !== '') {
            return $meta;
        }

        $text = trim(preg_replace('/\s+/', ' ', strip_tags((string) $this->content)));
        if ($text === '') {
            return null;
        }

        if (mb_strlen($text) <= $maxLength) {
            return $text;
        }

        return mb_substr($text, 0, $maxLength) . '…';
    }
}
