<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CareerQuestion extends Model
{
    protected $fillable = [
        'career_test_id',
        'question_text',
        'question_type',
        'options',
        'category',
        'order',
        'is_required'
    ];

    protected $casts = [
        'options' => 'array',
        'is_required' => 'boolean',
    ];

    public function careerTest(): BelongsTo
    {
        return $this->belongsTo(CareerTest::class);
    }
}
