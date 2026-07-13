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

    /**
     * @param  list<string>  $slugs
     * @return list<string>
     */
    public function resolvePrimaryListingCategorySlugs(array $slugs): array
    {
        return $this->resolveListingCategorySlugs($slugs, 1);
    }

    /**
     * @param  list<string>  $slugs
     * @return list<int>
     */
    public function resolveCategoryTreeIds(array $slugs, int $listingLimit = 2): array
    {
        $listingSlugs = $this->resolveListingCategorySlugs($slugs, $listingLimit);
        $ids = [];

        foreach ($listingSlugs as $slug) {
            $category = Category::query()->where('slug', $slug)->first();
            if ($category) {
                $ids = array_merge($ids, $this->collectCategoryTreeIds($category));
            }
        }

        return array_values(array_unique($ids));
    }

    /**
     * @param  list<string>  $slugs
     * @return list<int>
     */
    public function resolvePrimaryCategoryTreeIds(array $slugs): array
    {
        return $this->resolveCategoryTreeIds($slugs, 1);
    }

    public function productMatchesAllowedCategories(?Category $productCategory, array $allowedSlugs): bool
    {
        if ($allowedSlugs === []) {
            return true;
        }

        if (!$productCategory) {
            return false;
        }

        $primaryAllowed = $this->resolvePrimaryListingCategorySlugs($allowedSlugs);
        if ($primaryAllowed === []) {
            return false;
        }

        $productListing = $this->resolveListingCategorySlugs([$productCategory->slug], 2);

        return in_array($primaryAllowed[0], $productListing, true);
    }

    /**
     * @return list<int>
     */
    private function collectCategoryTreeIds(Category $category): array
    {
        $ids = [$category->id];

        foreach (Category::query()->where('parent_id', $category->id)->get() as $child) {
            $ids = array_merge($ids, $this->collectCategoryTreeIds($child));
        }

        return $ids;
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
