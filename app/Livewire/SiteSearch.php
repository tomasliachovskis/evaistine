<?php

namespace App\Livewire;

use App\Http\Controllers\Api\KeywordPageController;
use App\Http\Controllers\Api\ProductController;
use Livewire\Attributes\Locked;
use Livewire\Component;

// Ported from discount/src/components/common/search-input.tsx — real behavior
// is click-to-open-overlay, not an always-visible input: a pill/icon trigger
// opens a full search panel (desktop: fixed dark-backdrop overlay; mobile: a
// bottom sheet), which is where the live-search <input> actually lives. Live
// results from 2+ chars via the same MeilisearchService the JSON API used
// (in-process, no HTTP hop), popular keyword pages shown before typing.
// Enter/"see all" always lands on the noindex'd /akcijos/paieska/{query} page,
// which stays the no-JS fallback.
//
// One component, two render modes (`mode="desktop"|"mobile"`, matching the
// source's `variant`/`mobileProductToolbar` split) so the search/results PHP
// logic isn't duplicated across two Livewire classes — see site-header.blade.php
// for both `<livewire:site-search mode="..." />` placements.
class SiteSearch extends Component
{
    public string $mode = 'desktop';

    public string $query = '';

    public bool $open = false;

    public array $results = [];

    #[Locked]
    public array $popular = [];

    public function mount(string $mode = 'desktop'): void
    {
        $this->mode = $mode;

        $payload = json_decode(app(KeywordPageController::class)->index()->getContent(), true);

        $this->popular = collect($payload['pages'] ?? [])
            ->sortBy('sort_order')
            ->take(8)
            ->map(fn ($page) => [
                'title' => $page['title'] ?? $page['h1'] ?? $page['slug'],
                'href' => '/akcijos/' . $page['slug'],
                'emoji' => $page['emoji'] ?? null,
            ])
            ->values()
            ->all();
    }

    public function updatedQuery(): void
    {
        $trimmed = trim($this->query);

        if (mb_strlen($trimmed) < 2) {
            $this->results = [];

            return;
        }

        $payload = json_decode(app(ProductController::class)->search(request(), $trimmed)->getContent(), true);

        $this->results = collect($payload['data']['data'] ?? [])
            ->take(8)
            ->map(fn ($deal) => [
                'name' => $deal['product']['name'],
                'href' => '/akcijos/' . $deal['product']['full_slug'],
            ])
            ->all();
    }

    public function goToResults()
    {
        $trimmed = trim($this->query);

        if ($trimmed === '') {
            return;
        }

        return redirect('/akcijos/paieska/' . rawurlencode($trimmed));
    }

    public function render()
    {
        return view('livewire.site-search');
    }
}
