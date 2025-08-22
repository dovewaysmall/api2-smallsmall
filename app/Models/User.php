<?php

namespace App\Models;

use Laravel\Sanctum\HasApiTokens;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory;

    protected $table = 'user_tbl';
    protected $primaryKey = 'userID';
    public $incrementing = false;
    protected $keyType = 'string';
    public $timestamps = false;
    
    protected $fillable = [
        'id',
        'userID',
        'firstName',
        'lastName',
        'email',
        'password',
        'phone',
        'user_type',
        'verified',
        'referral',
        'status',
        'boarding_status',
        'profile_picture',
        'interest',
        'regDate',
        'income',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected $casts = [
        'email_verified_at' => 'datetime',
        'password' => 'hashed',
    ];
}
