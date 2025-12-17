<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ConnectSchedule extends Model
{
    use HasFactory;

    protected $fillable = [
        'professional_id',
        'start_time',
        'end_time',
        'is_booked',
    ];

    protected $casts = [
        'start_time' => 'datetime',
        'end_time' => 'datetime',
        'is_booked' => 'boolean',
    ];

    public function professional()
    {
        return $this->belongsTo(ConnectProfessional::class, 'professional_id');
    }
}
