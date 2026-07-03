<?php

namespace App\Services;

use App\Models\Category;
use App\Support\FoodCategorySlugs;

class KeywordPageCategoryResolver
{
    /**
     * @param  list<string>  $slugs
     * @return list<string>
     */
    public function resolveListingCategorySlugs(array $slugs, int $limit = 2): array
    {
        $resolved = [];

        foreach ($slugs as $slug) {
            $slug = trim((string) $slug);
            if ($slug === '') {
                continue;
            }

            if (in_array($slug, FoodCategorySlugs::ALL, true) && !in_array($slug, $resolved, true)) {
                $resolved[] = $slug;
            }

            if (count($resolved) >= $limit) {
                return $resolved;
            }
        }

        foreach ($slugs as $slug) {
            if (count($resolved) >= $limit) {
                break;
            }

            $slug = trim((string) $slug);
            if ($slug === '' || in_array($slug, FoodCategorySlugs::ALL, true)) {
                continue;
            }

            $listingSlug = $this->resolveSubcategoryToListingSlug($slug);
            if ($listingSlug !== null && !in_array($listingSlug, $resolved, true)) {
                $resolved[] = $listingSlug;
            }
        }

        return $resolved;
    }

    private function resolveSubcategoryToListingSlug(string $slug): ?string
    {
        if (in_array($slug, FoodCategorySlugs::ALL, true)) {
            return $slug;
        }

        $category = Category::query()->where('slug', $slug)->first();
        while ($category !== null) {
            if (in_array($category->slug, FoodCategorySlugs::ALL, true)) {
                return $category->slug;
            }

            $category = $category->parent_id
                ? Category::query()->find($category->parent_id)
                : null;
        }

        return null;
    }
}
