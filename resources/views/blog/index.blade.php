<x-layouts.app
    :breadcrumbs="$breadcrumbs ?? []" :title="$title" :description="$description" :canonical="$canonical" :robots="$robots">
    @push('head')
        <script type="application/ld+json">
            {!! json_encode($breadcrumbSchema, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) !!}
        </script>
    @endpush

    <x-page-breadcrumbs :items="$breadcrumbs" current="/naujienos" />

    <div class="base-container mx-auto justify-center gap-4 pb-2">
        <div class="mb-4 flex flex-col sm:mb-[32px] sm:flex-row sm:items-center sm:justify-between">
            <h1 class="mb-1 sm:mb-0">Naujienos</h1>
        </div>

        <div class="mb-8 w-full">
            @if ($posts->isEmpty())
                <div class="w-full py-12 text-center">
                    <p class="text-gray-600">Nerasta straipsnių</p>
                </div>
            @else
                <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
                    @foreach ($posts as $post)
                        <x-blog-post-card :post="$post" />
                    @endforeach
                </div>

                @if ($posts->lastPage() > 1)
                    {{-- Plain worded previous/next instead of the framework's
                         default paginator ("Next »", small numbered links). --}}
                    <nav class="mt-8 flex items-center justify-between gap-3" aria-label="Puslapiai">
                        @if ($posts->onFirstPage())
                            <span></span>
                        @else
                            <a href="{{ $posts->previousPageUrl() }}" class="inline-flex min-h-12 items-center gap-1.5 rounded-xl border border-gray-200 bg-white px-4 text-lg font-semibold text-gray-900 hover:border-gray-300">
                                <x-app-icon name="chevron-right" class="size-5 rotate-180" />
                                Ankstesnis puslapis
                            </a>
                        @endif
                        <span class="text-lg font-semibold text-gray-800">{{ $posts->currentPage() }} iš {{ $posts->lastPage() }}</span>
                        @if ($posts->hasMorePages())
                            <a href="{{ $posts->nextPageUrl() }}" class="inline-flex min-h-12 items-center gap-1.5 rounded-xl bg-action px-4 text-lg font-bold text-white hover:bg-action-hover">
                                Kitas puslapis
                                <x-app-icon name="chevron-right" class="size-5" />
                            </a>
                        @else
                            <span></span>
                        @endif
                    </nav>
                @endif
            @endif
        </div>
    </div>
</x-layouts.app>
