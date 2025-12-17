<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ConnectReview extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'professional_id',
        'booking_id',
        'rating',
        'comment',
        'is_anonymous',
    ];

    protected $casts = [
        'is_anonymous' => 'boolean',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function professional()
    {
        return $this->belongsTo(ConnectProfessional::class, 'professional_id');
    }

    public function booking()
    {
        return $this->belongsTo(ConnectBooking::class, 'booking_id');
    }
}
