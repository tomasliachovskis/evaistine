<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\KeywordPage;
use App\Services\KeywordPageService;
use App\Support\CacheVersion;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class KeywordPageController extends Controller
{
    public function __construct(
        private KeywordPageService $keywordPageService,
    ) {
    }

    public function index()
    {
        $cacheKey = 'keyword_pages_list_v8_' . CacheVersion::suffix(['keywords']);

        return Cache::remember($cacheKey, 3600, function () {
            return response()->json([
                'slugs' => $this->keywordPageService->listPublishedSlugs(),
                'all_slugs' => $this->keywordPageService->listAllSlugs(),
                'pages' => $this->keywordPageService->listPublishedPages(),
            ]);
        });
    }

    public function show(Request $request, string $slug)
    {
        // Unpublished drafts stay a real 404 — only published pages get the
        // always-200 empty state (see KeywordPageService::buildListingResponse()).
        $page = KeywordPage::query()
            ->published()
            ->where('slug', $slug)
            ->firstOrFail();

        $filters = [
            'order' => $request->get('order', 'popular'),
            'card' => $request->get('card'),
            'plus' => $request->get('plus'),
            'page' => $request->get('page'),
            'store' => $request->get('store'),
            'category' => $request->get('category'),
        ];

        $cacheKey = 'keyword_page_v6_' . md5($slug . serialize(array_filter($filters, fn ($v) => $v !== null && $v !== '')))
            . '_' . CacheVersion::suffix(['keywords', 'discounts']);

        return Cache::remember($cacheKey, 1800, function () use ($page, $filters) {
            return response()->json(
                $this->keywordPageService->buildListingResponse($page, $filters)
            );
        });
    }
}
