<?php

namespace App\Models;

use Laravel\Sanctum\HasApiTokens;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Admin extends Authenticatable
{
    use HasApiTokens, HasFactory;

    protected $table = 'admin_tbl';
    protected $primaryKey = 'adminID';
    public $incrementing = false;
    protected $keyType = 'string';
    public $timestamps = false;
    
    protected $fillable = [
        'adminID',
        'firstName',
        'lastName',
        'email',
        'password',
        'phone',
        'role',
        'department',
        'status',
        'profile_picture',
        'date_hired',
        'salary',
        'manager_id',
        'last_login',
        'created_at',
        'updated_at'
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