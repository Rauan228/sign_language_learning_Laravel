<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;

class ConnectFollowController extends Controller
{
    // Follow a user
    public function store(Request $request, $id)
    {
        $targetUser = User::findOrFail($id);
        $currentUser = auth()->user();

        if ($currentUser->id === $targetUser->id) {
            return response()->json(['message' => 'Cannot follow yourself'], 422);
        }

        // Check if already following
        if (!$currentUser->following()->where('following_id', $targetUser->id)->exists()) {
            $currentUser->following()->attach($targetUser->id, ['status' => 'accepted']);
        }

        return response()->json([
            'success' => true,
            'message' => 'Followed successfully',
            'is_following' => true,
            'is_mutual' => $targetUser->following()->where('following_id', $currentUser->id)->exists()
        ]);
    }

    // Unfollow a user
    public function destroy($id)
    {
        $targetUser = User::findOrFail($id);
        $currentUser = auth()->user();

        $currentUser->following()->detach($targetUser->id);

        return response()->json([
            'success' => true,
            'message' => 'Unfollowed successfully',
            'is_following' => false
        ]);
    }

    // Get followers of a user
    public function followers($id)
    {
        $user = User::findOrFail($id);
        $followers = $user->followers()
            ->select('users.id', 'users.name', 'users.avatar', 'users.role', 'users.bio')
            ->paginate(20);

        // Add context for current user (am I following them?)
        if (auth()->check()) {
            $myFollowingIds = auth()->user()->following()->pluck('users.id')->toArray();
            $followers->getCollection()->transform(function ($follower) use ($myFollowingIds) {
                $follower->is_following = in_array($follower->id, $myFollowingIds);
                return $follower;
            });
        }

        return response()->json($followers);
    }

    // Get users followed by a user
    public function following($id)
    {
        $user = User::findOrFail($id);
        $following = $user->following()
            ->select('users.id', 'users.name', 'users.avatar', 'users.role', 'users.bio')
            ->paginate(20);

        // Add context for current user
        if (auth()->check()) {
            $myFollowingIds = auth()->user()->following()->pluck('users.id')->toArray();
            $following->getCollection()->transform(function ($followed) use ($myFollowingIds) {
                $followed->is_following = in_array($followed->id, $myFollowingIds);
                return $followed;
            });
        }

        return response()->json($following);
    }
}
