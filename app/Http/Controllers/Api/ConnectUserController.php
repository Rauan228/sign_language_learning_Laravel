<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;

class ConnectUserController extends Controller
{
    public function show($id)
    {
        $user = User::select('id', 'name', 'avatar', 'bio', 'role', 'created_at')
            ->withCount(['followers', 'following', 'posts'])
            ->findOrFail($id);

        if (auth()->check()) {
            $currentUser = auth()->user();
            $user->is_following = $currentUser->following()->where('following_id', $id)->exists();
            $user->is_mutual = $user->is_following && $currentUser->followers()->where('follower_id', $id)->exists();
        } else {
            $user->is_following = false;
            $user->is_mutual = false;
        }

        return response()->json([
            'success' => true,
            'data' => $user
        ]);
    }
}
