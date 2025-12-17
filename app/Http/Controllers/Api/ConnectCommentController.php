<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ConnectComment;
use App\Models\ConnectPost;
use Illuminate\Http\Request;

class ConnectCommentController extends Controller
{
    // Create comment
    public function store(Request $request)
    {
        $request->validate([
            'post_id' => 'required|exists:connect_posts,id',
            'content' => 'required|string',
            'parent_id' => 'nullable|exists:connect_comments,id',
            'is_anonymous' => 'boolean',
        ]);

        $comment = ConnectComment::create([
            'user_id' => auth()->id(),
            'post_id' => $request->post_id,
            'content' => $request->input('content'),
            'parent_id' => $request->parent_id,
            'is_anonymous' => $request->boolean('is_anonymous'),
        ]);

        // Increment post comment count
        ConnectPost::where('id', $request->post_id)->increment('comments_count');
        ConnectPost::where('id', $request->post_id)->update(['last_activity_at' => now()]);

        return response()->json($comment, 201);
    }

    // Like comment
    public function like($id)
    {
        $comment = ConnectComment::findOrFail($id);
        $user = auth()->user();
        
        $existingLike = $comment->likes()->where('user_id', $user->id)->first();
        
        if ($existingLike) {
            $existingLike->delete();
            $comment->decrement('likes_count');
            return response()->json(['liked' => false, 'count' => $comment->likes_count]);
        } else {
            $comment->likes()->create([
                'user_id' => $user->id
            ]);
            $comment->increment('likes_count');
            return response()->json(['liked' => true, 'count' => $comment->likes_count]);
        }
    }
}
