<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\KeywordPage;
use App\Services\KeywordPageService;
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
        return Cache::tags(['keywords'])->remember('keyword_pages_list_v4', 3600, function () {
            return response()->json([
                'slugs' => $this->keywordPageService->listPublishedSlugs(),
                'pages' => $this->keywordPageService->listPublishedPages(),
            ]);
        });
    }

    public function show(Request $request, string $slug)
    {
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

        $cacheKey = 'keyword_page_v4_' . md5($slug . serialize(array_filter($filters, fn ($v) => $v !== null && $v !== '')));

        return Cache::tags(['keywords', 'discounts', $slug])
            ->remember($cacheKey, 1800, function () use ($page, $filters) {
                return response()->json(
                    $this->keywordPageService->buildListingResponse($page, $filters)
                );
            });
    }
}
