<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\UserController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\AdminController;
use App\Http\Controllers\Api\BookingController;

Route::post('/login', [AuthController::class, 'login']);

Route::middleware('auth:sanctum')->group(function () {
    Route::get('/user', function (Request $request) {
        return $request->user();
    });
    
    Route::get('/users', [UserController::class, 'index']);
    Route::get('/users/count', [UserController::class, 'count']);
    Route::get('/users/{id}', [UserController::class, 'show']);
    
    Route::get('/cx-users', [AdminController::class, 'getCXUsers']);
    Route::get('/cx-users/{id}', [AdminController::class, 'getCXUser']);
    
    Route::get('/bookings', [BookingController::class, 'index']);
    Route::get('/bookings/user/{userID}', [BookingController::class, 'getUserBookings']);
    
    Route::post('/logout', [AuthController::class, 'logout']);
});