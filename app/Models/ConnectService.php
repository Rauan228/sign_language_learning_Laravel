<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ConnectService extends Model
{
    use HasFactory;

    protected $fillable = [
        'professional_id',
        'type',
        'name',
        'description',
        'duration_minutes',
        'price',
        'max_participants',
    ];

    protected $casts = [
        'price' => 'decimal:2',
    ];

    public function professional()
    {
        return $this->belongsTo(ConnectProfessional::class, 'professional_id');
    }
}
