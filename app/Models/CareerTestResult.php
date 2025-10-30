<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CareerTestResult extends Model
{
    protected $fillable = [
        'user_id',
        'career_test_id',
        'answers',
        'disability_info',
        'ai_analysis',
        'recommendations',
        'completion_time'
    ];

    protected $casts = [
        'answers' => 'array',
        'recommendations' => 'array',
        'ai_analysis' => 'array',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function careerTest(): BelongsTo
    {
        return $this->belongsTo(CareerTest::class);
    }
}
