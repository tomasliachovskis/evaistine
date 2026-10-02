<?php

use App\Http\Controllers\AkcijosController;
use App\Http\Controllers\Auth\AuthController;
use App\Http\Controllers\BlogController;
use App\Http\Controllers\CheapestProductsController;
use App\Http\Controllers\CouponController;
use App\Http\Controllers\FavoritesController;
use App\Http\Controllers\HomeBetaController;
use App\Http\Controllers\LeafletController;
use App\Http\Controllers\ListingDealsPartialController;
use App\Http\Controllers\NewHomeController;
use App\Http\Controllers\PriceWatchController;
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

// NewHomeController's "Variant B — kainų palyginimas" replaced the old
// HomeController-rendered homepage — kept below, unrouted, in case of
// rollback.
Route::get('/', [NewHomeController::class, 'index'])->name('home');

Route::get('/privatumo-politika', [StaticPageController::class, 'privacyPolicy']);
Route::get('/apie', [StaticPageController::class, 'about']);
Route::redirect('/kontaktai', '/apie#kontaktai', 301);

Route::get('/naujienos', [BlogController::class, 'index']);
Route::get('/naujienos/{slug}', [BlogController::class, 'show']);

Route::get('/pigiausios-prekes', [CheapestProductsController::class, 'index']);

// "Variant B" is now the live homepage at "/" — redirect the old review URL
// so it doesn't serve as a duplicate-content second copy of "/".
Route::redirect('/nauja-pradzia', '/', 301);
// Simpler homepage for older readers, under review next to "/" (noindex).
Route::get('/pradzia-beta', [HomeBetaController::class, 'index']);

Route::get('/robots.txt', [SitemapController::class, 'robots']);
Route::get('/sitemap.xml', [SitemapController::class, 'sitemap']);
Route::get('/product-sitemap/{page}', [SitemapController::class, 'productSitemap'])->where('page', '[0-9]+');

// IndexNow key verification file (seo:indexnow): must be served at
// /{key}.txt on the public host and contain the key itself.
Route::get('/{key}.txt', function (string $key) {
    $expected = (string) config('services.indexnow.key');
    abort_unless($expected !== '' && hash_equals($expected, $key), 404);

    return response($expected, 200, ['Content-Type' => 'text/plain']);
})->where('key', '[a-zA-Z0-9-]{8,128}');

// Order matters: /akcijos/paieska[...] must resolve before the generic
// {slug1}/{slug2?} catch-all below, or "paieska" would be parsed as a
// store/category/keyword slug instead.
Route::get('/akcijos/_deals', ListingDealsPartialController::class)->name('akcijos.deals.partial');
Route::get('/akcijos', [AkcijosController::class, 'index']);
Route::get('/akcijos/paieska', [AkcijosController::class, 'searchForm']);
Route::get('/akcijos/paieska/{query}', [AkcijosController::class, 'search'])->where('query', '.*');

// "Alkoholiniai ir nealkoholiniai gėrimai" category was split 2026-09-08
// into "Alkoholiniai gėrimai" (kept the old id, products/subcategories
// were already all alcohol types) and a new "Nealkoholiniai gėrimai" root
// — the old combined URL redirects to the non-alcoholic side, per explicit
// choice (not the alcoholic side, even though the old id was kept there).
Route::redirect('/akcijos/alkoholiniai-ir-nealkoholiniai-gerimai', '/akcijos/nealkoholiniai-gerimai', 301);
Route::get('/akcijos/{store}/alkoholiniai-ir-nealkoholiniai-gerimai', function (string $store) {
    return redirect("/akcijos/{$store}/nealkoholiniai-gerimai", 301);
});

Route::get('/akcijos/{slug1}/{slug2?}', [AkcijosController::class, 'show']);

Route::get('/parduotuves', [StoreController::class, 'index']);
Route::get('/parduotuves/{slug}/{city?}', [StoreController::class, 'show']);
// Per-address location pages (e.g. /parduotuves/iki/kelme/birutes-g-7) no
// longer exist — the city page lists every address, so send them there.
Route::get('/parduotuves/{slug}/{city}/{address}', function (string $slug, string $city) {
    return redirect("/parduotuves/{$slug}/{$city}", 301);
})->where('address', '.*');

Route::get('/leidiniai', [LeafletController::class, 'index']);
Route::get('/leidinys/{store}/{flyerSlug}', [LeafletController::class, 'show']);
Route::get('/leidinys/{store}', [LeafletController::class, 'hub']);

Route::get('/kuponai', [CouponController::class, 'index']);
Route::get('/kuponai/{website}', [CouponController::class, 'hub']);

// Named "login" so the `auth` middleware's default guest-redirect has
// somewhere to send people — there's no standalone login page, just the
// modal, so bounce to home with a flag it picks up to open itself.
Route::get('/login', function () {
    return redirect('/?login=1');
})->name('login');

Route::post('/auth/pending-favorite', [AuthController::class, 'rememberPendingFavorite']);
Route::post('/login', [AuthController::class, 'sendMagicLink'])->middleware(['throttle:6,1,magic-link-burst', 'throttle:50,1440,magic-link-daily']);
Route::get('/auth/magic-link/{token}', [AuthController::class, 'verifyMagicLink']);
Route::post('/login/code', [AuthController::class, 'verifyLoginCode'])->middleware('throttle:10,1,login-code');
Route::post('/logout', [AuthController::class, 'logout'])->name('logout');
Route::get('/auth/{provider}/redirect', [AuthController::class, 'redirectToProvider'])->whereIn('provider', ['google', 'facebook']);
Route::get('/auth/{provider}/callback', [AuthController::class, 'handleProviderCallback'])->whereIn('provider', ['google', 'facebook']);

Route::middleware('auth')->get('/favorites', [FavoritesController::class, 'index']);
Route::post('/favorites/toggle/{product}', [FavoritesController::class, 'toggle'])->where('product', '[0-9]+');

// No-login-required, signed link from the price-watch email — GET shows a
// confirm page, POST performs the opt-out (the form posts back to the exact
// same signed URL via url()->full(), so no second signature is needed).
Route::match(['GET', 'POST'], '/price-watch/unsubscribe/{user}', [PriceWatchController::class, 'unsubscribe'])
    ->middleware('signed')
    ->name('price-watch.unsubscribe');
