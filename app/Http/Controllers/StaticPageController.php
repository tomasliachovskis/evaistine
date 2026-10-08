<?php

namespace App\Http\Controllers;

class StaticPageController extends Controller
{
    public function privacyPolicy()
    {
        return view('static.privacy-policy', [
            'title' => 'Privatumo politika',
            'description' => 'eVaistine.lt privatumo politika. Sužinokite, kaip tvarkome jūsų asmeninę informaciją.',
            'canonical' => url('/privatumo-politika'),
            'breadcrumbs' => [
                ['name' => 'Pradžia', 'href' => '/'],
                ['name' => 'Privatumo politika', 'href' => '/privatumo-politika'],
            ],
        ]);
    }

    public function terms()
    {
        return view('static.terms', [
            'title' => 'Naudojimosi taisyklės',
            'description' => 'eVaistine.lt naudojimosi taisyklės: esame vaistų kainų palyginimo svetainė, ne vaistinė. Kas atsako už prekes, kainas ir pirkimą.',
            'canonical' => url('/naudojimosi-taisykles'),
            'breadcrumbs' => [
                ['name' => 'Pradžia', 'href' => '/'],
                ['name' => 'Naudojimosi taisyklės', 'href' => '/naudojimosi-taisykles'],
            ],
        ]);
    }

    public function about()
    {
        return view('static.about', [
            'title' => 'Apie mus',
            'description' => 'Kas yra eVaistine.lt, iš kur renkame kainas ir akcijas, ir kaip su mumis susisiekti.',
            'canonical' => url('/apie'),
            'breadcrumbs' => [
                ['name' => 'Pradžia', 'href' => '/'],
                ['name' => 'Apie mus', 'href' => '/apie'],
            ],
        ]);
    }
}
