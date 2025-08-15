<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
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
                    'user_type'
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
                ->where('poster', $id)
                ->select('propertyID', 'propertyTitle', 'address', 'price', 'propertyType', 'status')
                ->get();

            return response()->json([
                'success' => true,
                'message' => 'Landlord retrieved successfully',
                'data' => [
                    'landlord_info' => $landlord,
                    'properties' => $properties,
                    'property_count' => $properties->count()
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
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation error',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            // Prepare data for insertion
            $data = [
                'firstName' => $request->firstName,
                'lastName' => $request->lastName,
                'email' => $request->email,
                'phone' => $request->phone,
                'password' => bcrypt($request->password),
                'user_type' => 'landlord',
                'verified' => 0,
            ];

            // Insert landlord and get ID
            $landlordId = DB::table('user_tbl')->insertGetId($data);

            if ($landlordId) {
                // Retrieve the created landlord (excluding password)
                $createdLandlord = DB::table('user_tbl')
                    ->where('userID', $landlordId)
                    ->select('userID', 'firstName', 'lastName', 'email', 'phone', 'user_type', 
                             'verified')
                    ->first();

                return response()->json([
                    'success' => true,
                    'message' => 'Landlord created successfully',
                    'data' => $createdLandlord,
                    'id' => $landlordId
                ], 201);
            } else {
                return response()->json([
                    'success' => false,
                    'message' => 'Failed to create landlord'
                ], 500);
            }

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
                'verified'
            ]);


            DB::table('user_tbl')->where('userID', $id)->update($updateData);
            
            $updatedLandlord = DB::table('user_tbl')
                ->where('userID', $id)
                ->select('userID', 'firstName', 'lastName', 'email', 'phone', 'user_type',
                         'verified')
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
                         'userID')
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
                         'userID')
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

            // Get landlords created this week
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
                    'regDate'
                )
                ->orderBy('userID', 'desc')
                ->get();

            // Add property count for each landlord
            $landlordsWithPropertyCount = $landlords->map(function ($landlord) {
                $propertyCount = DB::table('property_tbl')
                    ->where('property_owner', $landlord->userID)
                    ->count();
                
                $landlord->property_count = $propertyCount;
                return $landlord;
            });

            $weekStats = [
                'total_landlords' => $landlordsWithPropertyCount->count(),
                'verified_landlords' => $landlordsWithPropertyCount->where('verified', 1)->count(),
                'unverified_landlords' => $landlordsWithPropertyCount->where('verified', 0)->count(),
                'total_properties_owned' => $landlordsWithPropertyCount->sum('property_count')
            ];

            return response()->json([
                'success' => true,
                'message' => 'Landlords for this week retrieved successfully',
                'data' => $landlordsWithPropertyCount,
                'count' => $landlordsWithPropertyCount->count(),
                'period' => [
                    'start' => $startOfWeek->format('Y-m-d H:i:s'),
                    'end' => $endOfWeek->format('Y-m-d H:i:s')
                ],
                'week_statistics' => $weekStats
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'An error occurred while retrieving landlords for this week',
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

            // Get landlords created this month
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
                    'regDate'
                )
                ->orderBy('userID', 'desc')
                ->get();

            // Add property count for each landlord
            $landlordsWithPropertyCount = $landlords->map(function ($landlord) {
                $propertyCount = DB::table('property_tbl')
                    ->where('property_owner', $landlord->userID)
                    ->count();
                
                $landlord->property_count = $propertyCount;
                return $landlord;
            });

            $monthStats = [
                'total_landlords' => $landlordsWithPropertyCount->count(),
                'verified_landlords' => $landlordsWithPropertyCount->where('verified', 1)->count(),
                'unverified_landlords' => $landlordsWithPropertyCount->where('verified', 0)->count(),
                'total_properties_owned' => $landlordsWithPropertyCount->sum('property_count'),
                'average_properties_per_landlord' => $landlordsWithPropertyCount->count() > 0 ? 
                    round($landlordsWithPropertyCount->sum('property_count') / $landlordsWithPropertyCount->count(), 2) : 0
            ];

            return response()->json([
                'success' => true,
                'message' => 'Landlords for this month retrieved successfully',
                'data' => $landlordsWithPropertyCount,
                'count' => $landlordsWithPropertyCount->count(),
                'period' => [
                    'start' => $startOfMonth->format('Y-m-d H:i:s'),
                    'end' => $endOfMonth->format('Y-m-d H:i:s')
                ],
                'month_statistics' => $monthStats
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'An error occurred while retrieving landlords for this month',
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

            // Get landlords created this year
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
                    'regDate'
                )
                ->orderBy('userID', 'desc')
                ->get();

            // Add property count for each landlord
            $landlordsWithPropertyCount = $landlords->map(function ($landlord) {
                $propertyCount = DB::table('property_tbl')
                    ->where('property_owner', $landlord->userID)
                    ->count();
                
                $landlord->property_count = $propertyCount;
                return $landlord;
            });

            $yearStats = [
                'total_landlords' => $landlordsWithPropertyCount->count(),
                'verified_landlords' => $landlordsWithPropertyCount->where('verified', 1)->count(),
                'unverified_landlords' => $landlordsWithPropertyCount->where('verified', 0)->count(),
                'total_properties_owned' => $landlordsWithPropertyCount->sum('property_count'),
                'average_properties_per_landlord' => $landlordsWithPropertyCount->count() > 0 ? 
                    round($landlordsWithPropertyCount->sum('property_count') / $landlordsWithPropertyCount->count(), 2) : 0,
                'monthly_breakdown' => $landlordsWithPropertyCount->groupBy(function($item) {
                    return date('Y-m', strtotime($item->regDate));
                })->map->count()
            ];

            return response()->json([
                'success' => true,
                'message' => 'Landlords for this year retrieved successfully',
                'data' => $landlordsWithPropertyCount,
                'count' => $landlordsWithPropertyCount->count(),
                'period' => [
                    'start' => $startOfYear->format('Y-m-d H:i:s'),
                    'end' => $endOfYear->format('Y-m-d H:i:s')
                ],
                'year_statistics' => $yearStats
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'An error occurred while retrieving landlords for this year',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}