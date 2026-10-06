<?php

namespace App\Rules;

use App\Models\Category;
use App\Models\Store;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Support\Facades\Route;

// Listing pages share one flat URL space (/{slug}, see App\Support\PageUrl):
// pharmacies, categories and keyword pages. A keyword page whose slug equals
// a pharmacy, a category or another route's first segment (/apie, /p,
// /vaistines, ...) could never be reached, so it is refused here.
class FreeTopLevelSlug implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (is_string($value) && ($conflict = self::conflict($value)) !== null) {
            $fail("Šis adresas jau užimtas: {$conflict}.");
        }
    }

    // What already owns /{slug}, or null when it's free for a keyword page.
    public static function conflict(string $slug): ?string
    {
        $slug = mb_strtolower(trim($slug, '/'));

        if (in_array($slug, self::reservedSegments(), true)) {
            return 'svetainės puslapis';
        }
        if (Store::where('slug', $slug)->exists()) {
            return 'vaistinė';
        }
        if (Category::where('slug', $slug)->exists()) {
            return 'kategorija';
        }

        return null;
    }

    /** @return array<int, string> first path segment of every non-fallback route, plus public/ entries */
    public static function reservedSegments(): array
    {
        $segments = [];
        foreach (Route::getRoutes() as $route) {
            if ($route->isFallback) {
                continue;
            }
            $first = explode('/', trim($route->uri(), '/'))[0] ?? '';
            if ($first !== '' && ! str_starts_with($first, '{')) {
                $segments[] = mb_strtolower($first);
            }
        }

        // Files and folders in public/ (assets, build, storage, favicon.ico)
        // are served before the router ever sees the request.
        foreach (glob(public_path('*')) ?: [] as $path) {
            $segments[] = mb_strtolower(basename($path));
        }

        return array_values(array_unique($segments));
    }
}
