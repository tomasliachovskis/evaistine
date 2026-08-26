<?php

namespace App\Http\Controllers;

use App\Models\BlogPost;
use App\Support\BreadcrumbSchema;

class BlogController extends Controller
{
    public function index()
    {
        $posts = BlogPost::published()->orderByDesc('published_at')->paginate(15);

        $breadcrumbs = [
            ['name' => 'Akcijos', 'href' => '/'],
            ['name' => 'Naujienos', 'href' => '/naujienos'],
        ];

        return view('blog.index', [
            'title' => 'Naujienos',
            'description' => 'Naujausi straipsniai apie akcijas, nuolaidas ir taupymą. Naudingi patarimai ir gairės geriausiems pasiūlymams.',
            'canonical' => url('/naujienos'),
            'posts' => $posts,
            'breadcrumbs' => $breadcrumbs,
            'breadcrumbSchema' => BreadcrumbSchema::build($breadcrumbs),
        ]);
    }

    public function show(string $slug)
    {
        $post = BlogPost::published()->where('slug', $slug)->firstOrFail();

        $breadcrumbs = [
            ['name' => 'Akcijos', 'href' => '/'],
            ['name' => 'Naujienos', 'href' => '/naujienos'],
            ['name' => $post->title, 'href' => "/naujienos/{$post->slug}"],
        ];

        return view('blog.show', [
            'title' => $post->meta_title ?: $post->title,
            'description' => $post->meta_description ?: '',
            'canonical' => url("/naujienos/{$slug}"),
            'post' => $post,
            'breadcrumbs' => $breadcrumbs,
            'breadcrumbSchema' => BreadcrumbSchema::build($breadcrumbs),
        ]);
    }
}
