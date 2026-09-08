<?php

namespace App\Http\Controllers;

use App\Services\PriceIndexService;
use App\Support\BreadcrumbSchema;
use App\Support\CanonicalUrl;

class PriceIndexController extends Controller
{
    public function index(PriceIndexService $service)
    {
        $path = '/kainu-indeksas';

        $breadcrumbs = [
            ['name' => 'Akcijos', 'href' => '/'],
            ['name' => 'Kainų indeksas', 'href' => $path],
        ];

        return view('price-index.show', [
            'title' => 'Savaitės krepšelio kainų indeksas – SuperAkcijos.lt',
            'description' => 'Kiekvieną savaitę sekame kasdienių prekių kainas didžiausiuose prekybos tinkluose ir parodome, kur šiuo metu pigiausia apsipirkti.',
            'canonical' => CanonicalUrl::build($path),
            // Deliberately not in the sitemap yet and not linked from
            // anywhere on the site — explicit noindex too, so it can't get
            // picked up by Google before it's actually ready to launch.
            'robots' => 'noindex, nofollow, noarchive, nosnippet',
            'breadcrumbs' => $breadcrumbs,
            'breadcrumbSchema' => BreadcrumbSchema::build($breadcrumbs),
            'data' => $service->getPageData(),
        ]);
    }
}
