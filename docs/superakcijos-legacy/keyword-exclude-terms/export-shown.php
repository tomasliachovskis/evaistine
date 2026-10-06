<?php
$svc = app(App\Services\KeywordPageService::class);
$m = new ReflectionMethod($svc, 'collectMatchingDiscountsCollection'); $m->setAccessible(true);
$out = [];
foreach (App\Models\KeywordPage::published()->orderBy('slug')->get() as $p) {
    try { $items = $m->invoke($svc, $p); } catch (Throwable $e) { $items = collect(); }
    $names = $items->map(fn ($d) => trim(($d->product?->name ?? '') . ($d->product?->brand ? ' [' . $d->product->brand . ']' : '')))->unique()->values()->all();
    $out[] = ['slug' => $p->slug, 'title' => $p->title, 'h1' => $p->h1, 'search_terms' => $p->search_terms, 'exclude_terms' => $p->exclude_terms, 'category_slugs' => $p->category_slugs, 'shown' => count($names), 'names' => $names];
}
file_put_contents('/tmp/kw_dump.json', json_encode($out, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
echo count($out) . " pages dumped\n";
