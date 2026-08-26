<?php

namespace App\Http\Controllers;

class StaticPageController extends Controller
{
    public function privacyPolicy()
    {
        return view('static.privacy-policy', [
            'title' => 'Privatumo politika',
            'description' => 'SuperAkcijos.lt privatumo politika. Sužinokite, kaip tvarkome jūsų asmeninę informaciją.',
            'canonical' => url('/privatumo-politika'),
            'breadcrumbs' => [
                ['name' => 'Akcijos', 'href' => '/'],
                ['name' => 'Privatumo politika', 'href' => '/privatumo-politika'],
            ],
        ]);
    }
}
