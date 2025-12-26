<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ConnectPost;
use App\Models\ConnectCategory;
use App\Notifications\PostLiked;
use App\Notifications\PostCommented;
use App\Notifications\PostReposted;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use App\Models\User;

class ConnectPostController extends Controller
{
    // Get feed of root posts (threads)
    public function index(Request $request)
    {
        /** @var \App\Models\User $user */
        $user = Auth::user();
        $userId = $user->id;
        $followingIds = $user->following()->pluck('following_id')->toArray();
        $followingIds[] = $userId; // Include self

        // Base Query
        $query = ConnectPost::with(['category', 'user:id,name,avatar,role'])
            ->withCount('likes');

        $showRootOnly = true;

        // Specific Lists Filters
        if ($request->has('replies_of_user')) {
            $query->where('user_id', $request->replies_of_user)
                  ->whereNotNull('parent_id');
            $showRootOnly = false;
        } elseif ($request->has('reposts_of_user')) {
             $query->join('connect_reposts', 'connect_posts.id', '=', 'connect_reposts.post_id')
                   ->where('connect_reposts.user_id', $request->reposts_of_user)
                   ->select('connect_posts.*');
             $showRootOnly = false; 
        } elseif ($request->has('liked') && $request->boolean('liked')) {
             $query->join('connect_likes', 'connect_posts.id', '=', 'connect_likes.likeable_id')
                   ->where('connect_likes.likeable_type', ConnectPost::class)
                   ->where('connect_likes.user_id', Auth::id())
                   ->select('connect_posts.*'); 
             $showRootOnly = false;
        } elseif ($request->has('saved') && $request->boolean('saved')) {
             $query->join('connect_saved_posts', 'connect_posts.id', '=', 'connect_saved_posts.post_id')
                   ->where('connect_saved_posts.user_id', Auth::id())
                   ->select('connect_posts.*'); 
             $showRootOnly = false;
        }

        if ($showRootOnly) {
            $query->whereNull('parent_id');
        }

        // Logic for Feed vs Specific Filters
        if ($showRootOnly && !$request->has('category_id') && !$request->has('user_id') && !$request->has('type') && !$request->has('q')) {
            // MAIN FEED LOGIC: Global Feed (Show all posts)
            // ...
        }

        // Filter by User (My Posts)
        if ($request->has('user_id')) {
            $query->where('user_id', $request->user_id);
        }
        
        // Filter by Category
        if ($request->has('category_id')) {
            $query->where('category_id', $request->category_id);
        }

        // Filter by Type
        if ($request->has('type')) {
            $query->where('type', $request->type);
        }

        // Search
        if ($request->has('q')) {
            $q = $request->q;
            $query->where(function($query) use ($q) {
                $query->where('title', 'like', "%{$q}%")
                      ->orWhere('content', 'like', "%{$q}%");
            });
        }

        // Sorting
        $sort = $request->get('sort', 'new');
        switch ($sort) {
            case 'popular':
                $query->orderBy('likes_count', 'desc');
                break;
            case 'discussed':
                $query->orderBy('reply_count', 'desc');
                break;
            case 'new':
            default:
                $query->latest();
                break;
        }

        $posts = $query->paginate(20);

        // Process anonymity and attach reposter info
        $posts->getCollection()->transform(function ($post) use ($followingIds) {
            try {
                // Check if this post is here because of a repost by someone I follow
                // (Only if it's not my own post, or maybe even if it is)
                
                // Find all reposts by people I follow
                $reposts = DB::table('connect_reposts')
                    ->where('post_id', $post->id)
                    ->whereIn('user_id', $followingIds)
                    ->orderBy('created_at', 'desc')
                    ->get();

                $reposters = [];
                
                if ($reposts->isNotEmpty()) {
                    // Get user details
                    $reposterIds = $reposts->pluck('user_id');
                    $reposterUsers = User::whereIn('id', $reposterIds)->get()->keyBy('id');
                    
                    foreach ($reposts as $repost) {
                        if (isset($reposterUsers[$repost->user_id])) {
                            $u = $reposterUsers[$repost->user_id];
                            $reposters[] = [
                                'id' => $u->id,
                                'name' => $u->name,
                                'avatar' => $u->avatar,
                                'reposted_at' => $repost->created_at
                            ];
                        }
                    }

                    // Backward compatibility / Primary reposter (latest)
                    if (!empty($reposters)) {
                        $latest = $reposters[0];
                        $post->reposter = $latest;
                    }
                }
                $post->reposters = $reposters;

                return $this->processPostForResponse($post);
            } catch (\Exception $e) {
                \Illuminate\Support\Facades\Log::error('Error transforming post ' . $post->id . ': ' . $e->getMessage());
                return $post; // Return raw post in case of error
            }
        });

        return response()->json($posts);
    }

    // Get single post with context (Thread View)
    public function show($id)
    {
        $post = ConnectPost::with(['category', 'user:id,name,avatar,role'])
            ->withCount('likes')
            ->findOrFail($id);

        // Increment views
        $post->increment('views');

        // 1. Get Ancestors (Context up)
        $ancestors = [];
        if ($post->path) {
            $pathIds = explode('/', $post->path);
            // Remove current id from path to get ancestors
            $ancestorIds = array_diff($pathIds, [$id]);
            
            if (!empty($ancestorIds)) {
                $ancestors = ConnectPost::whereIn('id', $ancestorIds)
                    ->with(['user:id,name,avatar,role'])
                    ->orderBy('depth', 'asc')
                    ->get()
                    ->map(fn($p) => $this->processPostForResponse($p));
            }
        }

        // 2. Get Replies (First level down)
        // Sort replies: relevant (liked) first, then new
        $replies = $post->replies()
            ->with(['user:id,name,avatar,role'])
            ->withCount('likes')
            ->orderBy('likes_count', 'desc')
            ->orderBy('created_at', 'desc')
            ->paginate(20); // Lazy loading support via pagination

        $replies->getCollection()->transform(function ($r) {
            return $this->processPostForResponse($r);
        });

        return response()->json([
            'ancestors' => $ancestors,
            'post' => $this->processPostForResponse($post),
            'replies' => $replies
        ]);
    }

    // Create post or reply
    public function store(Request $request)
    {
        $request->validate([
            'content' => 'required_without:original_post_id|string|nullable',
            'parent_id' => 'nullable|exists:connect_posts,id',
            'original_post_id' => 'nullable|exists:connect_posts,id',
            // Root post required fields
            'title' => 'required_without_all:parent_id,original_post_id|string|max:255|nullable',
            'category_id' => 'required_without_all:parent_id,original_post_id|exists:connect_categories,id|nullable',
            'type' => 'in:question,experience,discussion,support,repost',
            'is_anonymous' => 'boolean',
            'tags' => 'array',
        ]);

        return DB::transaction(function () use ($request) {
            // Handle Repost
            if ($request->original_post_id) {
                // Toggle Repost
                $postId = $request->original_post_id;
                $userId = Auth::id();
                
                $exists = DB::table('connect_reposts')
                    ->where('user_id', $userId)
                    ->where('post_id', $postId)
                    ->exists();

                if ($exists) {
                    DB::table('connect_reposts')
                        ->where('user_id', $userId)
                        ->where('post_id', $postId)
                        ->delete();
                    return response()->json(['reposted' => false]);
                } else {
                    DB::table('connect_reposts')->insert([
                        'user_id' => $userId,
                        'post_id' => $postId,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);

                    // Notify original author if not self
                    $originalPost = ConnectPost::find($postId);
                    if ($originalPost && $originalPost->user_id !== $userId) {
                        $originalPost->user->notify(new PostReposted(Auth::user(), $originalPost));
                    }

                    return response()->json(['reposted' => true]);
                }
            }

            $parent = null;
            if ($request->parent_id) {
                $parent = ConnectPost::find($request->parent_id);
            }

            // Create Post
            $post = ConnectPost::create([
                'user_id' => Auth::id(),
                'category_id' => $parent ? ($parent->category_id ?? null) : $request->category_id,
                'title' => $parent ? null : $request->title,
                'content' => $request->input('content'),
                'type' => $parent ? ($parent->type ?? 'discussion') : ($request->type ?? 'discussion'),
                'is_anonymous' => $request->boolean('is_anonymous'),
                'tags' => $parent ? $parent->tags : $request->tags,
                'parent_id' => $parent ? $parent->id : null,
                'depth' => $parent ? $parent->depth + 1 : 0,
                // root_thread_id and path will be set after ID creation
            ]);

            // Calculate Path and Root
            if ($parent) {
                $post->root_thread_id = $parent->root_thread_id ?? $parent->id;
                $post->path = $parent->path . '/' . $post->id;
                
                // Increment counters
                $parent->increment('reply_count');
                if ($post->root_thread_id !== $parent->id) {
                    ConnectPost::where('id', $post->root_thread_id)->increment('reply_count');
                }

                // Notify parent author if not self
                if ($parent->user_id !== Auth::id()) {
                    $parent->user->notify(new PostCommented(Auth::user(), $parent, $request->input('content')));
                }
            } else {
                $post->root_thread_id = $post->id;
                $post->path = (string) $post->id;
            }

            $post->save();

            return response()->json($this->processPostForResponse($post), 201);
        });
    }
    
            // Like/Unlike post (Unified for posts and replies)
    public function like($id)
    {
        $post = ConnectPost::findOrFail($id);
        $user = Auth::user();
        
        $existingLike = $post->likes()->where('user_id', $user->id)->first();
        
        if ($existingLike) {
            $existingLike->delete();
            $post->decrement('likes_count');
            return response()->json(['liked' => false, 'count' => $post->likes_count]);
        } else {
            $post->likes()->create([
                'user_id' => $user->id
            ]);
            $post->increment('likes_count');

            // Notify author if not self
            if ($post->user_id !== $user->id) {
                $post->user->notify(new PostLiked($user, $post));
            }

            return response()->json(['liked' => true, 'count' => $post->likes_count]);
        }
    }

    // Save/Unsave post
    public function save($id)
    {
        $post = ConnectPost::findOrFail($id);
        $user = Auth::user();
        
        $exists = DB::table('connect_saved_posts')
            ->where('user_id', $user->id)
            ->where('post_id', $post->id)
            ->exists();
        
        if ($exists) {
            DB::table('connect_saved_posts')
                ->where('user_id', $user->id)
                ->where('post_id', $post->id)
                ->delete();
            return response()->json(['saved' => false]);
        } else {
            DB::table('connect_saved_posts')->insert([
                'user_id' => $user->id,
                'post_id' => $post->id,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            return response()->json(['saved' => true]);
        }
    }

    // Helper to process post display logic
    private function processPostForResponse($post)
    {
        // Anonymity
        if ($post->is_anonymous) {
            // If current user is author, show "You (Anon)"
            if (Auth::id() === $post->user_id) {
                $post->author_name = 'Вы (Анонимно)';
            } else {
                $post->user = null; // Hide user object
                $post->author_name = 'Аноним';
            }
        } else {
            $post->author_name = $post->user->name ?? 'Unknown';
        }

        // Like status
        $post->is_liked = Auth::check() ? $post->likes()->where('user_id', Auth::id())->exists() : false;
        
        // Saved status
        $post->is_saved = Auth::check() ? DB::table('connect_saved_posts')
            ->where('user_id', Auth::id())
            ->where('post_id', $post->id)
            ->exists() : false;

        // Process Original Post if it exists
        if ($post->originalPost) {
            $post->originalPost->author_name = $post->originalPost->is_anonymous ? 'Аноним' : ($post->originalPost->user->name ?? 'Unknown');
            if ($post->originalPost->is_anonymous) {
                $post->originalPost->user = null;
            }
        }

        // Reposted by me?
        $myRepost = Auth::check() ? DB::table('connect_reposts')
            ->where('user_id', Auth::id())
            ->where('post_id', $post->id)
            ->first() : null;

        $post->is_reposted = (bool) $myRepost;
        $post->my_reposted_at = $myRepost ? $myRepost->created_at : null;

        return $post;
    }

    // Get categories
    public function categories()
    {
        return response()->json(ConnectCategory::all());
    }
}
