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
            ->where('show_discounts_page', true)
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
        ->map(fn ($page) => ['href' => '/' . $page['slug'], 'label' => $page['title'] ?? $page['h1'] ?? $page['slug']])
        ->values();
@endphp

{{-- Ported from discount/src/components/common/footer.tsx --}}
<footer class="w-full bg-dark-green text-white">
    <div class="base-container">
        <div class="border-b border-white/10 py-12 lg:py-16">
            <a href="/" class="mb-8 inline-block hover:opacity-90">
                <img src="/assets/logo-white.svg" alt="eVaistine.lt" width="133" height="22" class="h-7 w-auto">
            </a>
            <div class="grid grid-cols-1 gap-10 sm:grid-cols-2 lg:grid-cols-5 lg:gap-12">
                <div class="flex flex-col gap-6">
                    <div>
                        <h2 class="mb-4 text-lg font-bold">Apie eVaistine.lt</h2>
                        <ul class="flex flex-col gap-2">
                            <li><a href="/apie" class="text-base text-white/80 transition-colors hover:text-white hover:underline">Apie mus</a></li>
                            <li><a href="/naujienos" class="text-base text-white/80 transition-colors hover:text-white hover:underline">Naujienos</a></li>
                            <li><a href="/naudojimosi-taisykles" class="text-base text-white/80 transition-colors hover:text-white hover:underline">Naudojimosi taisyklės</a></li>
                            <li><a href="/privatumo-politika" class="text-base text-white/80 transition-colors hover:text-white hover:underline">Privatumo politika</a></li>
                            <li><a href="/vaistines" class="text-base text-white/80 transition-colors hover:text-white hover:underline">Vaistinės</a></li>
                        </ul>
                    </div>
                    {{-- "Sekite mus" (Facebook/Instagram) removed 2026-10-08: the links
                         were superakcijos' accounts. Add the block back (see git history)
                         once eVaistine has its own pages. --}}
                </div>

                <div>
                    <h2 class="mb-4 text-lg font-bold">Kategorijos</h2>
                    <ul class="flex flex-col gap-2">
                        @foreach ($footerCategories as $category)
                            <li><a href="/{{ $category->slug }}" class="text-base text-white/80 transition-colors hover:text-white hover:underline">{{ $category->name }}</a></li>
                        @endforeach
                    </ul>
                </div>

                <div>
                    <h2 class="mb-4 text-lg font-bold">Vaistinės</h2>
                    <ul class="flex flex-col gap-2">
                        @foreach ($footerStores as $store)
                            <li><a href="/{{ $store['slug'] }}" class="text-base text-white/80 transition-colors hover:text-white hover:underline">{{ $store['name'] }}</a></li>
                        @endforeach
                        <li><a href="/vaistines" class="text-base text-white/80 transition-colors hover:text-white hover:underline">Visos vaistinės</a></li>
                    </ul>
                </div>

                <div>
                    <h2 class="mb-4 text-lg font-bold">Produktai</h2>
                    <ul class="flex flex-col gap-2">
                        @foreach ($footerKeywordItems as $item)
                            <li><a href="{{ $item['href'] }}" class="text-base text-white/80 transition-colors hover:text-white hover:underline">{{ $item['label'] }}</a></li>
                        @endforeach
                    </ul>
                </div>

                <div>
                    <h2 class="mb-4 text-lg font-bold">Naudinga</h2>
                    <ul class="flex flex-col gap-2">
                        <li><a href="/favorites" class="text-base text-white/80 transition-colors hover:text-white hover:underline">Stebimos prekės</a></li>
                        <li><a href="/akcijos" class="text-base text-white/80 transition-colors hover:text-white hover:underline">Akcijos</a></li>
                        <li><a href="/naujienos" class="text-base text-white/80 transition-colors hover:text-white hover:underline">Naujienos</a></li>
                    </ul>
                </div>
            </div>
        </div>

        <div class="flex flex-col gap-4 py-6">
            <x-pharmacy-disclaimer class="max-w-4xl text-white/80 [&_a]:text-white" />
            <p class="text-base text-white/60">&copy; {{ date('Y') }} eVaistine.lt &ndash; vaistų kainų palyginimas</p>
        </div>
    </div>
</footer>
