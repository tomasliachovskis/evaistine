<?php

namespace App\Http\Controllers;

class StaticPageController extends Controller
{
    public function privacyPolicy()
    {
        return view('static.privacy-policy', [
            'title' => 'Privatumo politika',
            'description' => 'eVaistinė.lt privatumo politika. Sužinokite, kaip tvarkome jūsų asmeninę informaciją.',
            'canonical' => url('/privatumo-politika'),
            'breadcrumbs' => [
                ['name' => 'Akcijos', 'href' => '/'],
                ['name' => 'Privatumo politika', 'href' => '/privatumo-politika'],
            ],
        ]);
    }

    public function about()
    {
        return view('static.about', [
            'title' => 'Apie mus',
            'description' => 'Kas yra eVaistinė.lt, iš kur renkame kainas ir akcijas, ir kaip su mumis susisiekti.',
            'canonical' => url('/apie'),
            'breadcrumbs' => [
                ['name' => 'Akcijos', 'href' => '/'],
                ['name' => 'Apie mus', 'href' => '/apie'],
            ],
        ]);
    }
}
