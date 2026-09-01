<?php

namespace App\Support;

use App\Http\Controllers\Api\KeywordPageController;
use App\Http\Controllers\Api\ProductController;
use Illuminate\Http\Request;

class ListingDealsFetcher
{
    public static function fetch(
        string $mode,
        ?string $primarySlug,
        ?string $secondarySlug,
        int $page,
        string $order = 'popular',
        string $storeFilter = '',
        string $categoryFilter = '',
        bool $cardOnly = false,
        bool $plusOnly = false,
        ?Request $request = null,
    ): array {
        $filters = array_filter([
            'order' => $order,
            'card' => $cardOnly ? '1' : null,
            'plus' => $plusOnly ? '1' : null,
            'page' => $page > 1 ? (string) $page : null,
            'store' => $storeFilter !== '' ? $storeFilter : null,
            'category' => $categoryFilter !== '' ? $categoryFilter : null,
        ], fn ($value) => $value !== null);

        ($request ?? request())->query->replace($filters);

        if ($mode === 'keyword') {
            $payload = json_decode(app(KeywordPageController::class)->show(request(), $primarySlug)->getContent(), true);
        } elseif ($mode === 'search') {
            $payload = json_decode(app(ProductController::class)->search(request(), $primarySlug)->getContent(), true);
        } elseif ($primarySlug === null) {
            $payload = json_decode(app(ProductController::class)->getAllDiscounts()->getContent(), true);
        } else {
            $response = $secondarySlug
                ? app(ProductController::class)->getDiscounts($primarySlug, $secondarySlug)
                : app(ProductController::class)->getDiscounts($primarySlug);
            $payload = json_decode($response->getContent(), true);
        }

        return $payload['data'] ?? [];
    }
}
