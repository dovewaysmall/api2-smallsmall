<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\UserController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\AdminController;
use App\Http\Controllers\Api\BookingController;
use App\Http\Controllers\Api\CallLogController;
use App\Http\Controllers\Api\EmployeeController;
use App\Http\Controllers\Api\FeedbackController;
use App\Http\Controllers\Api\InspectionController;
use App\Http\Controllers\Api\LandlordController;
use App\Http\Controllers\Api\PayoutController;
use App\Http\Controllers\Api\PropertyController;

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
    
    Route::get('/feedback', [FeedbackController::class, 'index']);
    Route::post('/feedback', [FeedbackController::class, 'store']);
    Route::get('/feedback/stats', [FeedbackController::class, 'getStats']);
    Route::get('/feedback/rating/{rating}', [FeedbackController::class, 'getByRating']);
    Route::get('/feedback/date-range', [FeedbackController::class, 'getByDateRange']);
    Route::get('/feedback/{id}', [FeedbackController::class, 'show']);
    Route::put('/feedback/{id}', [FeedbackController::class, 'update']);
    Route::delete('/feedback/{id}', [FeedbackController::class, 'destroy']);
    
    Route::get('/inspections', [InspectionController::class, 'index']);
    Route::post('/inspections', [InspectionController::class, 'store']);
    Route::get('/inspections/stats', [InspectionController::class, 'getStats']);
    Route::get('/inspections/status/{status}', [InspectionController::class, 'getByStatus']);
    Route::get('/inspections/type/{type}', [InspectionController::class, 'getByType']);
    Route::get('/inspections/user/{userID}', [InspectionController::class, 'getByUser']);
    Route::get('/inspections/date-range', [InspectionController::class, 'getByDateRange']);
    Route::get('/inspections/{id}', [InspectionController::class, 'show']);
    Route::put('/inspections/{id}', [InspectionController::class, 'update']);
    Route::delete('/inspections/{id}', [InspectionController::class, 'destroy']);
    
    Route::get('/landlords', [LandlordController::class, 'index']);
    Route::post('/landlords', [LandlordController::class, 'store']);
    Route::get('/landlords/count', [LandlordController::class, 'count']);
    Route::get('/landlords/stats', [LandlordController::class, 'getStats']);
    Route::get('/landlords/verified', [LandlordController::class, 'getVerified']);
    Route::get('/landlords/search', [LandlordController::class, 'search']);
    Route::get('/landlords/state/{state}', [LandlordController::class, 'getByState']);
    Route::get('/landlords/{id}', [LandlordController::class, 'show']);
    Route::put('/landlords/{id}', [LandlordController::class, 'update']);
    Route::delete('/landlords/{id}', [LandlordController::class, 'destroy']);
    
    Route::get('/payouts', [PayoutController::class, 'index']);
    Route::post('/payouts', [PayoutController::class, 'store']);
    Route::get('/payouts/stats', [PayoutController::class, 'getStats']);
    Route::get('/payouts/status/{status}', [PayoutController::class, 'getByStatus']);
    Route::get('/payouts/payee/{payeeId}', [PayoutController::class, 'getByPayee']);
    Route::get('/payouts/due/{days?}', [PayoutController::class, 'getDue']);
    Route::get('/payouts/date-range', [PayoutController::class, 'getByDateRange']);
    Route::get('/payouts/{id}', [PayoutController::class, 'show']);
    Route::put('/payouts/{id}', [PayoutController::class, 'update']);
    Route::delete('/payouts/{id}', [PayoutController::class, 'destroy']);
    
    Route::get('/properties', [PropertyController::class, 'index']);
    Route::post('/properties', [PropertyController::class, 'store']);
    Route::get('/properties/stats', [PropertyController::class, 'getStats']);
    Route::get('/properties/featured', [PropertyController::class, 'getFeatured']);
    Route::get('/properties/search', [PropertyController::class, 'search']);
    Route::get('/properties/location', [PropertyController::class, 'getByLocation']);
    Route::get('/properties/status/{status}', [PropertyController::class, 'getByStatus']);
    Route::get('/properties/owner/{landlordId}', [PropertyController::class, 'getByOwner']);
    Route::get('/properties/{id}', [PropertyController::class, 'show']);
    Route::put('/properties/{id}', [PropertyController::class, 'update']);
    Route::delete('/properties/{id}', [PropertyController::class, 'destroy']);
    
    Route::post('/logout', [AuthController::class, 'logout']);
});





