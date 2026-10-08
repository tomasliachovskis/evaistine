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
            ['name' => 'Pradžia', 'href' => '/'],
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
            $buildGroup('Vaistų ir papildų kainų palyginimas', $candidates['medicines']),
            $buildGroup('Kosmetikos ir higienos prekių kainų palyginimas', $candidates['care']),
        ])->filter(fn (array $group) => !empty($group['items']))->values()->all();

        return view('pigiausios-prekes.show', [
            'title' => 'Pigiausios prekės vaistinėse',
            'description' => 'Kasdien sekame populiarių vaistų ir kitų vaistinės prekių kainas visose vaistinėse ir parodome, kurioje šiuo metu pigiausia.',
            'canonical' => CanonicalUrl::build($path),
            'robots' => null,
            'breadcrumbs' => $breadcrumbs,
            'breadcrumbSchema' => BreadcrumbSchema::build($breadcrumbs),
            'groups' => $groups,
            'freshnessLabel' => ($freshnessDate = ContentFreshness::forAll()) ? LithuanianDate::relative($freshnessDate) : null,
        ]);
    }
}
