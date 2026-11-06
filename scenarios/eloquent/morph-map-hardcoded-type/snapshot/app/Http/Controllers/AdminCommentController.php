<?php

namespace App\Http\Controllers;

use App\Models\Comment;
use App\Models\Listing;
use App\Models\Order;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdminCommentController extends Controller
{
    private const MODEL_TYPES = [
        'order' => Order::class,
        'listing' => Listing::class,
    ];

    public function index(Request $request): JsonResponse
    {
        $model = $request->query('model', 'order');

        $comments = Comment::query()
            ->where('commentable_type', self::MODEL_TYPES[$model] ?? null)
            ->latest('id')
            ->get()
            ->map(fn (Comment $comment) => [
                'id' => $comment->id,
                'body' => $comment->body,
                'about_type' => $comment->commentable_type,
                'about_id' => $comment->commentable_id,
                'approved' => $comment->approved,
            ]);

        return response()->json(['data' => $comments]);
    }

    public function approve(Comment $comment): JsonResponse
    {
        $comment->forceFill(['approved' => true])->save();

        return response()->json(['message' => 'approved']);
    }
}
