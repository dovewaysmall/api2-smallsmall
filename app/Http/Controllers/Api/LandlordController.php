<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class LandlordController extends Controller
{
    /**
     * Display a listing of all landlords (Improved version of your original).
     */
    public function index()
    {
        try {
            // Get all landlords first
            $landlords = DB::table('user_tbl')
                ->where('user_type', 'landlord')
                ->select(
                    'userID',
                    'firstName', 
                    'lastName',
                    'email',
                    'phone',
                    'verified',
                    'user_type',
                    'boarding_status'
                )
                ->orderBy('userID', 'desc')
                ->get();

            // Loop through each landlord and count their properties and tenants
            $landlordsWithCounts = $landlords->map(function ($landlord) {
                // Count properties owned by this landlord
                $propertyCount = DB::table('property_tbl')
                    ->where('property_owner', $landlord->userID)
                    ->count();
                
                // Count tenants for this landlord (through property bookings)
                $tenantCount = DB::table('bookings')
                    ->join('property_tbl', 'bookings.propertyID', '=', 'property_tbl.propertyID')
                    ->where('property_tbl.property_owner', $landlord->userID)
                    ->distinct('bookings.userID')
                    ->count('bookings.userID');
                
                $landlord->property_count = $propertyCount;
                $landlord->tenant_count = $tenantCount;
                return $landlord;
            });

            return response()->json([
                'success' => true,
                'message' => 'Landlords retrieved successfully',
                'data' => $landlordsWithCounts,
                'count' => $landlordsWithCounts->count()
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'An error occurred while retrieving landlords',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Display the specified landlord.
     */
    public function show(string $id)
    {
        try {
            $landlord = DB::table('user_tbl')
                ->where('userID', $id)
                ->where('user_type', 'landlord')
                ->first();

            if (!$landlord) {
                return response()->json([
                    'success' => false,
                    'message' => 'Landlord not found'
                ], 404);
            }

            // Get landlord's properties
            $properties = DB::table('property_tbl')
                ->where('property_owner', $id)
                ->select('propertyID', 'propertyTitle', 'address', 'price', 'propertyType', 'status')
                ->get();

            // Get landlord's tenant count
            $tenantCount = DB::table('bookings')
                ->join('property_tbl', 'bookings.propertyID', '=', 'property_tbl.propertyID')
                ->where('property_tbl.property_owner', $id)
                ->distinct('bookings.userID')
                ->count('bookings.userID');

            return response()->json([
                'success' => true,
                'message' => 'Landlord retrieved successfully',
                'data' => [
                    'landlord_info' => $landlord,
                    'properties' => $properties,
                    'property_count' => $properties->count(),
                    'tenant_count' => $tenantCount
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'An error occurred while retrieving landlord',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Store a newly created landlord (Improved version of your original).
     */
    public function store(Request $request)
    {
        // Enhanced validation rules
        $validator = Validator::make($request->all(), [
            'firstName' => 'required|string|max:255',
            'lastName' => 'required|string|max:255',
            'email' => 'required|email|unique:user_tbl,email|max:255',
            'phone' => 'required|string|max:20',
            'password' => 'required|string|min:8|max:255',
            'income' => 'required|string|max:20',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation error',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            // Generate random 12-digit userID
            do {
                $userID = str_pad(random_int(100000000000, 999999999999), 12, '0', STR_PAD_LEFT);
            } while (User::where('userID', $userID)->exists());

            // Generate a unique id for the record
            do {
                $id = random_int(100000, 999999);
            } while (User::where('id', $id)->exists());

            // Use Eloquent model to create landlord
            $landlord = User::create([
                'id' => $id,
                'userID' => $userID,
                'firstName' => $request->firstName,
                'lastName' => $request->lastName,
                'email' => $request->email,
                'phone' => $request->phone,
                'password' => bcrypt($request->password),
                'user_type' => 'landlord',
                'verified' => 0,
                'referral' => '',
                'status' => 'active',
                'boarding_status' => 'not yet boarded',
                'profile_picture' => '',
                'interest' => '',
                'regDate' => now(),
                'income' => $request->income,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Landlord created successfully',
                'data' => [
                    'userID' => $landlord->userID,
                    'firstName' => $landlord->firstName,
                    'lastName' => $landlord->lastName,
                    'email' => $landlord->email,
                    'phone' => $landlord->phone,
                    'user_type' => $landlord->user_type,
                    'verified' => $landlord->verified
                ],
                'id' => $landlord->userID
            ], 201);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'An error occurred while creating landlord',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update the specified landlord.
     */
    public function update(Request $request, string $id)
    {
        $landlord = DB::table('user_tbl')
            ->where('userID', $id)
            ->where('user_type', 'landlord')
            ->first();

        if (!$landlord) {
            return response()->json([
                'success' => false,
                'message' => 'Landlord not found'
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'firstName' => 'sometimes|string|max:255',
            'lastName' => 'sometimes|string|max:255',
            'email' => 'sometimes|email|unique:user_tbl,email,' . $id . ',userID|max:255',
            'phone' => 'sometimes|string|max:20',
            'verified' => 'sometimes|boolean',
            'boarding_status' => 'sometimes|in:not yet boarded,onboarded,offboarded',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation error',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $updateData = $request->only([
                'firstName', 'lastName', 'email', 'phone', 
                'verified', 'boarding_status'
            ]);


            DB::table('user_tbl')->where('userID', $id)->update($updateData);
            
            $updatedLandlord = DB::table('user_tbl')
                ->where('userID', $id)
                ->select('userID', 'firstName', 'lastName', 'email', 'phone', 'user_type',
                         'verified', 'boarding_status')
                ->first();

            return response()->json([
                'success' => true,
                'message' => 'Landlord updated successfully',
                'data' => $updatedLandlord
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'An error occurred while updating landlord',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Remove the specified landlord.
     */
    public function destroy(string $id)
    {
        $landlord = DB::table('user_tbl')
            ->where('userID', $id)
            ->where('user_type', 'landlord')
            ->first();

        if (!$landlord) {
            return response()->json([
                'success' => false,
                'message' => 'Landlord not found'
            ], 404);
        }

        try {

            DB::table('user_tbl')->where('userID', $id)->delete();

            return response()->json([
                'success' => true,
                'message' => 'Landlord deleted successfully'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'An error occurred while deleting landlord',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get landlords count (Improved version of your original).
     */
    public function count()
    {
        try {
            $totalCount = DB::table('user_tbl')->where('user_type', 'landlord')->count();
            $verifiedCount = DB::table('user_tbl')
                ->where('user_type', 'landlord')
                ->where('verified', 1)
                ->count();
            $unverifiedCount = DB::table('user_tbl')
                ->where('user_type', 'landlord')
                ->where('verified', 0)
                ->count();
            
            // Recent landlords (last 30 days)
            $recentCount = DB::table('user_tbl')
                ->where('user_type', 'landlord')
                ->where('userID', '>', 0)
                ->count();

            return response()->json([
                'success' => true,
                'message' => 'Landlord count retrieved successfully',
                'total_landlords' => $totalCount,
                'verified_landlords' => $verifiedCount,
                'unverified_landlords' => $unverifiedCount,
                'recent_landlords_30_days' => $recentCount
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'An error occurred while retrieving landlord count',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get verified landlords.
     */
    public function getVerified()
    {
        try {
            $landlords = DB::table('user_tbl')
                ->where('user_type', 'landlord')
                ->where('verified', 1)
                ->select('userID', 'firstName', 'lastName', 'email', 'phone', 
                         'boarding_status')
                ->orderBy('userID', 'desc')
                ->get();

            return response()->json([
                'success' => true,
                'message' => 'Verified landlords retrieved successfully',
                'data' => $landlords,
                'count' => $landlords->count()
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'An error occurred while retrieving verified landlords',
                'error' => $e->getMessage()
            ], 500);
        }
    }


    /**
     * Search landlords by name or email.
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
            $landlords = DB::table('user_tbl')
                ->where('user_type', 'landlord')
                ->where(function($q) use ($query) {
                    $q->where('firstName', 'LIKE', "%{$query}%")
                      ->orWhere('lastName', 'LIKE', "%{$query}%")
                      ->orWhere('email', 'LIKE', "%{$query}%");
                })
                ->select('userID', 'firstName', 'lastName', 'email', 'phone', 'verified',
                         'boarding_status')
                ->orderBy('userID', 'desc')
                ->get();

            return response()->json([
                'success' => true,
                'message' => 'Landlord search results',
                'data' => $landlords,
                'count' => $landlords->count(),
                'search_query' => $query
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'An error occurred while searching landlords',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get landlord statistics.
     */
    public function getStats()
    {
        try {
            $totalLandlords = DB::table('user_tbl')->where('user_type', 'landlord')->count();
            $verifiedLandlords = DB::table('user_tbl')
                ->where('user_type', 'landlord')
                ->where('verified', 1)
                ->count();
            

            $recentLandlords = DB::table('user_tbl')
                ->where('user_type', 'landlord')
                ->where('userID', '>', 0)
                ->count();

            // Properties owned by landlords
            $totalProperties = DB::table('property_tbl')
                ->whereIn('poster', function($query) {
                    $query->select('userID')
                          ->from('user_tbl')
                          ->where('user_type', 'landlord');
                })
                ->count();

            return response()->json([
                'success' => true,
                'message' => 'Landlord statistics retrieved successfully',
                'total_landlords' => $totalLandlords,
                'verified_landlords' => $verifiedLandlords,
                'verification_rate' => $totalLandlords > 0 ? round(($verifiedLandlords / $totalLandlords) * 100, 2) : 0,
                'total_landlords_count' => $recentLandlords,
                'total_properties_owned' => $totalProperties
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'An error occurred while retrieving landlord statistics',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get landlords created this week.
     */
    public function getThisWeek()
    {
        try {
            $startOfWeek = now()->startOfWeek();
            $endOfWeek = now()->endOfWeek();

            // Get all landlords created this week
            $landlords = DB::table('user_tbl')
                ->where('user_type', 'landlord')
                ->whereDate('regDate', '>=', $startOfWeek)
                ->whereDate('regDate', '<=', $endOfWeek)
                ->select(
                    'userID',
                    'firstName', 
                    'lastName',
                    'email',
                    'phone',
                    'verified',
                    'user_type',
                    'boarding_status',
                    'regDate'
                )
                ->orderBy('userID', 'desc')
                ->get();

            // Loop through each landlord and count their properties using property_owner field
            $landlordsWithPropertyCount = $landlords->map(function ($landlord) {
                $propertyCount = DB::table('property_tbl')
                    ->where('property_owner', $landlord->userID)
                    ->count();
                
                $landlord->property_count = $propertyCount;
                return $landlord;
            });

            return response()->json([
                'success' => true,
                'message' => 'Landlords retrieved successfully',
                'data' => $landlordsWithPropertyCount,
                'count' => $landlordsWithPropertyCount->count()
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'An error occurred while retrieving landlords',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get landlords created this month.
     */
    public function getThisMonth()
    {
        try {
            $startOfMonth = now()->startOfMonth();
            $endOfMonth = now()->endOfMonth();

            // Get all landlords created this month
            $landlords = DB::table('user_tbl')
                ->where('user_type', 'landlord')
                ->whereDate('regDate', '>=', $startOfMonth)
                ->whereDate('regDate', '<=', $endOfMonth)
                ->select(
                    'userID',
                    'firstName', 
                    'lastName',
                    'email',
                    'phone',
                    'verified',
                    'user_type',
                    'boarding_status'
                )
                ->orderBy('userID', 'desc')
                ->get();

            // Loop through each landlord and count their properties using property_owner field
            $landlordsWithPropertyCount = $landlords->map(function ($landlord) {
                $propertyCount = DB::table('property_tbl')
                    ->where('property_owner', $landlord->userID)
                    ->count();
                
                $landlord->property_count = $propertyCount;
                return $landlord;
            });

            return response()->json([
                'success' => true,
                'message' => 'Landlords retrieved successfully',
                'data' => $landlordsWithPropertyCount,
                'count' => $landlordsWithPropertyCount->count()
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'An error occurred while retrieving landlords',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get landlords created this year.
     */
    public function getThisYear()
    {
        try {
            $startOfYear = now()->startOfYear();
            $endOfYear = now()->endOfYear();

            // Get all landlords created this year
            $landlords = DB::table('user_tbl')
                ->where('user_type', 'landlord')
                ->whereDate('regDate', '>=', $startOfYear)
                ->whereDate('regDate', '<=', $endOfYear)
                ->select(
                    'userID',
                    'firstName', 
                    'lastName',
                    'email',
                    'phone',
                    'verified',
                    'user_type',
                    'boarding_status'
                )
                ->orderBy('userID', 'desc')
                ->get();

            // Loop through each landlord and count their properties using property_owner field
            $landlordsWithPropertyCount = $landlords->map(function ($landlord) {
                $propertyCount = DB::table('property_tbl')
                    ->where('property_owner', $landlord->userID)
                    ->count();
                
                $landlord->property_count = $propertyCount;
                return $landlord;
            });

            return response()->json([
                'success' => true,
                'message' => 'Landlords retrieved successfully',
                'data' => $landlordsWithPropertyCount,
                'count' => $landlordsWithPropertyCount->count()
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'An error occurred while retrieving landlords',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get landlords by boarding status.
     */
    public function getByBoardingStatus(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'boarding_status' => 'required|in:not yet boarded,onboarded,offboarded',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation error',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $boardingStatus = $request->input('boarding_status');
            $landlords = DB::table('user_tbl')
                ->where('user_type', 'landlord')
                ->where('boarding_status', $boardingStatus)
                ->select(
                    'userID',
                    'firstName', 
                    'lastName',
                    'email',
                    'phone',
                    'verified',
                    'user_type',
                    'boarding_status'
                )
                ->orderBy('userID', 'desc')
                ->get();

            // Loop through each landlord and count their properties
            $landlordsWithCounts = $landlords->map(function ($landlord) {
                $propertyCount = DB::table('property_tbl')
                    ->where('property_owner', $landlord->userID)
                    ->count();
                
                $tenantCount = DB::table('bookings')
                    ->join('property_tbl', 'bookings.propertyID', '=', 'property_tbl.propertyID')
                    ->where('property_tbl.property_owner', $landlord->userID)
                    ->distinct('bookings.userID')
                    ->count('bookings.userID');
                
                $landlord->property_count = $propertyCount;
                $landlord->tenant_count = $tenantCount;
                return $landlord;
            });

            return response()->json([
                'success' => true,
                'message' => "Landlords with boarding status '{$boardingStatus}' retrieved successfully",
                'data' => $landlordsWithCounts,
                'count' => $landlordsWithCounts->count(),
                'boarding_status' => $boardingStatus
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'An error occurred while retrieving landlords by boarding status',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}