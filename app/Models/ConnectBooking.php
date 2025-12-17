<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ConnectBooking extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'professional_id',
        'service_id',
        'schedule_id',
        'status',
        'is_anonymous',
        'notes',
        'meeting_link',
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

    public function service()
    {
        return $this->belongsTo(ConnectService::class, 'service_id');
    }

    public function schedule()
    {
        return $this->belongsTo(ConnectSchedule::class, 'schedule_id');
    }
}
