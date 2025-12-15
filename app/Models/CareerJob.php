<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CareerJob extends Model
{
    use HasFactory;

    protected $fillable = [
        'employer_id',
        'title',
        'description',
        'company_name',
        'location',
        'salary_from',
        'salary_to',
        'currency',
        'experience',
        'schedule',
        'employment',
        'accessibility_features',
        'status',
        'published_at',
    ];

    protected $casts = [
        'accessibility_features' => 'array',
        'published_at' => 'datetime',
        'salary_from' => 'integer',
        'salary_to' => 'integer',
    ];

    public function employer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'employer_id');
    }
}
