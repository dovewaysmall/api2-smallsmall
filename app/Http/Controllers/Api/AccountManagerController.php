<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class AccountManagerController extends Controller
{
    /**
     * Display a listing of all account managers.
     */
    public function index()
    {
        try {
            $accountManagers = DB::table('user_tbl')
                ->select(
                    'userID',
                    'firstName',
                    'lastName',
                    'email',
                    'phone',
                    'verified',
                    'profile_image',
                    'address',
                    'state',
                    'country',
                    'date_created',
                    'last_login'
                )
                ->whereIn('user_type', ['admin', 'staff', 'manager'])
                ->orWhereIn('userID', function($query) {
                    $query->select('account_manager')
                          ->from('user_tbl')
                          ->whereNotNull('account_manager')
                          ->distinct();
                })
                ->orderBy('firstName', 'asc')
                ->get();

            // Get client counts for each manager
            $managersWithCounts = $accountManagers->map(function($manager) {
                $clientCount = DB::table('user_tbl')
                    ->where('account_manager', $manager->userID)
                    ->count();

                $manager->client_count = $clientCount;
                return $manager;
            });

            return response()->json([
                'success' => true,
                'message' => 'Account managers retrieved successfully',
                'data' => $managersWithCounts,
                'count' => $managersWithCounts->count()
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'An error occurred while retrieving account managers',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Display the specified account manager with their clients.
     */
    public function show(string $id)
    {
        try {
            $accountManager = DB::table('user_tbl')
                ->select(
                    'userID',
                    'firstName',
                    'lastName',
                    'email',
                    'phone',
                    'verified',
                    'user_type',
                    'profile_image',
                    'address',
                    'state',
                    'country',
                    'date_created',
                    'last_login'
                )
                ->where('userID', $id)
                ->first();

            if (!$accountManager) {
                return response()->json([
                    'success' => false,
                    'message' => 'Account manager not found'
                ], 404);
            }

            // Get all clients assigned to this manager
            $clients = DB::table('user_tbl')
                ->select(
                    'userID',
                    'firstName',
                    'lastName',
                    'email',
                    'phone',
                    'user_type',
                    'verified',
                    'date_created',
                    'last_login'
                )
                ->where('account_manager', $id)
                ->orderBy('firstName', 'asc')
                ->get();

            // Get client statistics
            $clientStats = [
                'total_clients' => $clients->count(),
                'verified_clients' => $clients->where('verified', 1)->count(),
                'tenant_clients' => $clients->where('user_type', 'tenant')->count(),
                'landlord_clients' => $clients->where('user_type', 'landlord')->count(),
                'recent_clients' => $clients->where('date_created', '>=', now()->subDays(30))->count()
            ];

            return response()->json([
                'success' => true,
                'message' => 'Account manager retrieved successfully',
                'data' => [
                    'manager_info' => $accountManager,
                    'clients' => $clients,
                    'client_statistics' => $clientStats
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'An error occurred while retrieving account manager',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Assign account manager to a client (Improved version of your original).
     */
    public function assignManager(Request $request)
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
            // Check if the client exists
            $client = DB::table('user_tbl')
                ->where('userID', $request->userID)
                ->first();

            if (!$client) {
                return response()->json([
                    'success' => false,
                    'message' => 'Client not found'
                ], 404);
            }

            // Check if the account manager exists
            $manager = DB::table('user_tbl')
                ->where('userID', $request->account_manager)
                ->first();

            if (!$manager) {
                return response()->json([
                    'success' => false,
                    'message' => 'Account manager not found'
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
                // Get updated client info with manager details
                $updatedClient = DB::table('user_tbl')
                    ->leftJoin('user_tbl as manager', DB::raw('CAST(user_tbl.account_manager AS CHAR)'), '=', DB::raw('CAST(manager.userID AS CHAR)'))
                    ->select(
                        'user_tbl.userID',
                        'user_tbl.firstName',
                        'user_tbl.lastName',
                        'user_tbl.email',
                        'user_tbl.user_type',
                        'user_tbl.account_manager',
                        'manager.firstName as manager_firstName',
                        'manager.lastName as manager_lastName',
                        'manager.email as manager_email'
                    )
                    ->where('user_tbl.userID', $request->userID)
                    ->first();

                return response()->json([
                    'success' => true,
                    'message' => 'Account manager assigned successfully',
                    'data' => $updatedClient
                ]);
            } else {
                return response()->json([
                    'success' => false,
                    'message' => 'Failed to assign account manager'
                ], 500);
            }

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'An error occurred while assigning account manager',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Remove account manager from a client.
     */
    public function removeManager(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'userID' => 'required|string|exists:user_tbl,userID',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation error',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $client = DB::table('user_tbl')
                ->where('userID', $request->userID)
                ->first();

            if (!$client) {
                return response()->json([
                    'success' => false,
                    'message' => 'Client not found'
                ], 404);
            }

            if (!$client->account_manager) {
                return response()->json([
                    'success' => false,
                    'message' => 'Client does not have an account manager assigned'
                ], 400);
            }

            $updated = DB::table('user_tbl')
                ->where('userID', $request->userID)
                ->update([
                    'account_manager' => null,
                    'updated_at' => now()
                ]);

            if ($updated) {
                return response()->json([
                    'success' => true,
                    'message' => 'Account manager removed successfully',
                    'data' => [
                        'userID' => $request->userID,
                        'account_manager' => null
                    ]
                ]);
            } else {
                return response()->json([
                    'success' => false,
                    'message' => 'Failed to remove account manager'
                ], 500);
            }

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'An error occurred while removing account manager',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get clients without account managers.
     */
    public function getUnassignedClients()
    {
        try {
            $unassignedClients = DB::table('user_tbl')
                ->select(
                    'userID',
                    'firstName',
                    'lastName',
                    'email',
                    'phone',
                    'user_type',
                    'verified',
                    'date_created'
                )
                ->whereNull('account_manager')
                ->whereIn('user_type', ['tenant', 'landlord'])
                ->orderBy('date_created', 'desc')
                ->get();

            return response()->json([
                'success' => true,
                'message' => 'Unassigned clients retrieved successfully',
                'data' => $unassignedClients,
                'count' => $unassignedClients->count()
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'An error occurred while retrieving unassigned clients',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get account manager workload distribution.
     */
    public function getWorkloadDistribution()
    {
        try {
            $workloadDistribution = DB::table('user_tbl as manager')
                ->leftJoin('user_tbl as client', DB::raw('CAST(manager.userID AS CHAR)'), '=', DB::raw('CAST(client.account_manager AS CHAR)'))
                ->select(
                    'manager.userID',
                    'manager.firstName',
                    'manager.lastName',
                    'manager.email',
                    DB::raw('COUNT(client.userID) as client_count'),
                    DB::raw('SUM(CASE WHEN client.user_type = "tenant" THEN 1 ELSE 0 END) as tenant_count'),
                    DB::raw('SUM(CASE WHEN client.user_type = "landlord" THEN 1 ELSE 0 END) as landlord_count'),
                    DB::raw('SUM(CASE WHEN client.verified = 1 THEN 1 ELSE 0 END) as verified_clients')
                )
                ->whereIn('manager.user_type', ['admin', 'staff', 'manager'])
                ->groupBy('manager.userID', 'manager.firstName', 'manager.lastName', 'manager.email')
                ->orderBy('client_count', 'desc')
                ->get();

            // Calculate workload balance recommendations
            $totalClients = $workloadDistribution->sum('client_count');
            $totalManagers = $workloadDistribution->count();
            $averageClientsPerManager = $totalManagers > 0 ? round($totalClients / $totalManagers, 1) : 0;

            $workloadAnalysis = [
                'total_managers' => $totalManagers,
                'total_assigned_clients' => $totalClients,
                'average_clients_per_manager' => $averageClientsPerManager,
                'overloaded_managers' => $workloadDistribution->where('client_count', '>', $averageClientsPerManager + 5)->count(),
                'underutilized_managers' => $workloadDistribution->where('client_count', '<', max(1, $averageClientsPerManager - 5))->count()
            ];

            return response()->json([
                'success' => true,
                'message' => 'Account manager workload distribution retrieved successfully',
                'data' => $workloadDistribution,
                'workload_analysis' => $workloadAnalysis
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'An error occurred while retrieving workload distribution',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Bulk assign clients to account managers.
     */
    public function bulkAssign(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'assignments' => 'required|array|min:1',
            'assignments.*.userID' => 'required|string|exists:user_tbl,userID',
            'assignments.*.account_manager' => 'required|string|exists:user_tbl,userID',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation error',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $assignments = $request->assignments;
            $successCount = 0;
            $failedAssignments = [];

            foreach ($assignments as $assignment) {
                try {
                    $updated = DB::table('user_tbl')
                        ->where('userID', $assignment['userID'])
                        ->update([
                            'account_manager' => $assignment['account_manager'],
                            'updated_at' => now()
                        ]);

                    if ($updated) {
                        $successCount++;
                    } else {
                        $failedAssignments[] = $assignment['userID'];
                    }
                } catch (\Exception $e) {
                    $failedAssignments[] = $assignment['userID'];
                }
            }

            return response()->json([
                'success' => true,
                'message' => 'Bulk assignment completed',
                'successful_assignments' => $successCount,
                'failed_assignments' => count($failedAssignments),
                'failed_user_ids' => $failedAssignments,
                'total_processed' => count($assignments)
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'An error occurred during bulk assignment',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get account manager performance statistics.
     */
    public function getPerformanceStats($id)
    {
        try {
            $manager = DB::table('user_tbl')
                ->where('userID', $id)
                ->first();

            if (!$manager) {
                return response()->json([
                    'success' => false,
                    'message' => 'Account manager not found'
                ], 404);
            }

            // Client statistics
            $clientStats = DB::table('user_tbl')
                ->select(
                    DB::raw('COUNT(*) as total_clients'),
                    DB::raw('SUM(CASE WHEN verified = 1 THEN 1 ELSE 0 END) as verified_clients'),
                    DB::raw('SUM(CASE WHEN user_type = "tenant" THEN 1 ELSE 0 END) as tenant_clients'),
                    DB::raw('SUM(CASE WHEN user_type = "landlord" THEN 1 ELSE 0 END) as landlord_clients'),
                    DB::raw('SUM(CASE WHEN date_created >= DATE_SUB(NOW(), INTERVAL 30 DAY) THEN 1 ELSE 0 END) as new_clients_30_days')
                )
                ->where('account_manager', $id)
                ->first();

            // Active rentals for managed tenants
            $activeRentals = DB::table('bookings')
                ->join('user_tbl', DB::raw('CAST(bookings.userID AS CHAR)'), '=', DB::raw('CAST(user_tbl.userID AS CHAR)'))
                ->where('user_tbl.account_manager', $id)
                ->where('bookings.rent_status', 'active')
                ->count();

            // Revenue generated by managed clients
            $revenueStats = DB::table('bookings')
                ->join('user_tbl', DB::raw('CAST(bookings.userID AS CHAR)'), '=', DB::raw('CAST(user_tbl.userID AS CHAR)'))
                ->where('user_tbl.account_manager', $id)
                ->where('bookings.rent_status', 'active')
                ->selectRaw('SUM(rent_amount) as total_revenue, COUNT(*) as active_bookings')
                ->first();

            // Recent activity
            $recentActivity = [
                'new_clients_this_month' => $clientStats->new_clients_30_days,
                'active_rentals' => $activeRentals,
                'total_revenue_managed' => round($revenueStats->total_revenue ?? 0, 2)
            ];

            return response()->json([
                'success' => true,
                'message' => 'Account manager performance statistics retrieved successfully',
                'manager_info' => [
                    'userID' => $manager->userID,
                    'name' => $manager->firstName . ' ' . $manager->lastName,
                    'email' => $manager->email
                ],
                'client_statistics' => $clientStats,
                'performance_metrics' => $recentActivity
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'An error occurred while retrieving performance statistics',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Search account managers.
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
            $managers = DB::table('user_tbl')
                ->select(
                    'userID',
                    'firstName',
                    'lastName',
                    'email',
                    'phone',
                    'user_type',
                    'verified',
                    'date_created'
                )
                ->whereIn('user_type', ['admin', 'staff', 'manager'])
                ->where(function($q) use ($query) {
                    $q->where('firstName', 'LIKE', "%{$query}%")
                      ->orWhere('lastName', 'LIKE', "%{$query}%")
                      ->orWhere('email', 'LIKE', "%{$query}%");
                })
                ->orderBy('firstName', 'asc')
                ->get();

            // Add client counts
            $managersWithCounts = $managers->map(function($manager) {
                $clientCount = DB::table('user_tbl')
                    ->where('account_manager', $manager->userID)
                    ->count();
                $manager->client_count = $clientCount;
                return $manager;
            });

            return response()->json([
                'success' => true,
                'message' => 'Account manager search results',
                'data' => $managersWithCounts,
                'count' => $managersWithCounts->count(),
                'search_query' => $query
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'An error occurred while searching account managers',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}