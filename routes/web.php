<?php

use App\Http\Controllers\AkcijosController;
use App\Http\Controllers\Auth\AuthController;
use App\Http\Controllers\BlogController;
use App\Http\Controllers\FavoritesController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\LeafletController;
use App\Http\Controllers\ListingDealsPartialController;
use App\Http\Controllers\SitemapController;
use App\Http\Controllers\StaticPageController;
use App\Http\Controllers\StoreController;
use Illuminate\Support\Facades\Route;

// Was a bare ping response before this app served the public site directly
// (the public domain pointed at the separate Next.js app; this route was
// only ever hit on the API-only :8080 vhost). Kept as a lightweight health
// check now that "/" renders the real home page.
Route::get('/_health', function () {
    return response()->json('ping');
});

Route::get('/', [HomeController::class, 'index'])->name('home');

Route::get('/privatumo-politika', [StaticPageController::class, 'privacyPolicy']);
Route::get('/apie', [StaticPageController::class, 'about']);
Route::redirect('/kontaktai', '/apie#kontaktai', 301);

Route::get('/naujienos', [BlogController::class, 'index']);
Route::get('/naujienos/{slug}', [BlogController::class, 'show']);

Route::get('/robots.txt', [SitemapController::class, 'robots']);
Route::get('/sitemap.xml', [SitemapController::class, 'sitemap']);
Route::get('/product-sitemap/{page}', [SitemapController::class, 'productSitemap'])->where('page', '[0-9]+');

// Order matters: /akcijos/paieska[...] must resolve before the generic
// {slug1}/{slug2?} catch-all below, or "paieska" would be parsed as a
// store/category/keyword slug instead.
Route::get('/akcijos/_deals', ListingDealsPartialController::class)->name('akcijos.deals.partial');
Route::get('/akcijos', [AkcijosController::class, 'index']);
Route::get('/akcijos/paieska', [AkcijosController::class, 'searchForm']);
Route::get('/akcijos/paieska/{query}', [AkcijosController::class, 'search'])->where('query', '.*');
Route::get('/akcijos/{slug1}/{slug2?}', [AkcijosController::class, 'show']);

Route::get('/parduotuves', [StoreController::class, 'index']);
Route::get('/parduotuves/{slug}/{city?}', [StoreController::class, 'show']);

Route::get('/leidiniai', [LeafletController::class, 'index']);
Route::get('/leidinys/{store}/{flyerSlug}', [LeafletController::class, 'show']);
Route::get('/leidinys/{store}', [LeafletController::class, 'hub']);

// Named "login" so the `auth` middleware's default guest-redirect has
// somewhere to send people — there's no standalone login page, just the
// modal, so bounce to home with a flag it picks up to open itself.
Route::get('/login', function () {
    return redirect('/?login=1');
})->name('login');

Route::post('/auth/pending-favorite', [AuthController::class, 'rememberPendingFavorite']);
Route::post('/login', [AuthController::class, 'sendMagicLink'])->middleware(['throttle:6,1,magic-link-burst', 'throttle:50,1440,magic-link-daily']);
Route::get('/auth/magic-link/{token}', [AuthController::class, 'verifyMagicLink']);
Route::post('/logout', [AuthController::class, 'logout'])->name('logout');
Route::get('/auth/{provider}/redirect', [AuthController::class, 'redirectToProvider'])->whereIn('provider', ['google', 'facebook']);
Route::get('/auth/{provider}/callback', [AuthController::class, 'handleProviderCallback'])->whereIn('provider', ['google', 'facebook']);

Route::middleware('auth')->get('/favorites', [FavoritesController::class, 'index']);
Route::post('/favorites/toggle/{product}', [FavoritesController::class, 'toggle'])->where('product', '[0-9]+');
