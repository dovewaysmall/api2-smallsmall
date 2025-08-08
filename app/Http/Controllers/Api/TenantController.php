<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class TenantController extends Controller
{
    /**
     * Display a listing of all tenants (Improved version of your original).
     */
    public function index()
    {
        try {
            $tenants = DB::table('user_tbl')
                ->join('bookings', DB::raw('CAST(user_tbl.userID AS CHAR)'), '=', DB::raw('CAST(bookings.userID AS CHAR)'))
                ->leftJoin('user_tbl as manager', DB::raw('CAST(user_tbl.account_manager AS CHAR)'), '=', DB::raw('CAST(manager.userID AS CHAR)'))
                ->select(
                    'user_tbl.userID',
                    'user_tbl.firstName',
                    'user_tbl.lastName',
                    'user_tbl.email',
                    'user_tbl.phone',
                    'user_tbl.verified',
                    'user_tbl.user_type',
                    'user_tbl.profile_picture',
                    'user_tbl.country',
                    'user_tbl.regDate',
                    'user_tbl.account_manager',
                    'manager.firstName as manager_firstName',
                    'manager.lastName as manager_lastName'
                )
                ->distinct()
                ->orderBy('user_tbl.regDate', 'desc')
                ->get();

            return response()->json([
                'success' => true,
                'message' => 'Tenants retrieved successfully',
                'data' => $tenants,
                'count' => $tenants->count()
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'An error occurred while retrieving tenants',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Display the specified tenant (Improved version of your original).
     */
    public function show(string $id)
    {
        try {
            $tenant = DB::table('user_tbl')
                ->join('bookings', DB::raw('CAST(user_tbl.userID AS CHAR)'), '=', DB::raw('CAST(bookings.userID AS CHAR)'))
                ->leftJoin('user_tbl as manager', DB::raw('CAST(user_tbl.account_manager AS CHAR)'), '=', DB::raw('CAST(manager.userID AS CHAR)'))
                ->select(
                    'user_tbl.*',
                    'manager.firstName as manager_firstName',
                    'manager.lastName as manager_lastName',
                    'manager.email as manager_email'
                )
                ->where('user_tbl.userID', $id)
                ->first();

            if (!$tenant) {
                return response()->json([
                    'success' => false,
                    'message' => 'Tenant not found'
                ], 404);
            }

            // Get tenant's bookings
            $bookings = DB::table('bookings')
                ->leftJoin('property_tbl', DB::raw('CAST(bookings.propertyID AS CHAR)'), '=', DB::raw('CAST(property_tbl.propertyID AS CHAR)'))
                ->select('bookings.*', 'property_tbl.propertyTitle', 'property_tbl.address as property_address')
                ->where('bookings.userID', $id)
                ->get();

            // Get tenant's inspections
            $inspections = DB::table('inspection_tbl')
                ->leftJoin('property_tbl', DB::raw('CAST(inspection_tbl.propertyID AS CHAR)'), '=', DB::raw('CAST(property_tbl.propertyID AS CHAR)'))
                ->select('inspection_tbl.*', 'property_tbl.propertyTitle')
                ->where('inspection_tbl.userID', $id)
                ->get();

            return response()->json([
                'success' => true,
                'message' => 'Tenant retrieved successfully',
                'data' => [
                    'tenant_info' => $tenant,
                    'bookings' => $bookings,
                    'inspections' => $inspections,
                    'bookings_count' => $bookings->count(),
                    'inspections_count' => $inspections->count()
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'An error occurred while retrieving tenant',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get tenant profile with properties (Improved version of your original).
     */
    public function getProfile($id = null)
    {
        try {
            $query = DB::table('user_tbl')
                ->join('bookings', DB::raw('CAST(user_tbl.userID AS CHAR)'), '=', DB::raw('CAST(bookings.userID AS CHAR)'))
                ->leftJoin('inspection_tbl', DB::raw('CAST(user_tbl.userID AS CHAR)'), '=', DB::raw('CAST(inspection_tbl.userID AS CHAR)'))
                ->leftJoin('property_tbl', DB::raw('CAST(inspection_tbl.propertyID AS CHAR)'), '=', DB::raw('CAST(property_tbl.propertyID AS CHAR)'))
                ->leftJoin('user_tbl as manager', DB::raw('CAST(user_tbl.account_manager AS CHAR)'), '=', DB::raw('CAST(manager.userID AS CHAR)'))
                ->select(
                    'user_tbl.userID',
                    'user_tbl.firstName',
                    'user_tbl.lastName',
                    'user_tbl.verified',
                    'user_tbl.email',
                    'user_tbl.phone',
                    'user_tbl.profile_picture',
                    'user_tbl.country',
                    'user_tbl.regDate',
                    'user_tbl.account_manager',
                    'manager.firstName as manager_firstName',
                    'manager.lastName as manager_lastName',
                    'inspection_tbl.inspectionID',
                    'inspection_tbl.inspectionDate',
                    'inspection_tbl.propertyID',
                    'inspection_tbl.inspectionType',
                    'inspection_tbl.inspection_status',
                    'property_tbl.propertyTitle',
                    'property_tbl.address as property_address',
                    'property_tbl.city',
                    'property_tbl.state as property_state'
                );

            if ($id) {
                $tenantProfiles = $query->where('user_tbl.userID', $id)->get();
                
                if ($tenantProfiles->isEmpty()) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Tenant profile not found'
                    ], 404);
                }

                // Group data by tenant
                $tenantInfo = $tenantProfiles->first();
                $properties = $tenantProfiles->whereNotNull('propertyID')->unique('propertyID')->values();

                return response()->json([
                    'success' => true,
                    'message' => 'Tenant profile retrieved successfully',
                    'data' => [
                        'tenant_info' => [
                            'userID' => $tenantInfo->userID,
                            'firstName' => $tenantInfo->firstName,
                            'lastName' => $tenantInfo->lastName,
                            'verified' => $tenantInfo->verified,
                            'email' => $tenantInfo->email,
                            'phone' => $tenantInfo->phone,
                            'profile_picture' => $tenantInfo->profile_picture,
                            'country' => $tenantInfo->country,
                            'regDate' => $tenantInfo->regDate,
                            'account_manager' => $tenantInfo->account_manager,
                            'manager_name' => $tenantInfo->manager_firstName . ' ' . $tenantInfo->manager_lastName
                        ],
                        'inspected_properties' => $properties,
                        'properties_count' => $properties->count()
                    ]
                ]);
            } else {
                $tenantProfiles = $query->orderBy('user_tbl.firstName', 'asc')->get();

                return response()->json([
                    'success' => true,
                    'message' => 'Tenant profiles retrieved successfully',
                    'data' => $tenantProfiles,
                    'count' => $tenantProfiles->count()
                ]);
            }

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'An error occurred while retrieving tenant profiles',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get tenant rental information (Improved version of your original).
     */
    public function getRentalInfo($id = null)
    {
        try {
            $query = DB::table('user_tbl')
                ->join('bookings', DB::raw('CAST(user_tbl.userID AS CHAR)'), '=', DB::raw('CAST(bookings.userID AS CHAR)'))
                ->leftJoin('property_tbl', DB::raw('CAST(bookings.propertyID AS CHAR)'), '=', DB::raw('CAST(property_tbl.propertyID AS CHAR)'))
                ->select(
                    'user_tbl.userID',
                    'user_tbl.firstName',
                    'user_tbl.lastName',
                    'user_tbl.email',
                    'user_tbl.phone',
                    'bookings.bookingID',
                    'bookings.propertyID',
                    'bookings.move_in_date',
                    'bookings.booked_as',
                    'bookings.next_rental',
                    'bookings.rent_expiration',
                    'bookings.rent_status',
                    'bookings.total',
                    'bookings.payment_frequency',
                    'bookings.booking_date',
                    'bookings.booking_status',
                    'property_tbl.propertyTitle',
                    'property_tbl.address as property_address',
                    'property_tbl.city',
                    'property_tbl.state as property_state'
                )
;

            if ($id) {
                $rentalInfo = $query->where('bookings.userID', $id)
                    ->orderBy('bookings.booking_date', 'desc')
                    ->get();

                if ($rentalInfo->isEmpty()) {
                    return response()->json([
                        'success' => true,
                        'message' => 'No rental information found for this tenant',
                        'data' => [],
                        'count' => 0
                    ]);
                }

                // Calculate rental statistics
                $stats = [
                    'total_bookings' => $rentalInfo->count(),
                    'active_rentals' => $rentalInfo->where('rent_status', 'active')->count(),
                    'expired_rentals' => $rentalInfo->where('rent_status', 'expired')->count(),
                    'total_rent_amount' => $rentalInfo->where('rent_status', 'active')->sum('total'),
                    'upcoming_renewals' => $rentalInfo->where('rent_expiration', '>=', now())
                        ->where('rent_expiration', '<=', now()->addDays(30))->count()
                ];

                return response()->json([
                    'success' => true,
                    'message' => 'Tenant rental information retrieved successfully',
                    'data' => $rentalInfo,
                    'count' => $rentalInfo->count(),
                    'rental_statistics' => $stats
                ]);
            } else {
                $rentalInfo = $query->orderBy('bookings.booking_date', 'desc')->get();

                return response()->json([
                    'success' => true,
                    'message' => 'All tenant rental information retrieved successfully',
                    'data' => $rentalInfo,
                    'count' => $rentalInfo->count()
                ]);
            }

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'An error occurred while retrieving rental information',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update tenant account manager (Improved version of your original).
     */
    public function updateAccountManager(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'userID' => 'required|string|exists:user_tbl,userID',
            'account_manager' => 'required|string|exists:user_tbl,userID',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation error',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            // Check if the tenant exists (user with bookings)
            $tenant = DB::table('user_tbl')
                ->join('bookings', DB::raw('CAST(user_tbl.userID AS CHAR)'), '=', DB::raw('CAST(bookings.userID AS CHAR)'))
                ->where('user_tbl.userID', $request->userID)
                ->first();

            if (!$tenant) {
                return response()->json([
                    'success' => false,
                    'message' => 'Tenant not found'
                ], 404);
            }

            // Update account manager
            $updated = DB::table('user_tbl')
                ->where('userID', $request->userID)
                ->update([
                    'account_manager' => $request->account_manager,
                    'updated_at' => now()
                ]);

            if ($updated) {
                // Get updated tenant info with manager details
                $updatedTenant = DB::table('user_tbl')
                    ->leftJoin('user_tbl as manager', DB::raw('CAST(user_tbl.account_manager AS CHAR)'), '=', DB::raw('CAST(manager.userID AS CHAR)'))
                    ->select(
                        'user_tbl.userID',
                        'user_tbl.firstName',
                        'user_tbl.lastName',
                        'user_tbl.email',
                        'user_tbl.account_manager',
                        'manager.firstName as manager_firstName',
                        'manager.lastName as manager_lastName',
                        'manager.email as manager_email'
                    )
                    ->where('user_tbl.userID', $request->userID)
                    ->first();

                return response()->json([
                    'success' => true,
                    'message' => 'Account manager updated successfully',
                    'data' => $updatedTenant
                ]);
            } else {
                return response()->json([
                    'success' => false,
                    'message' => 'Failed to update account manager'
                ], 500);
            }

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'An error occurred while updating account manager',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get tenants by verification status.
     */
    public function getByVerificationStatus($status)
    {
        $validator = Validator::make(['status' => $status], [
            'status' => 'required|in:0,1,verified,unverified'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid verification status. Must be: 0, 1, verified, or unverified',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            // Convert string status to numeric
            if ($status === 'verified') $status = 1;
            if ($status === 'unverified') $status = 0;

            $tenants = DB::table('user_tbl')
                ->join('bookings', DB::raw('CAST(user_tbl.userID AS CHAR)'), '=', DB::raw('CAST(bookings.userID AS CHAR)'))
                ->leftJoin('user_tbl as manager', DB::raw('CAST(user_tbl.account_manager AS CHAR)'), '=', DB::raw('CAST(manager.userID AS CHAR)'))
                ->select(
                    'user_tbl.userID',
                    'user_tbl.firstName',
                    'user_tbl.lastName',
                    'user_tbl.email',
                    'user_tbl.phone',
                    'user_tbl.verified',
                    'user_tbl.regDate',
                    'user_tbl.account_manager',
                    'manager.firstName as manager_firstName',
                    'manager.lastName as manager_lastName'
                )
                ->where('user_tbl.verified', $status)
                ->distinct()
                ->orderBy('user_tbl.regDate', 'desc')
                ->get();

            return response()->json([
                'success' => true,
                'message' => 'Tenants retrieved successfully',
                'data' => $tenants,
                'count' => $tenants->count(),
                'verification_status' => $status == 1 ? 'verified' : 'unverified'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'An error occurred while retrieving tenants',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Search tenants.
     */
    public function search(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'query' => 'required|string|min:2|max:100',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation error',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $query = $request->input('query');
            $tenants = DB::table('user_tbl')
                ->join('bookings', DB::raw('CAST(user_tbl.userID AS CHAR)'), '=', DB::raw('CAST(bookings.userID AS CHAR)'))
                ->leftJoin('user_tbl as manager', DB::raw('CAST(user_tbl.account_manager AS CHAR)'), '=', DB::raw('CAST(manager.userID AS CHAR)'))
                ->select(
                    'user_tbl.userID',
                    'user_tbl.firstName',
                    'user_tbl.lastName',
                    'user_tbl.email',
                    'user_tbl.phone',
                    'user_tbl.verified',
                    'user_tbl.regDate',
                    'user_tbl.account_manager',
                    'manager.firstName as manager_firstName',
                    'manager.lastName as manager_lastName'
                )
                ->where(function($q) use ($query) {
                    $q->where('user_tbl.firstName', 'LIKE', "%{$query}%")
                      ->orWhere('user_tbl.lastName', 'LIKE', "%{$query}%")
                      ->orWhere('user_tbl.email', 'LIKE', "%{$query}%")
                      ->orWhere('user_tbl.phone', 'LIKE', "%{$query}%");
                })
                ->distinct()
                ->orderBy('user_tbl.firstName', 'asc')
                ->get();

            return response()->json([
                'success' => true,
                'message' => 'Tenant search results',
                'data' => $tenants,
                'count' => $tenants->count(),
                'search_query' => $query
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'An error occurred while searching tenants',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get tenant count.
     */
    public function count()
    {
        try {
            // Count distinct users who have bookings (actual tenants)
            $totalCount = DB::table('bookings')
                ->distinct()
                ->count('userID');
            
            return response()->json([
                'success' => true,
                'message' => 'Tenant count retrieved successfully',
                'count' => $totalCount
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'An error occurred while retrieving tenant count',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get tenant statistics.
     */
    public function getStats()
    {
        try {
            $totalTenants = DB::table('bookings')->distinct()->count('userID');
            
            $verifiedTenants = DB::table('user_tbl')
                ->join('bookings', DB::raw('CAST(user_tbl.userID AS CHAR)'), '=', DB::raw('CAST(bookings.userID AS CHAR)'))
                ->where('user_tbl.verified', 1)
                ->distinct()
                ->count('user_tbl.userID');
            
            $tenantsWithManager = DB::table('user_tbl')
                ->join('bookings', DB::raw('CAST(user_tbl.userID AS CHAR)'), '=', DB::raw('CAST(bookings.userID AS CHAR)'))
                ->whereNotNull('user_tbl.account_manager')
                ->distinct()
                ->count('user_tbl.userID');

            $activeRentals = DB::table('bookings')
                ->where('rent_status', 'active')
                ->count();

            $recentTenants = DB::table('user_tbl')
                ->join('bookings', DB::raw('CAST(user_tbl.userID AS CHAR)'), '=', DB::raw('CAST(bookings.userID AS CHAR)'))
                ->where('user_tbl.regDate', '>=', now()->subDays(30))
                ->distinct()
                ->count('user_tbl.userID');

            $countryStats = DB::table('user_tbl')
                ->join('bookings', DB::raw('CAST(user_tbl.userID AS CHAR)'), '=', DB::raw('CAST(bookings.userID AS CHAR)'))
                ->select('user_tbl.country', DB::raw('count(distinct user_tbl.userID) as count'))
                ->whereNotNull('user_tbl.country')
                ->groupBy('user_tbl.country')
                ->orderBy('count', 'desc')
                ->limit(10)
                ->get();

            $totalRentRevenue = DB::table('bookings')
                ->where('rent_status', 'active')
                ->sum('total');

            return response()->json([
                'success' => true,
                'message' => 'Tenant statistics retrieved successfully',
                'total_tenants' => $totalTenants,
                'verified_tenants' => $verifiedTenants,
                'verification_rate' => $totalTenants > 0 ? round(($verifiedTenants / $totalTenants) * 100, 2) : 0,
                'tenants_with_manager' => $tenantsWithManager,
                'active_rentals' => $activeRentals,
                'recent_tenants_30_days' => $recentTenants,
                'total_rent_revenue' => round($totalRentRevenue, 2),
                'country_breakdown' => $countryStats
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'An error occurred while retrieving tenant statistics',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get tenants with upcoming rent renewals.
     */
    public function getUpcomingRenewals($days = 30)
    {
        try {
            $upcomingRenewals = DB::table('user_tbl')
                ->join('bookings', DB::raw('CAST(user_tbl.userID AS CHAR)'), '=', DB::raw('CAST(bookings.userID AS CHAR)'))
                ->leftJoin('property_tbl', DB::raw('CAST(bookings.propertyID AS CHAR)'), '=', DB::raw('CAST(property_tbl.propertyID AS CHAR)'))
                ->select(
                    'user_tbl.userID',
                    'user_tbl.firstName',
                    'user_tbl.lastName',
                    'user_tbl.email',
                    'user_tbl.phone',
                    'bookings.bookingID',
                    'bookings.rent_expiration',
                    'bookings.next_rental',
                    'bookings.total',
                    'bookings.rent_status',
                    'property_tbl.propertyTitle',
                    'property_tbl.address as property_address'
                )
                ->where('bookings.rent_status', 'active')
                ->whereBetween('bookings.rent_expiration', [now(), now()->addDays($days)])
                ->orderBy('bookings.rent_expiration', 'asc')
                ->get();

            return response()->json([
                'success' => true,
                'message' => "Upcoming rent renewals within {$days} days retrieved successfully",
                'data' => $upcomingRenewals,
                'count' => $upcomingRenewals->count(),
                'total_renewal_amount' => $upcomingRenewals->sum('total'),
                'days_ahead' => $days
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'An error occurred while retrieving upcoming renewals',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}