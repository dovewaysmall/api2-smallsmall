<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class UserController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $users = User::all();
        
        return response()->json([
            'success' => true,
            'message' => 'Users retrieved successfully',
            'data' => $users
        ]);
    }

    /**
     * Get users count.
     */
    public function count()
    {
        $count = User::count();
        
        return response()->json([
            'success' => true,
            'message' => 'Users count retrieved successfully',
            'count' => $count
        ]);
    }

    /**
     * Get users count for current month.
     */
    public function monthlyCount()
    {
        $count = User::whereMonth('regDate', now()->month)
                    ->whereYear('regDate', now()->year)
                    ->count();
        
        return response()->json([
            'success' => true,
            'message' => 'Monthly users count retrieved successfully',
            'count' => $count,
            'month' => now()->format('F Y')
        ]);
    }

    /**
     * Get users count for current year.
     */
    public function yearlyCount()
    {
        $count = User::whereYear('regDate', now()->year)
                    ->count();
        
        return response()->json([
            'success' => true,
            'message' => 'Yearly users count retrieved successfully',
            'count' => $count,
            'year' => now()->year
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        //
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        $user = User::find($id);
        
        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'User not found'
            ], 404);
        }
        
        return response()->json([
            'success' => true,
            'message' => 'User retrieved successfully',
            'data' => $user
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        //
    }

    /**
     * Get unconverted users from this week (users without booking records).
     */
    public function getUnconvertedThisWeek()
    {
        try {
            $startOfWeek = now()->startOfWeek();
            $endOfWeek = now()->endOfWeek();

            $unconvertedUsers = DB::table('user_tbl')
                ->leftJoin('bookings', DB::raw('CAST(user_tbl.userID AS CHAR)'), '=', DB::raw('CAST(bookings.userID AS CHAR)'))
                ->select('user_tbl.*')
                ->whereNull('bookings.userID')
                ->whereDate('user_tbl.regDate', '>=', $startOfWeek)
                ->whereDate('user_tbl.regDate', '<=', $endOfWeek)
                ->orderBy('user_tbl.regDate', 'desc')
                ->get();

            $weekStats = [
                'total_unconverted_users' => $unconvertedUsers->count(),
                'verified_users' => $unconvertedUsers->where('verified', 1)->count(),
                'unverified_users' => $unconvertedUsers->where('verified', 0)->count(),
                'user_types' => $unconvertedUsers->groupBy('user_type')->map->count(),
                'conversion_opportunity' => $unconvertedUsers->count()
            ];

            return response()->json([
                'success' => true,
                'message' => 'Unconverted users for this week retrieved successfully',
                'data' => $unconvertedUsers,
                'count' => $unconvertedUsers->count(),
                'period' => [
                    'start' => $startOfWeek->format('Y-m-d H:i:s'),
                    'end' => $endOfWeek->format('Y-m-d H:i:s')
                ],
                'week_statistics' => $weekStats
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'An error occurred while retrieving unconverted users for this week',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get unconverted users from this month (users without booking records).
     */
    public function getUnconvertedThisMonth()
    {
        try {
            $startOfMonth = now()->startOfMonth();
            $endOfMonth = now()->endOfMonth();

            $unconvertedUsers = DB::table('user_tbl')
                ->leftJoin('bookings', DB::raw('CAST(user_tbl.userID AS CHAR)'), '=', DB::raw('CAST(bookings.userID AS CHAR)'))
                ->select('user_tbl.*')
                ->whereNull('bookings.userID')
                ->whereDate('user_tbl.regDate', '>=', $startOfMonth)
                ->whereDate('user_tbl.regDate', '<=', $endOfMonth)
                ->orderBy('user_tbl.regDate', 'desc')
                ->get();

            $monthStats = [
                'total_unconverted_users' => $unconvertedUsers->count(),
                'verified_users' => $unconvertedUsers->where('verified', 1)->count(),
                'unverified_users' => $unconvertedUsers->where('verified', 0)->count(),
                'user_types' => $unconvertedUsers->groupBy('user_type')->map->count(),
                'conversion_opportunity' => $unconvertedUsers->count(),
                'average_days_since_registration' => $unconvertedUsers->count() > 0 ? 
                    round($unconvertedUsers->avg(function($user) {
                        return now()->diffInDays($user->regDate);
                    }), 1) : 0,
                'verification_rate' => $unconvertedUsers->count() > 0 ? 
                    round(($unconvertedUsers->where('verified', 1)->count() / $unconvertedUsers->count()) * 100, 2) : 0
            ];

            return response()->json([
                'success' => true,
                'message' => 'Unconverted users for this month retrieved successfully',
                'data' => $unconvertedUsers,
                'count' => $unconvertedUsers->count(),
                'period' => [
                    'start' => $startOfMonth->format('Y-m-d H:i:s'),
                    'end' => $endOfMonth->format('Y-m-d H:i:s')
                ],
                'month_statistics' => $monthStats
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'An error occurred while retrieving unconverted users for this month',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get unconverted users from this year (users without booking records).
     */
    public function getUnconvertedThisYear()
    {
        try {
            $startOfYear = now()->startOfYear();
            $endOfYear = now()->endOfYear();

            $unconvertedUsers = DB::table('user_tbl')
                ->leftJoin('bookings', DB::raw('CAST(user_tbl.userID AS CHAR)'), '=', DB::raw('CAST(bookings.userID AS CHAR)'))
                ->select('user_tbl.*')
                ->whereNull('bookings.userID')
                ->whereDate('user_tbl.regDate', '>=', $startOfYear)
                ->whereDate('user_tbl.regDate', '<=', $endOfYear)
                ->orderBy('user_tbl.regDate', 'desc')
                ->get();

            $yearStats = [
                'total_unconverted_users' => $unconvertedUsers->count(),
                'verified_users' => $unconvertedUsers->where('verified', 1)->count(),
                'unverified_users' => $unconvertedUsers->where('verified', 0)->count(),
                'user_types' => $unconvertedUsers->groupBy('user_type')->map->count(),
                'conversion_opportunity' => $unconvertedUsers->count(),
                'average_days_since_registration' => $unconvertedUsers->count() > 0 ? 
                    round($unconvertedUsers->avg(function($user) {
                        return now()->diffInDays($user->regDate);
                    }), 1) : 0,
                'verification_rate' => $unconvertedUsers->count() > 0 ? 
                    round(($unconvertedUsers->where('verified', 1)->count() / $unconvertedUsers->count()) * 100, 2) : 0,
                'monthly_breakdown' => $unconvertedUsers->groupBy(function($user) {
                    return date('Y-m', strtotime($user->regDate));
                })->map->count(),
                'longest_unconverted_days' => $unconvertedUsers->count() > 0 ? 
                    $unconvertedUsers->max(function($user) {
                        return now()->diffInDays($user->regDate);
                    }) : 0
            ];

            return response()->json([
                'success' => true,
                'message' => 'Unconverted users for this year retrieved successfully',
                'data' => $unconvertedUsers,
                'count' => $unconvertedUsers->count(),
                'period' => [
                    'start' => $startOfYear->format('Y-m-d H:i:s'),
                    'end' => $endOfYear->format('Y-m-d H:i:s')
                ],
                'year_statistics' => $yearStats
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'An error occurred while retrieving unconverted users for this year',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get all unconverted users (users without booking records).
     */
    public function getAllUnconverted()
    {
        try {
            $unconvertedUsers = DB::table('user_tbl')
                ->leftJoin('bookings', DB::raw('CAST(user_tbl.userID AS CHAR)'), '=', DB::raw('CAST(bookings.userID AS CHAR)'))
                ->select('user_tbl.*')
                ->whereNull('bookings.userID')
                ->orderBy('user_tbl.regDate', 'desc')
                ->get();

            $overallStats = [
                'total_unconverted_users' => $unconvertedUsers->count(),
                'verified_users' => $unconvertedUsers->where('verified', 1)->count(),
                'unverified_users' => $unconvertedUsers->where('verified', 0)->count(),
                'user_types' => $unconvertedUsers->groupBy('user_type')->map->count(),
                'verification_rate' => $unconvertedUsers->count() > 0 ? 
                    round(($unconvertedUsers->where('verified', 1)->count() / $unconvertedUsers->count()) * 100, 2) : 0,
                'average_days_since_registration' => $unconvertedUsers->count() > 0 ? 
                    round($unconvertedUsers->avg(function($user) {
                        return now()->diffInDays($user->regDate);
                    }), 1) : 0,
                'longest_unconverted_days' => $unconvertedUsers->count() > 0 ? 
                    $unconvertedUsers->max(function($user) {
                        return now()->diffInDays($user->regDate);
                    }) : 0,
                'recent_registrations' => $unconvertedUsers->where('regDate', '>=', now()->subDays(7))->count(),
                'old_registrations' => $unconvertedUsers->where('regDate', '<=', now()->subDays(30))->count(),
                'registration_timeline' => [
                    'last_7_days' => $unconvertedUsers->where('regDate', '>=', now()->subDays(7))->count(),
                    'last_30_days' => $unconvertedUsers->where('regDate', '>=', now()->subDays(30))->count(),
                    'last_90_days' => $unconvertedUsers->where('regDate', '>=', now()->subDays(90))->count(),
                    'over_90_days' => $unconvertedUsers->where('regDate', '<', now()->subDays(90))->count()
                ],
                'monthly_breakdown' => $unconvertedUsers->groupBy(function($user) {
                    return date('Y-m', strtotime($user->regDate));
                })->map->count(),
                'conversion_urgency' => [
                    'high_priority' => $unconvertedUsers->where('regDate', '>=', now()->subDays(7))->where('verified', 1)->count(),
                    'medium_priority' => $unconvertedUsers->where('regDate', '>=', now()->subDays(30))->where('verified', 1)->count(),
                    'low_priority' => $unconvertedUsers->where('regDate', '<', now()->subDays(30))->count()
                ]
            ];

            return response()->json([
                'success' => true,
                'message' => 'All unconverted users retrieved successfully',
                'data' => $unconvertedUsers,
                'count' => $unconvertedUsers->count(),
                'overall_statistics' => $overallStats,
                'summary' => [
                    'total_opportunity' => $unconvertedUsers->count(),
                    'immediate_targets' => $overallStats['conversion_urgency']['high_priority'],
                    'potential_churn_risk' => $overallStats['registration_timeline']['over_90_days']
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'An error occurred while retrieving all unconverted users',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
