<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable, HasApiTokens;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'phone',
        'birth_date',
        'role',
        'avatar',
        'bio',
        'is_active',
        'last_login_at',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'birth_date' => 'date',
            'last_login_at' => 'datetime',
            'is_active' => 'boolean',
        ];
    }

    // Relationships
    public function instructedCourses()
    {
        return $this->hasMany(Course::class, 'instructor_id');
    }

    public function purchases()
    {
        return $this->hasMany(Purchase::class);
    }

    public function progress()
    {
        return $this->hasMany(Progress::class);
    }

    public function enrolledCourses()
    {
        return $this->belongsToMany(Course::class, 'purchases')
                    ->wherePivot('status', 'completed');
    }

    // Connect / Forum Relationships
    public function posts()
    {
        return $this->hasMany(ConnectPost::class);
    }

    public function connectComments()
    {
        return $this->hasMany(ConnectComment::class);
    }

    public function connectProfile()
    {
        return $this->hasOne(ConnectProfessional::class);
    }

    public function connectBookings()
    {
        return $this->hasMany(ConnectBooking::class);
    }

    public function connectSubscription()
    {
        return $this->hasOne(ConnectSubscription::class)->latest();
    }

    // Social Features
    public function followers()
    {
        return $this->belongsToMany(User::class, 'connect_follows', 'following_id', 'follower_id')
                    ->withPivot('status')
                    ->withTimestamps();
    }

    public function following()
    {
        return $this->belongsToMany(User::class, 'connect_follows', 'follower_id', 'following_id')
                    ->withPivot('status')
                    ->withTimestamps();
    }

    public function sentMessages()
    {
        return $this->hasMany(ConnectMessage::class, 'sender_id');
    }

    public function receivedMessages()
    {
        return $this->hasMany(ConnectMessage::class, 'receiver_id');
    }
}
