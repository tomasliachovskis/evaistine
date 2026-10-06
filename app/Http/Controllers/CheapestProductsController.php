<?php

namespace App\Http\Controllers;

use App\Services\KeywordPageService;
use App\Support\BreadcrumbSchema;
use App\Support\CanonicalUrl;
use App\Support\ContentFreshness;
use App\Support\LithuanianDate;

class CheapestProductsController extends Controller
{
    // Fuller list than the homepage teaser (which only ever shows 2 random
    // picks per block) — every candidate keyword page gets its own teaser
    // here, same buildHomeTeaser()/topCandidatesByCategoryGroup() the
    // homepage uses, just not randomized down to 2.
    private const ITEMS_PER_GROUP = 20;

    public function index(KeywordPageService $keywordPageService)
    {
        $path = '/pigiausios-prekes';

        $breadcrumbs = [
            ['name' => 'Akcijos', 'href' => '/'],
            ['name' => 'Pigiausios prekės', 'href' => $path],
        ];

        $candidates = $keywordPageService->topCandidatesByCategoryGroup(self::ITEMS_PER_GROUP);
        $buildGroup = fn (string $name, array $pages) => [
            'name' => $name,
            'items' => collect($pages)
                ->map(fn ($page) => $keywordPageService->buildHomeTeaser($page, 5))
                ->filter()
                ->values()
                ->all(),
        ];
        $groups = collect([
            $buildGroup('Maisto prekių kainų palyginimas', $candidates['food']),
            $buildGroup('Ne maisto prekių kainų palyginimas', $candidates['non_food']),
        ])->filter(fn (array $group) => !empty($group['items']))->values()->all();

        return view('pigiausios-prekes.show', [
            'title' => 'Pigiausios prekės vaistinėse',
            'description' => 'Kiekvieną savaitę sekame kasdienių prekių kainas didžiausiuose prekybos tinkluose ir parodome, kur šiuo metu pigiausia apsipirkti.',
            'canonical' => CanonicalUrl::build($path),
            'robots' => null,
            'breadcrumbs' => $breadcrumbs,
            'breadcrumbSchema' => BreadcrumbSchema::build($breadcrumbs),
            'groups' => $groups,
            'freshnessLabel' => ($freshnessDate = ContentFreshness::forAll()) ? LithuanianDate::relative($freshnessDate) : null,
        ]);
    }
}
