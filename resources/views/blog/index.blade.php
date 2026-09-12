<x-layouts.app :title="$title" :description="$description" :canonical="$canonical" :robots="$robots">
    @push('head')
        <script type="application/ld+json">
            {!! json_encode($breadcrumbSchema, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) !!}
        </script>
    @endpush

    <x-page-breadcrumbs :items="$breadcrumbs" current="/naujienos" />

    <div class="base-container mx-auto justify-center gap-4 py-2">
        <div class="mb-4 flex flex-col sm:mb-[32px] sm:flex-row sm:items-center sm:justify-between">
            <h1 class="mb-1 text-2xl font-extrabold text-gray-900 sm:mb-0 sm:text-3xl">Naujienos</h1>
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
                    <div class="mt-8 flex justify-center">
                        {{ $posts->onEachSide(1)->links() }}
                    </div>
                @endif
            @endif
        </div>
    </div>
</x-layouts.app>
