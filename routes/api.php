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
use App\Http\Controllers\Api\RepairController;
use App\Http\Controllers\Api\StaffController;
use App\Http\Controllers\Api\TenantController;
use App\Http\Controllers\Api\AccountManagerController;
use App\Http\Controllers\Api\TransactionController;
use App\Http\Controllers\Api\VerificationController;

Route::post('/login', [AuthController::class, 'login'])->name('login');

Route::middleware('auth:sanctum')->group(function () {
    Route::get('/user', function (Request $request) {
        return $request->user();
    });
    
    Route::get('/users', [UserController::class, 'index']);
    Route::get('/users/count', [UserController::class, 'count']);
    Route::get('/users/count/monthly', [UserController::class, 'monthlyCount']);
    Route::get('/users/count/yearly', [UserController::class, 'yearlyCount']);
    Route::get('/users/unconverted', [UserController::class, 'getAllUnconverted']);
    Route::get('/users/unconverted/this-week', [UserController::class, 'getUnconvertedThisWeek']);
    Route::get('/users/unconverted/this-month', [UserController::class, 'getUnconvertedThisMonth']);
    Route::get('/users/unconverted/this-year', [UserController::class, 'getUnconvertedThisYear']);
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
    Route::get('/inspections/count', [InspectionController::class, 'count']);
    Route::get('/inspections/count/monthly', [InspectionController::class, 'monthlyCount']);
    Route::get('/inspections/stats', [InspectionController::class, 'getStats']);
    Route::get('/inspections/status/{status}', [InspectionController::class, 'getByStatus']);
    Route::get('/inspections/type/{type}', [InspectionController::class, 'getByType']);
    Route::get('/inspections/user/{userID}', [InspectionController::class, 'getByUser']);
    Route::get('/inspections/date-range', [InspectionController::class, 'getByDateRange']);
    Route::get('/inspections/this-week', [InspectionController::class, 'getThisWeek']);
    Route::get('/inspections/this-month', [InspectionController::class, 'getThisMonth']);
    Route::get('/inspections/this-year', [InspectionController::class, 'getThisYear']);
    Route::get('/inspections/{id}', [InspectionController::class, 'show']);
    Route::put('/inspections/{id}', [InspectionController::class, 'update']);
    Route::delete('/inspections/{id}', [InspectionController::class, 'destroy']);
    
    Route::get('/landlords', [LandlordController::class, 'index']);
    Route::post('/landlords', [LandlordController::class, 'store']);
    Route::get('/landlords/count', [LandlordController::class, 'count']);
    Route::get('/landlords/stats', [LandlordController::class, 'getStats']);
    Route::get('/landlords/verified', [LandlordController::class, 'getVerified']);
    Route::get('/landlords/search', [LandlordController::class, 'search']);
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
    Route::get('/properties/count', [PropertyController::class, 'count']);
    Route::get('/properties/stats', [PropertyController::class, 'getStats']);
    Route::get('/properties/featured', [PropertyController::class, 'getFeatured']);
    Route::get('/properties/search', [PropertyController::class, 'search']);
    Route::get('/properties/location', [PropertyController::class, 'getByLocation']);
    Route::get('/properties/status/{status}', [PropertyController::class, 'getByStatus']);
    Route::get('/properties/owner/{landlordId}', [PropertyController::class, 'getByOwner']);
    Route::get('/properties/this-week', [PropertyController::class, 'getThisWeek']);
    Route::get('/properties/this-month', [PropertyController::class, 'getThisMonth']);
    Route::get('/properties/this-year', [PropertyController::class, 'getThisYear']);
    Route::get('/properties/{id}', [PropertyController::class, 'show']);
    Route::put('/properties/{id}', [PropertyController::class, 'update']);
    Route::delete('/properties/{id}', [PropertyController::class, 'destroy']);
    
    Route::get('/repairs', [RepairController::class, 'index']);
    Route::post('/repairs', [RepairController::class, 'store']);
    Route::get('/repairs/stats', [RepairController::class, 'getStats']);
    Route::get('/repairs/status/{status}', [RepairController::class, 'getByStatus']);
    Route::get('/repairs/type/{type}', [RepairController::class, 'getByType']);
    Route::get('/repairs/priority/{priority}', [RepairController::class, 'getByPriority']);
    Route::get('/repairs/user/{userId}', [RepairController::class, 'getByUser']);
    Route::get('/repairs/date-range', [RepairController::class, 'getByDateRange']);
    Route::get('/repairs/{id}', [RepairController::class, 'show']);
    Route::put('/repairs/{id}', [RepairController::class, 'update']);
    Route::delete('/repairs/{id}', [RepairController::class, 'destroy']);
    
    Route::get('/staff', [StaffController::class, 'index']);
    Route::post('/staff', [StaffController::class, 'store']);
    Route::get('/staff/stats', [StaffController::class, 'getStats']);
    Route::get('/staff/active', [StaffController::class, 'getActive']);
    Route::get('/staff/search', [StaffController::class, 'search']);
    Route::get('/staff/hierarchy', [StaffController::class, 'getManagersHierarchy']);
    Route::get('/staff/cx', [StaffController::class, 'getCXStaff']);
    Route::get('/staff/cx/{id}', [StaffController::class, 'getCXStaff']);
    Route::get('/staff/cx-dashboard', [StaffController::class, 'getCXDashboard']);
    Route::get('/staff/tsr', [StaffController::class, 'getTSRStaff']);
    Route::get('/staff/tsr/{id}', [StaffController::class, 'getTSRStaff']);
    Route::get('/staff/tsr-dashboard', [StaffController::class, 'getTSRDashboard']);
    Route::get('/staff/tsr-workload', [StaffController::class, 'getTSRWorkload']);
    Route::get('/staff/role/{role}', [StaffController::class, 'getByRole']);
    Route::get('/staff/department/{department}', [StaffController::class, 'getByDepartment']);
    Route::get('/staff/{id}', [StaffController::class, 'show']);
    Route::put('/staff/{id}', [StaffController::class, 'update']);
    Route::delete('/staff/{id}', [StaffController::class, 'destroy']);
    
    Route::get('/tenants', [TenantController::class, 'index']);
    Route::get('/tenants/count', [TenantController::class, 'count']);
    Route::get('/tenants/stats', [TenantController::class, 'getStats']);
    Route::get('/tenants/search', [TenantController::class, 'search']);
    Route::get('/tenants/profile', [TenantController::class, 'getProfile']);
    Route::get('/tenants/profile/{id}', [TenantController::class, 'getProfile']);
    Route::get('/tenants/rental-info', [TenantController::class, 'getRentalInfo']);
    Route::get('/tenants/rental-info/{id}', [TenantController::class, 'getRentalInfo']);
    Route::get('/tenants/verification/{status}', [TenantController::class, 'getByVerificationStatus']);
    Route::get('/tenants/renewals/{days?}', [TenantController::class, 'getUpcomingRenewals']);
    Route::get('/tenants/this-week', [TenantController::class, 'getThisWeek']);
    Route::get('/tenants/this-month', [TenantController::class, 'getThisMonth']);
    Route::get('/tenants/this-year', [TenantController::class, 'getThisYear']);
    Route::put('/tenants/account-manager', [TenantController::class, 'updateAccountManager']);
    Route::get('/tenants/{id}', [TenantController::class, 'show']);
    
    Route::get('/account-managers', [AccountManagerController::class, 'index']);
    Route::get('/account-managers/search', [AccountManagerController::class, 'search']);
    Route::get('/account-managers/workload', [AccountManagerController::class, 'getWorkloadDistribution']);
    Route::get('/account-managers/unassigned-clients', [AccountManagerController::class, 'getUnassignedClients']);
    Route::post('/account-managers/assign', [AccountManagerController::class, 'assignManager']);
    Route::post('/account-managers/remove', [AccountManagerController::class, 'removeManager']);
    Route::post('/account-managers/bulk-assign', [AccountManagerController::class, 'bulkAssign']);
    Route::get('/account-managers/{id}', [AccountManagerController::class, 'show']);
    Route::get('/account-managers/{id}/performance', [AccountManagerController::class, 'getPerformanceStats']);
    
    Route::get('/transactions', [TransactionController::class, 'index']);
    Route::post('/transactions', [TransactionController::class, 'store']);
    Route::get('/transactions/count', [TransactionController::class, 'count']);
    Route::get('/transactions/stats', [TransactionController::class, 'getStats']);
    Route::get('/transactions/search', [TransactionController::class, 'search']);
    Route::get('/transactions/date-range', [TransactionController::class, 'getByDateRange']);
    Route::get('/transactions/status/{status}', [TransactionController::class, 'getByStatus']);
    Route::get('/transactions/type/{type}', [TransactionController::class, 'getByType']);
    Route::get('/transactions/user/{userId}', [TransactionController::class, 'getByUser']);
    Route::get('/transactions/this-week', [TransactionController::class, 'getThisWeek']);
    Route::get('/transactions/this-month', [TransactionController::class, 'getThisMonth']);
    Route::get('/transactions/this-year', [TransactionController::class, 'getThisYear']);
    Route::get('/transactions/{id}', [TransactionController::class, 'show']);
    Route::put('/transactions/{id}', [TransactionController::class, 'update']);
    
    Route::get('/verifications', [VerificationController::class, 'index']);
    Route::post('/verifications', [VerificationController::class, 'store']);
    Route::get('/verifications/count', [VerificationController::class, 'count']);
    Route::get('/verifications/stats', [VerificationController::class, 'getStats']);
    Route::get('/verifications/search', [VerificationController::class, 'search']);
    Route::get('/verifications/status/{status}', [VerificationController::class, 'getByStatus']);
    Route::get('/verifications/this-week', [VerificationController::class, 'getThisWeek']);
    Route::get('/verifications/this-month', [VerificationController::class, 'getThisMonth']);
    Route::get('/verifications/this-year', [VerificationController::class, 'getThisYear']);
    Route::post('/verifications/update-status', [VerificationController::class, 'updateStatus']);
    Route::get('/verifications/{id}', [VerificationController::class, 'show']);
    
    Route::post('/logout', [AuthController::class, 'logout']);
});





