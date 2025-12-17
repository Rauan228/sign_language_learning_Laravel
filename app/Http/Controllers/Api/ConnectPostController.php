<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ConnectPost;
use App\Models\ConnectCategory;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ConnectPostController extends Controller
{
    // Get feed of root posts (threads)
    public function index(Request $request)
    {
        // Only fetch root posts (depth = 0 or parent_id is null)
        $query = ConnectPost::whereNull('parent_id')
            ->with(['category', 'user:id,name,avatar,role'])
            ->withCount('likes'); // reply_count is already a column

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

        // Process anonymity
        $posts->getCollection()->transform(function ($post) {
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
            'content' => 'required|string',
            'parent_id' => 'nullable|exists:connect_posts,id',
            // Root post required fields
            'title' => 'required_without:parent_id|string|max:255|nullable',
            'category_id' => 'required_without:parent_id|exists:connect_categories,id|nullable',
            'type' => 'in:question,experience,discussion,support',
            'is_anonymous' => 'boolean',
            'tags' => 'array',
        ]);

        return DB::transaction(function () use ($request) {
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
            return response()->json(['liked' => true, 'count' => $post->likes_count]);
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

        return $post;
    }

    // Get categories
    public function categories()
    {
        return response()->json(ConnectCategory::all());
    }
}
