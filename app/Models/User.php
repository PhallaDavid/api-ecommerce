<?php

namespace App\Models;

use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Illuminate\Support\Facades\Storage;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

    protected $fillable = [
        'name',
        'email',
        'phone',
        'avatar',
        'password',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected $casts = [
        'email_verified_at' => 'datetime',
    ];

    protected $appends = [
        'avatar_url',
        'email_verified',
        'phone_verified',
    ];

    public function getAvatarUrlAttribute()
    {
        if (empty($this->avatar)) {
            return null;
        }

        return Storage::disk('public')->url($this->avatar);
    }

    public function getEmailVerifiedAttribute()
    {
        return !is_null($this->email_verified_at);
    }

    public function getPhoneVerifiedAttribute()
    {
        return !is_null($this->phone_verified_at);
    }
}
