<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ConnectPost;
use App\Models\ConnectCategory;
use App\Notifications\PostLiked;
use App\Notifications\PostCommented;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Models\User;

class ConnectPostController extends Controller
{
    // Get feed of root posts (threads)
    public function index(Request $request)
    {
        $userId = auth()->id();
        $followingIds = auth()->user()->following()->pluck('following_id')->toArray();
        $followingIds[] = $userId; // Include self

        // Base Query: Root posts
        $query = ConnectPost::whereNull('parent_id')
            ->with(['category', 'user:id,name,avatar,role'])
            ->withCount('likes');

        // Logic for Feed vs Specific Filters
        // If filtering by specific category or type, we might not want repost logic mixed in unless we decide reposts also inherit category.
        // For now, let's assume "Feed" (no specific filters) shows reposts.

        if (!$request->has('category_id') && !$request->has('user_id') && !$request->has('type') && !$request->has('q') && !$request->has('saved')) {
            // MAIN FEED LOGIC: Posts from following OR Reposts from following
            
            // We need to fetch posts that are:
            // 1. Created by people I follow (or me)
            // 2. Reposted by people I follow (or me)

            // To do this efficiently with pagination and ordering by "activity time" (created_at of post OR created_at of repost),
            // we might need a Union or a complex Join.
            
            // Simplified approach: 
            // Select posts where user_id IN following 
            // OR id IN (select post_id from reposts where user_id IN following)
            
            // But we need the "reposter" info attached to the specific row in the result set.
            // A post might appear multiple times if multiple friends reposted it? 
            // Standard feed usually deduplicates and shows "Friend A and Friend B reposted".
            // For MVP, let's just pick the latest repost event if it exists.

            $query->where(function($q) use ($followingIds) {
                $q->whereIn('user_id', $followingIds)
                  ->orWhereExists(function ($sub) use ($followingIds) {
                      $sub->select(DB::raw(1))
                          ->from('connect_reposts')
                          ->whereColumn('connect_reposts.post_id', 'connect_posts.id')
                          ->whereIn('connect_reposts.user_id', $followingIds);
                  });
            });
            
            // We need to attach the "reposter" info for the frontend.
            // We can do this in the transformation step or via a subquery select.
            // Let's do it in transformation for simplicity, although it's N+1 queries if not careful.
            // Better: Eager load a "reposters" relationship filtered by my following.
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

        // Filter by Saved
        if ($request->has('saved') && $request->boolean('saved')) {
             $query->join('connect_saved_posts', 'connect_posts.id', '=', 'connect_saved_posts.post_id')
                   ->where('connect_saved_posts.user_id', auth()->id())
                   ->select('connect_posts.*'); 
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
            // Check if this post is here because of a repost by someone I follow
            // (Only if it's not my own post, or maybe even if it is)
            
            // Find the latest repost by someone I follow
            $repost = DB::table('connect_reposts')
                ->where('post_id', $post->id)
                ->whereIn('user_id', $followingIds)
                ->orderBy('created_at', 'desc')
                ->first();

            if ($repost) {
                $reposter = User::find($repost->user_id);
                if ($reposter) {
                    $post->reposter = [
                        'id' => $reposter->id,
                        'name' => $reposter->name,
                        'avatar' => $reposter->avatar,
                        'reposted_at' => $repost->created_at
                    ];
                }
            }

            return $this->processPostForResponse($post);
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
                $userId = auth()->id();
                
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
                    return response()->json(['reposted' => true]);
                }
            }

            $parent = null;
            if ($request->parent_id) {
                $parent = ConnectPost::find($request->parent_id);
            }

            // Create Post
            $post = ConnectPost::create([
                'user_id' => auth()->id(),
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
                if ($parent->user_id !== auth()->id()) {
                    $parent->user->notify(new PostCommented(auth()->user(), $parent, $request->input('content')));
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
        $user = auth()->user();
        
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
        $user = auth()->user();
        
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
            if (auth()->id() === $post->user_id) {
                $post->author_name = 'Вы (Анонимно)';
            } else {
                $post->user = null; // Hide user object
                $post->author_name = 'Аноним';
            }
        } else {
            $post->author_name = $post->user->name ?? 'Unknown';
        }

        // Like status
        $post->is_liked = auth()->check() ? $post->likes()->where('user_id', auth()->id())->exists() : false;
        
        // Saved status
        $post->is_saved = auth()->check() ? DB::table('connect_saved_posts')
            ->where('user_id', auth()->id())
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
        $post->is_reposted = auth()->check() ? DB::table('connect_reposts')
            ->where('user_id', auth()->id())
            ->where('post_id', $post->id)
            ->exists() : false;

        return $post;
    }

    // Get categories
    public function categories()
    {
        return response()->json(ConnectCategory::all());
    }
}
