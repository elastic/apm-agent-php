<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

    protected $fillable = [
        'email', 'name', 'token'
    ];

    protected $hidden = [
        'token'
    ];

    public function posts() {
        return $this->hasMany(Post::class, 'user_id');
    }
//    use HasApiTokens, HasFactory, Notifiable;
//
//    protected $fillable = [
//        'name',
//        'email',
//        'password',
//    ];
//
//    protected $hidden = [
//        'password',
//        'remember_token',
//    ];
//
//    protected $casts = [
//        'email_verified_at' => 'datetime',
//    ];
}
