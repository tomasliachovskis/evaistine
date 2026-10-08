<?php

namespace App\Http\Controllers;

use App\Models\BlogPost;
use App\Support\BreadcrumbSchema;
use App\Support\CanonicalUrl;
use App\Support\NewsArticleSchema;
use Illuminate\Http\Request;

class BlogController extends Controller
{
    public function index(Request $request)
    {
        $posts = BlogPost::published()->orderByDesc('published_at')->paginate(15);

        // Real crawlable <a href="?page=N"> links (see blog/index.blade.php's
        // paginator), unlike /akcijos's Livewire-only pagination — so page 2+
        // must actually be noindexed, and self-canonical rather than pointing
        // back at page 1, or Googlebot gets contradictory signals.
        $query = $request->query();

        $breadcrumbs = [
            ['name' => 'Pradžia', 'href' => '/'],
            ['name' => 'Naujienos', 'href' => '/naujienos'],
        ];

        return view('blog.index', [
            'title' => 'Akcijų ir nuolaidų naujienos – patarimai, kaip sutaupyti',
            'description' => 'Naujausi straipsniai apie akcijas, nuolaidas ir taupymą. Naudingi patarimai ir gairės geriausiems pasiūlymams.',
            'canonical' => CanonicalUrl::build('/naujienos', $query),
            'robots' => CanonicalUrl::robotsMeta('/naujienos', $query),
            'posts' => $posts,
            'breadcrumbs' => $breadcrumbs,
            'breadcrumbSchema' => BreadcrumbSchema::build($breadcrumbs),
        ]);
    }

    public function show(string $slug)
    {
        $post = BlogPost::published()->where('slug', $slug)->firstOrFail();

        $breadcrumbs = [
            ['name' => 'Pradžia', 'href' => '/'],
            ['name' => 'Naujienos', 'href' => '/naujienos'],
            ['name' => $post->title, 'href' => "/naujienos/{$post->slug}"],
        ];

        $canonical = CanonicalUrl::build("/naujienos/{$slug}");
        $description = $post->meta_description ?: ($post->excerpt(155) ?? '');

        return view('blog.show', [
            'title' => $post->meta_title ?: $post->title,
            'description' => $description,
            'canonical' => $canonical,
            'articleSchema' => NewsArticleSchema::build($post, $canonical, $description),
            'ogImage' => $post->imageUrl(),
            'ogType' => 'article',
            'post' => $post,
            'breadcrumbs' => $breadcrumbs,
            'breadcrumbSchema' => BreadcrumbSchema::build($breadcrumbs),
        ]);
    }
}
