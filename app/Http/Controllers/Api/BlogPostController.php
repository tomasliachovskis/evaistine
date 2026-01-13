<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\BlogPost;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class BlogPostController extends Controller
{
    public function index()
    {
        $posts = BlogPost::published()
            ->orderBy('published_at', 'desc')
            ->paginate(15);

        $posts->getCollection()->transform(function ($post) {
            if ($post->review_image) {
                $post->review_image_url = Storage::url($post->review_image);
            }
            return $post;
        });

        return response()->json($posts);
    }

    public function show($slug)
    {
        $post = BlogPost::published()->where('slug', $slug)->firstOrFail();

        if ($post->review_image) {
            $post->review_image_url = Storage::url($post->review_image);
        }

        return response()->json($post);
    }
}
