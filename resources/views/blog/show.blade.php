<x-layouts.app
    :breadcrumbs="$breadcrumbs ?? []" :title="$title" :description="$description" :canonical="$canonical" :og-image="$ogImage" :og-type="$ogType">
    @push('head')
        <script type="application/ld+json">
            {!! json_encode($breadcrumbSchema, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) !!}
        </script>
        <script type="application/ld+json">
            {!! json_encode($articleSchema, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) !!}
        </script>
    @endpush

    <x-page-breadcrumbs :items="$breadcrumbs" :current="'/naujienos/' . $post->slug" />

    <div class="base-container mb-8 sm:mb-[68px]">
        <div class="flex w-full justify-start">
            <div class="w-full cursor-default rounded-xl border bg-card p-4 sm:max-w-[70%] sm:p-6">
                <div class="flex flex-col gap-4">
                    <div>
                        <h1 class="mb-3">{{ $post->title }}</h1>
                        <p class="mb-4 text-sm text-gray-600">{{ $post->published_at?->day }} {{ \App\Support\LithuanianDate::shortMonth($post->published_at) }} {{ $post->published_at?->year }}</p>
                    </div>
                    {{-- $post->content is trusted admin-authored HTML. --}}
                    <div class="blog-content">{!! $post->content !!}</div>

                    @if ($post->source_url)
                        <p class="border-t pt-4 text-sm text-gray-500">
                            Šaltinis:
                            <a href="{{ $post->source_url }}" target="_blank" rel="noopener noreferrer nofollow" class="text-primary hover:underline">
                                {{ $post->source_name ?: $post->source_url }}
                            </a>
                        </p>
                    @endif
                </div>
            </div>
        </div>
    </div>
</x-layouts.app>
