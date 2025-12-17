<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ConnectProfessional extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'title',
        'specialization',
        'bio',
        'languages',
        'price_per_hour',
        'rating',
        'reviews_count',
        'is_verified',
    ];

    protected $casts = [
        'languages' => 'array',
        'is_verified' => 'boolean',
        'price_per_hour' => 'decimal:2',
        'rating' => 'float',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function services()
    {
        return $this->hasMany(ConnectService::class, 'professional_id');
    }

    public function schedules()
    {
        return $this->hasMany(ConnectSchedule::class, 'professional_id');
    }

    public function bookings()
    {
        return $this->hasMany(ConnectBooking::class, 'professional_id');
    }

    public function reviews()
    {
        return $this->hasMany(ConnectReview::class, 'professional_id');
    }
}
