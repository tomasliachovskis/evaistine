<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Category extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'description',
        'slug',
        'parent_id',
        'hide',
        'seo_title',
        'meta_title',
        'meta_description',
        'faq',
    ];

    protected $casts = [
        'hide' => 'boolean',
        'faq' => 'array',
    ];

    public function products()
    {
        return $this->hasMany(Product::class);
    }

    public function parent()
    {
        return $this->belongsTo(Category::class, 'parent_id');
    }

    public function children()
    {
        return $this->hasMany(Category::class, 'parent_id');
    }

    public function discounts()
    {
        return $this->hasManyThrough(Discount::class, Product::class);
    }

    /** @var array<int, int>|null */
    private static ?array $popularIds = null;

    /**
     * Ids of config('categories.popular_slugs'), looked up once per request
     * (deal scoring calls this per discount).
     *
     * @return array<int, int>
     */
    public static function popularIds(): array
    {
        return self::$popularIds ??= self::whereIn('slug', config('categories.popular_slugs', []))
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();
    }
}
