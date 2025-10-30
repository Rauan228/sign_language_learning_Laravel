<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class LessonMedia extends Model
{
    use HasFactory;

    protected $table = 'lesson_media';

    protected $fillable = [
        'lesson_id',
        'type',
        'provider',
        'storage_path',
        'url',
        'mime',
        'duration',
        'quality',
        'sources',
        'captions',
        'poster_url',
        'is_default',
        'extra',
    ];

    protected function casts(): array
    {
        return [
            'sources' => 'array',
            'captions' => 'array',
            'extra' => 'array',
            'is_default' => 'boolean',
        ];
    }

    /**
     * Get the lesson that owns the media.
     */
    public function lesson()
    {
        return $this->belongsTo(Lesson::class);
    }

    /**
     * Get the full URL for the media file.
     */
    public function getFullUrlAttribute()
    {
        if ($this->url) {
            // If it's already a full URL, return as is
            if (str_starts_with($this->url, 'http')) {
                return $this->url;
            }
            
            // Otherwise, prepend the storage URL
            return asset('storage/' . ltrim($this->url, '/'));
        }
        
        return null;
    }

    /**
     * Scope to get default media for a lesson.
     */
    public function scopeDefault($query)
    {
        return $query->where('is_default', true);
    }

    /**
     * Scope to get media by type.
     */
    public function scopeOfType($query, $type)
    {
        return $query->where('type', $type);
    }
}