@props(['post'])

{{-- Ported from discount/src/components/blog/blog-post-preview-item.tsx
     (showImage=true / headingLevel=h2 / excerptClamp=3 variant, as used by
     BlogPostList on /naujienos). --}}
@php
    $imageUrl = $post->imageUrl();
    $excerpt = $post->excerpt();
@endphp
<a
    href="/naujienos/{{ $post->slug }}"
    class="group flex h-full min-w-0 flex-col overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-sm transition-all hover:border-green/40 hover:shadow-md"
>
    <div class="relative aspect-[2/1] min-h-[7.25rem] overflow-hidden bg-gradient-to-br from-green/25 via-emerald-50/90 to-white sm:min-h-[8rem]">
        @if ($imageUrl)
            <img src="{{ $imageUrl }}" alt="" class="absolute inset-0 size-full object-cover transition-transform duration-300 group-hover:scale-[1.03]">
        @else
            <div
                class="absolute inset-0 opacity-[0.14]"
                style="background-image: radial-gradient(circle at 20% 30%, var(--green) 0%, transparent 45%), radial-gradient(circle at 80% 70%, var(--dark-green) 0%, transparent 40%);"
                aria-hidden="true"
            ></div>
        @endif
        <div class="absolute inset-0 bg-gradient-to-t from-gray-900/55 via-gray-900/10 to-transparent" aria-hidden="true"></div>
        @unless ($imageUrl)
            <x-app-icon name="newspaper" class="absolute left-1/2 top-1/2 size-10 -translate-x-1/2 -translate-y-1/2 text-green/35 sm:size-12" />
        @endunless
    </div>
    <div class="flex flex-1 flex-col p-4 sm:p-5">
        <h2 class="line-clamp-2 text-base font-bold leading-snug text-gray-900 group-hover:text-dark-green">
            {{ $post->title }}
        </h2>
        <time datetime="{{ $post->published_at?->toDateString() }}" class="mt-1.5 inline-flex w-fit items-center gap-1.5 text-xs font-medium text-gray-500">
            <span class="size-1.5 shrink-0 rounded-full bg-green/70" aria-hidden="true"></span>
            {{ $post->published_at?->day }} {{ \App\Support\LithuanianDate::shortMonth($post->published_at) }} {{ $post->published_at?->year }}
        </time>
        @if ($excerpt)
            <p class="mt-2 line-clamp-3 flex-1 text-sm leading-relaxed text-gray-600">{{ $excerpt }}</p>
        @else
            <div class="flex-1"></div>
        @endif
        <span class="mt-3 inline-flex items-center gap-1 text-sm font-semibold text-dark-green">
            Skaityti
            <x-app-icon name="arrow-right" class="size-4 transition-transform group-hover:translate-x-0.5" />
        </span>
    </div>
</a>
