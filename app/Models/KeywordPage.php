<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class KeywordPage extends Model
{
    use HasFactory;

    protected $fillable = [
        'slug',
        'title',
        'emoji',
        'grammar_plural',
        'grammar_genitive',
        'grammar_dative',
        'h1',
        'primary_keywords',
        'secondary_keywords',
        'brands',
        'meta_title',
        'meta_description',
        'search_terms',
        'category_slugs',
        'exclude_terms',
        'intro_html',
        'tips',
        'faq',
        'related_slugs',
        'min_active_offers',
        'is_published',
        'sort_order',
    ];

    protected $casts = [
        'search_terms' => 'array',
        'primary_keywords' => 'array',
        'secondary_keywords' => 'array',
        'brands' => 'array',
        'category_slugs' => 'array',
        'exclude_terms' => 'array',
        'tips' => 'array',
        'faq' => 'array',
        'related_slugs' => 'array',
        'is_published' => 'boolean',
        'min_active_offers' => 'integer',
        'sort_order' => 'integer',
    ];

    public function scopePublished($query)
    {
        return $query->where('is_published', true);
    }
}
