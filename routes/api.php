<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\UserController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\AdminController;
use App\Http\Controllers\Api\BookingController;
use App\Http\Controllers\Api\CallLogController;
use App\Http\Controllers\Api\EmployeeController;

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
    Route::get('/bookings/count', [BookingController::class, 'count']);
    Route::get('/bookings/user/{userID}', [BookingController::class, 'getUserBookings']);
    
    Route::get('/subscriptions/due-this-month', [BookingController::class, 'getSubscriptionsDueThisMonth']);
    Route::get('/subscriptions/due-in-two-weeks', [BookingController::class, 'getSubscriptionsDueInTwoWeeks']);
    Route::get('/subscriptions/{id}', [BookingController::class, 'getSubscriptionDueById']);
    
    Route::get('/call-logs', [CallLogController::class, 'index']);
    Route::post('/call-logs', [CallLogController::class, 'store']);
    Route::get('/call-logs/count', [CallLogController::class, 'count']);
    Route::get('/call-logs/user-group/{userGroup}', [CallLogController::class, 'getByUserGroup']);
    Route::get('/call-logs/date-range', [CallLogController::class, 'getByDateRange']);
    
    Route::get('/employees', [EmployeeController::class, 'index']);
    Route::post('/employees', [EmployeeController::class, 'store']);
    Route::get('/employees/active', [EmployeeController::class, 'getActiveEmployees']);
    Route::get('/employees/stats', [EmployeeController::class, 'getEmployeeStats']);
    Route::get('/employees/search', [EmployeeController::class, 'search']);
    Route::get('/employees/department/{department}', [EmployeeController::class, 'getByDepartment']);
    Route::get('/employees/{id}', [EmployeeController::class, 'show']);
    Route::put('/employees/{id}', [EmployeeController::class, 'update']);
    Route::delete('/employees/{id}', [EmployeeController::class, 'destroy']);
    
    Route::post('/logout', [AuthController::class, 'logout']);
});





