@php
    use App\Http\Controllers\Api\KeywordPageController;
    use App\Models\Category;
    use App\Models\Store;
    use App\Support\CacheVersion;
    use Illuminate\Support\Facades\App;
    use Illuminate\Support\Facades\Cache;

    // The footer renders on every page (base layout), so — same reasoning as
    // MobileNavComposer — its store/category rankings must come from cache,
    // not a fresh query per request.
    $footerCacheSuffix = CacheVersion::suffix(['discounts']);

    // Same editorial priority order as the home hero chips / store directory
    // / favorites store cards, instead of a raw discounts_count sort.
    $footerStores = Cache::remember("footer_stores_{$footerCacheSuffix}", 1800, fn () => \App\Support\StoreListPriority::sort(
        Store::query()
            ->where('extract_discounts_from_flyer', true)
            ->withCount('discounts')
            ->having('discounts_count', '>', 0)
            ->get()
            ->map(fn ($store) => ['slug' => $store->slug, 'name' => $store->name, 'discounts_count' => $store->discounts_count])
            ->all()
    ));

    $footerCategories = Cache::remember("footer_categories_{$footerCacheSuffix}", 1800, fn () => Category::query()
        ->whereNull('parent_id')
        ->where('hide', false)
        ->withCount('discounts')
        ->having('discounts_count', '>', 0)
        ->orderByDesc('discounts_count')
        ->get());

    $keywordPayload = json_decode(App::make(KeywordPageController::class)->index()->getContent(), true);
    $footerKeywordItems = collect($keywordPayload['pages'] ?? [])
        ->sortBy('sort_order')
        ->take(15)
        ->map(fn ($page) => ['href' => '/akcijos/' . $page['slug'], 'label' => $page['title'] ?? $page['h1'] ?? $page['slug']])
        ->values();
@endphp

{{-- Ported from discount/src/components/common/footer.tsx --}}
<footer class="w-full bg-dark-green text-white">
    <div class="base-container">
        <div class="border-b border-white/10 py-12 lg:py-16">
            <a href="/" class="mb-8 inline-block hover:opacity-90">
                <img src="/assets/logo-white.svg" alt="Superakcijos.lt" width="178" height="22" class="h-7 w-auto">
            </a>
            <div class="grid grid-cols-1 gap-10 sm:grid-cols-2 lg:grid-cols-5 lg:gap-12">
                <div class="flex flex-col gap-6">
                    <div>
                        <h2 class="mb-4 text-lg font-bold">Apie SuperAkcijos.lt</h2>
                        <ul class="flex flex-col gap-2">
                            <li><a href="/apie" class="text-base text-white/80 transition-colors hover:text-green">Apie mus</a></li>
                            <li><a href="/naujienos" class="text-base text-white/80 transition-colors hover:text-green">Naujienos</a></li>
                            <li><a href="/privatumo-politika" class="text-base text-white/80 transition-colors hover:text-green">Privatumo politika</a></li>
                            <li><a href="/parduotuves" class="text-base text-white/80 transition-colors hover:text-green">Parduotuvės</a></li>
                        </ul>
                    </div>
                    <div class="border-t border-white/10 pt-6">
                        <h3 class="mb-4 text-lg font-bold">Sekite mus</h3>
                        <ul class="flex flex-col gap-2">
                            <li>
                                <a href="https://www.facebook.com/profile.php?id=61586857013836" target="_blank" rel="noopener noreferrer" class="inline-flex items-center gap-2 text-base text-white/80 transition-colors hover:text-green">
                                    <x-app-icon name="facebook" class="h-6 w-6 shrink-0" />
                                    Facebook
                                </a>
                            </li>
                            <li>
                                <a href="https://www.instagram.com/superakcijos.lt/" target="_blank" rel="noopener noreferrer" class="inline-flex items-center gap-2 text-base text-white/80 transition-colors hover:text-green">
                                    <x-app-icon name="instagram" class="h-6 w-6 shrink-0" />
                                    Instagram
                                </a>
                            </li>
                        </ul>
                    </div>
                </div>

                <div>
                    <h2 class="mb-4 text-lg font-bold">Kategorijos</h2>
                    <ul class="flex flex-col gap-2">
                        @foreach ($footerCategories as $category)
                            <li><a href="/akcijos/{{ $category->slug }}" class="text-base text-white/80 transition-colors hover:text-green">{{ $category->name }}</a></li>
                        @endforeach
                    </ul>
                </div>

                <div>
                    <h2 class="mb-4 text-lg font-bold">Parduotuvės</h2>
                    <ul class="flex flex-col gap-2">
                        @foreach ($footerStores as $store)
                            <li><a href="/akcijos/{{ $store['slug'] }}" class="text-base text-white/80 transition-colors hover:text-green">{{ $store['name'] }}</a></li>
                        @endforeach
                        <li><a href="/parduotuves" class="text-base text-white/80 transition-colors hover:text-green">Visos parduotuvės</a></li>
                    </ul>
                </div>

                <div>
                    <h2 class="mb-4 text-lg font-bold">Produktai</h2>
                    <ul class="flex flex-col gap-2">
                        @foreach ($footerKeywordItems as $item)
                            <li><a href="{{ $item['href'] }}" class="text-base text-white/80 transition-colors hover:text-green">{{ $item['label'] }}</a></li>
                        @endforeach
                    </ul>
                </div>

                <div>
                    <h2 class="mb-4 text-lg font-bold">Naudinga</h2>
                    <ul class="flex flex-col gap-2">
                        <li><a href="/favorites" class="text-base text-white/80 transition-colors hover:text-green">Mėgstami</a></li>
                        <li><a href="/akcijos" class="text-base text-white/80 transition-colors hover:text-green">Akcijos</a></li>
                        <li><a href="/naujienos" class="text-base text-white/80 transition-colors hover:text-green">Naujienos</a></li>
                    </ul>
                </div>
            </div>
        </div>

        <div class="py-6 text-center text-base text-white/60">
            <p>&copy; {{ date('Y') }} SuperAkcijos.lt &ndash; Rask visas akcijas ir nuolaidas</p>
        </div>
    </div>
</footer>
