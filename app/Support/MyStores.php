<?php

namespace App\Support;

use Illuminate\Http\Request;

/**
 * "Mano parduotuvės" on the server. The browser keeps the choice in
 * localStorage and mirrors it into a plain cookie (see the myStores Alpine
 * store in components/layouts/app.blade.php), so multi-store listings can
 * render already filtered instead of loading every store and then
 * re-filtering with a second request.
 */
class MyStores
{
    public const COOKIE = 'evaistine_parduotuves';

    // "Rodyti visas": a session cookie, all stores for the rest of the visit.
    public const SHOW_ALL_COOKIE = 'evaistine_visos_parduotuves';

    private const MAX_STORES = 12;

    /**
     * Real-looking store slugs only, unique, at most 12 (same limit as
     * MyStoresController).
     *
     * @return list<string>
     */
    public static function clean(string $slugs): array
    {
        return array_slice(array_values(array_unique(array_filter(
            array_map('trim', explode(',', $slugs)),
            fn ($slug) => preg_match('/^[a-z0-9-]{1,100}$/', $slug) === 1,
        ))), 0, self::MAX_STORES);
    }

    /**
     * The signed-in user's saved stores (they win, like in the browser),
     * else the cookie.
     *
     * @return list<string>
     */
    public static function slugs(Request $request): array
    {
        $account = $request->user()?->preferred_store_slugs;
        if (is_array($account)) {
            return self::clean(implode(',', array_filter($account, 'is_string')));
        }

        return self::clean((string) $request->cookie(self::COOKIE, ''));
    }

    /**
     * Applies the saved stores as ?store= for this request when the URL
     * doesn't pick stores itself and "Rodyti visas" isn't on. Everything
     * downstream (the API filters, Livewire's #[Url] storeFilter, the search
     * page) already reads request()->get('store').
     */
    public static function applyToRequest(Request $request): void
    {
        if ($request->query->has('store') || $request->cookie(self::SHOW_ALL_COOKIE) === '1') {
            return;
        }

        $slugs = self::slugs($request);
        if ($slugs === []) {
            return;
        }

        sort($slugs);
        $request->query->set('store', implode(',', $slugs));
        $request->attributes->set('my_stores_applied', true);
    }

    /**
     * The query string the URL really had: without the store filter that
     * applyToRequest() added. For canonical/robots tags.
     */
    public static function urlQuery(Request $request): array
    {
        $query = $request->query();
        if ($request->attributes->get('my_stores_applied')) {
            unset($query['store']);
        }

        return $query;
    }
}
