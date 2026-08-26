<x-layouts.app :title="$title" :description="$description" :canonical="$canonical">
    @push('head')
        <script type="application/ld+json">
            {!! json_encode($breadcrumbSchema, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) !!}
        </script>
    @endpush

    <x-page-breadcrumbs :items="$breadcrumbs" :current="'/naujienos/' . $post->slug" />

    <div class="base-container mb-8 mt-4 sm:mb-[68px] sm:mt-8">
        <div class="flex w-full justify-start">
            <div class="w-full cursor-default rounded-xl border bg-card p-4 sm:max-w-[70%] sm:p-6">
                <div class="flex flex-col gap-4">
                    <div>
                        <h1 class="mb-3">{{ $post->title }}</h1>
                        <p class="mb-4 text-sm text-gray-600">{{ $post->published_at?->toDateString() }}</p>
                    </div>
                    {{-- $post->content is trusted admin-authored HTML, same as the
                         Next.js dangerouslySetInnerHTML it replaces. --}}
                    <div class="blog-content">{!! $post->content !!}</div>
                </div>
            </div>
        </div>
    </div>
</x-layouts.app>
