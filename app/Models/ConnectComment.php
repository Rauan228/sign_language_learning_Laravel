<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class ConnectComment extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'post_id',
        'user_id',
        'parent_id',
        'content',
        'is_anonymous',
        'likes_count',
    ];

    protected $casts = [
        'is_anonymous' => 'boolean',
    ];

    public function post()
    {
        return $this->belongsTo(ConnectPost::class, 'post_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function parent()
    {
        return $this->belongsTo(ConnectComment::class, 'parent_id');
    }

    public function replies()
    {
        return $this->hasMany(ConnectComment::class, 'parent_id');
    }

    public function likes()
    {
        return $this->morphMany(ConnectLike::class, 'likeable');
    }
}
