<?php

namespace App\Http\Controllers;

use App\Models\Post;
use Illuminate\Http\JsonResponse;

class PostController extends Controller
{
    public function index(): JsonResponse
    {
        $posts = Post::query()
            ->latest()
            ->limit(25)
            ->get()
            ->map(fn (Post $post) => [
                'id' => $post->id,
                'title' => $post->title,
                'author' => $post->author_name,
                'published_at' => $post->created_at?->toISOString(),
            ]);

        return response()->json(['data' => $posts]);
    }
}
