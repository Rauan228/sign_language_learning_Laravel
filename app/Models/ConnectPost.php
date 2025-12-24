<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class ConnectPost extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'user_id',
        'category_id',
        'root_thread_id',
        'parent_id',
        'depth',
        'path',
        'title',
        'content',
        'type',
        'status',
        'moderation_state',
        'is_anonymous',
        'tags',
        'views',
        'likes_count',
        'reply_count',
        'last_activity_at',
    ];

    protected $casts = [
        'tags' => 'array',
        'is_anonymous' => 'boolean',
        'last_activity_at' => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function category()
    {
        return $this->belongsTo(ConnectCategory::class, 'category_id');
    }

    // Parent post
    public function parent()
    {
        return $this->belongsTo(ConnectPost::class, 'parent_id');
    }

    // Direct replies
    public function replies()
    {
        return $this->hasMany(ConnectPost::class, 'parent_id');
    }
    
    // Root post of the thread
    public function root()
    {
        return $this->belongsTo(ConnectPost::class, 'root_thread_id');
    }

    public function originalPost()
    {
        return $this->belongsTo(ConnectPost::class, 'original_post_id');
    }

    public function reposts()
    {
        return $this->hasMany(ConnectPost::class, 'original_post_id');
    }

    public function likes()
    {
        return $this->morphMany(ConnectLike::class, 'likeable');
    }
}
