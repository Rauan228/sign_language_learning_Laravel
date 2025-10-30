<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CareerTest extends Model
{
    protected $fillable = [
        'title',
        'description',
        'is_active',
        'time_limit'
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function questions(): HasMany
    {
        return $this->hasMany(CareerQuestion::class)->orderBy('order');
    }

    public function results(): HasMany
    {
        return $this->hasMany(CareerTestResult::class);
    }
}
