<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ConnectMessage;
use App\Models\User;
use App\Events\MessageSent;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ConnectMessageController extends Controller
{
    // Get list of conversations (users I have chatted with or matched with)
    public function index()
    {
        $userId = auth()->id();

        // Find users where I am sender or receiver
        // This is a bit complex SQL to get unique conversation partners with latest message
        // Simplified approach: Get mutual follows first (allowed to chat), then attach last message info.
        
        // 1. Get mutual follows
        $mutualFollows = User::whereHas('followers', function($q) use ($userId) {
                $q->where('follower_id', $userId);
            })
            ->whereHas('following', function($q) use ($userId) {
                $q->where('following_id', $userId);
            })
            ->select('id', 'name', 'avatar', 'role', 'last_login_at')
            ->get();

        // 2. Attach last message info
        foreach ($mutualFollows as $user) {
            $lastMessage = ConnectMessage::where(function($q) use ($userId, $user) {
                    $q->where('sender_id', $userId)->where('receiver_id', $user->id);
                })
                ->orWhere(function($q) use ($userId, $user) {
                    $q->where('sender_id', $user->id)->where('receiver_id', $userId);
                })
                ->latest()
                ->first();

            $user->last_message = $lastMessage;
            $user->unread_count = ConnectMessage::where('sender_id', $user->id)
                ->where('receiver_id', $userId)
                ->where('is_read', false)
                ->count();
        }

        // Sort by last message date
        $sorted = $mutualFollows->sortByDesc(function($user) {
            return $user->last_message ? $user->last_message->created_at : $user->created_at; // Fallback if no message
        })->values();

        return response()->json($sorted);
    }

    // Get messages with a specific user
    public function show($id)
    {
        $otherUser = User::findOrFail($id);
        $myId = auth()->id();

        // Check if mutual follow exists (security check)
        $isFollowing = auth()->user()->following()->where('following_id', $id)->exists();
        $isFollowedBy = auth()->user()->followers()->where('follower_id', $id)->exists();

        if (!$isFollowing || !$isFollowedBy) {
             // Optional: allow viewing old messages but disable sending?
             // For now, strict: must be mutual friends to chat.
             // But if they were friends and one unfriended, they should still see history.
             // So we proceed to show messages, but frontend will disable input if not mutual.
        }

        // Mark messages as read
        ConnectMessage::where('sender_id', $id)
            ->where('receiver_id', $myId)
            ->where('is_read', false)
            ->update(['is_read' => true]);

        $messages = ConnectMessage::where(function($q) use ($myId, $id) {
                $q->where('sender_id', $myId)->where('receiver_id', $id);
            })
            ->orWhere(function($q) use ($myId, $id) {
                $q->where('sender_id', $id)->where('receiver_id', $myId);
            })
            ->orderBy('created_at', 'desc') // Get newest first to ensure we see them
            ->paginate(50);

        // Reverse the collection so they appear chronologically in the chat (oldest at top)
        $messages->setCollection($messages->getCollection()->reverse()->values());

        // Transform pagination to simple array if frontend expects it, or just return as is
        // The frontend now handles {messages: {data: []}} or {messages: []}
        
        return response()->json([
            'messages' => $messages,
            'partner' => $otherUser->only(['id', 'name', 'avatar', 'role', 'last_login_at']),
            'can_chat' => $isFollowing && $isFollowedBy
        ]);
    }

    // Send a message
    public function store(Request $request, $id)
    {
        $request->validate([
            'content' => 'required|string|max:1000',
        ]);

        $receiverId = $id;
        $senderId = auth()->id();

        // Verify mutual follow
        $isFollowing = auth()->user()->following()->where('following_id', $receiverId)->exists();
        $isFollowedBy = auth()->user()->followers()->where('follower_id', $receiverId)->exists();

        if (!$isFollowing || !$isFollowedBy) {
            return response()->json(['message' => 'You must be mutual followers to send messages.'], 403);
        }

        $message = ConnectMessage::create([
            'sender_id' => $senderId,
            'receiver_id' => $receiverId,
            'content' => $request->input('content'),
            'is_read' => false,
        ]);

        broadcast(new MessageSent($message))->toOthers();

        return response()->json($message, 201);
    }
}
