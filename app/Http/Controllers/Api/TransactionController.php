<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class TransactionController extends Controller
{
    /**
     * Display a listing of all transactions (Improved version of your original).
     */
    public function index()
    {
        try {
            $transactions = DB::table('transaction_tbl')
                ->leftJoin('user_tbl', DB::raw('CAST(transaction_tbl.userID AS CHAR)'), '=', DB::raw('CAST(user_tbl.userID AS CHAR)'))
                ->leftJoin('property_tbl', DB::raw('CAST(transaction_tbl.propertyID AS CHAR)'), '=', DB::raw('CAST(property_tbl.propertyID AS CHAR)'))
                ->select(
                    'transaction_tbl.*',
                    'user_tbl.firstName as user_firstName',
                    'user_tbl.lastName as user_lastName',
                    'user_tbl.email as user_email',
                    'user_tbl.phone as user_phone',
                    'property_tbl.propertyTitle',
                    'property_tbl.address as property_address'
                )
                ->orderBy('transaction_tbl.transaction_date', 'desc')
                ->get();

            return response()->json([
                'success' => true,
                'message' => 'Transactions retrieved successfully',
                'data' => $transactions,
                'count' => $transactions->count()
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'An error occurred while retrieving transactions',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Display the specified transaction (Improved version of your original).
     */
    public function show(string $id)
    {
        try {
            $transaction = DB::table('transaction_tbl')
                ->leftJoin('user_tbl', DB::raw('CAST(transaction_tbl.userID AS CHAR)'), '=', DB::raw('CAST(user_tbl.userID AS CHAR)'))
                ->leftJoin('property_tbl', DB::raw('CAST(transaction_tbl.propertyID AS CHAR)'), '=', DB::raw('CAST(property_tbl.propertyID AS CHAR)'))
                ->select(
                    'transaction_tbl.*',
                    'user_tbl.firstName as user_firstName',
                    'user_tbl.lastName as user_lastName',
                    'user_tbl.email as user_email',
                    'user_tbl.phone as user_phone',
                    'user_tbl.user_type',
                    'property_tbl.propertyTitle',
                    'property_tbl.address as property_address',
                    'property_tbl.city',
                    'property_tbl.state as property_state'
                )
                ->where('transaction_tbl.id', $id)
                ->first();

            if (!$transaction) {
                return response()->json([
                    'success' => false,
                    'message' => 'Transaction not found'
                ], 404);
            }

            return response()->json([
                'success' => true,
                'message' => 'Transaction retrieved successfully',
                'data' => $transaction
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'An error occurred while retrieving transaction',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Store a newly created transaction.
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'userID' => 'required|string|exists:user_tbl,userID',
            'propertyID' => 'nullable|string|exists:property_tbl,propertyID',
            'transaction_type' => 'required|in:rent_payment,deposit,refund,fee,commission,penalty,maintenance,other',
            'amount' => 'required|numeric|min:0',
            'payment_method' => 'required|in:bank_transfer,card,cash,mobile_money,cheque,other',
            'transaction_status' => 'nullable|in:pending,completed,failed,cancelled,refunded',
            'description' => 'nullable|string|max:1000',
            'reference_number' => 'nullable|string|max:255',
            'gateway_reference' => 'nullable|string|max:255',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation error',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            // Generate unique transaction ID
            $transactionId = 'TXN' . date('Ymd') . mt_rand(1000, 9999);

            $data = [
                'transaction_id' => $transactionId,
                'userID' => $request->userID,
                'propertyID' => $request->propertyID,
                'transaction_type' => $request->transaction_type,
                'amount' => $request->amount,
                'payment_method' => $request->payment_method,
                'transaction_status' => $request->transaction_status ?? 'pending',
                'description' => $request->description,
                'reference_number' => $request->reference_number,
                'gateway_reference' => $request->gateway_reference,
                'transaction_date' => now(),
                'created_at' => now(),
            ];

            $insertedId = DB::table('transaction_tbl')->insertGetId($data);

            if ($insertedId) {
                $createdTransaction = DB::table('transaction_tbl')
                    ->leftJoin('user_tbl', DB::raw('CAST(transaction_tbl.userID AS CHAR)'), '=', DB::raw('CAST(user_tbl.userID AS CHAR)'))
                    ->select('transaction_tbl.*', 'user_tbl.firstName', 'user_tbl.lastName', 'user_tbl.email')
                    ->where('transaction_tbl.id', $insertedId)
                    ->first();

                return response()->json([
                    'success' => true,
                    'message' => 'Transaction created successfully',
                    'data' => $createdTransaction,
                    'id' => $insertedId
                ], 201);
            } else {
                return response()->json([
                    'success' => false,
                    'message' => 'Failed to create transaction'
                ], 500);
            }

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'An error occurred while creating transaction',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update the specified transaction.
     */
    public function update(Request $request, string $id)
    {
        $transaction = DB::table('transaction_tbl')->where('id', $id)->first();

        if (!$transaction) {
            return response()->json([
                'success' => false,
                'message' => 'Transaction not found'
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'transaction_status' => 'sometimes|in:pending,completed,failed,cancelled,refunded',
            'amount' => 'sometimes|numeric|min:0',
            'payment_method' => 'sometimes|in:bank_transfer,card,cash,mobile_money,cheque,other',
            'description' => 'nullable|string|max:1000',
            'reference_number' => 'nullable|string|max:255',
            'gateway_reference' => 'nullable|string|max:255',
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
                'transaction_status', 'amount', 'payment_method', 
                'description', 'reference_number', 'gateway_reference'
            ]);

            $updateData['updated_at'] = now();

            DB::table('transaction_tbl')->where('id', $id)->update($updateData);
            
            $updatedTransaction = DB::table('transaction_tbl')
                ->leftJoin('user_tbl', DB::raw('CAST(transaction_tbl.userID AS CHAR)'), '=', DB::raw('CAST(user_tbl.userID AS CHAR)'))
                ->select('transaction_tbl.*', 'user_tbl.firstName', 'user_tbl.lastName')
                ->where('transaction_tbl.id', $id)
                ->first();

            return response()->json([
                'success' => true,
                'message' => 'Transaction updated successfully',
                'data' => $updatedTransaction
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'An error occurred while updating transaction',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get transactions count (Improved version of your original).
     */
    public function count()
    {
        try {
            $totalCount = DB::table('transaction_tbl')->count();
            $todayCount = DB::table('transaction_tbl')
                ->whereDate('transaction_date', today())
                ->count();
            $thisMonthCount = DB::table('transaction_tbl')
                ->whereMonth('transaction_date', now()->month)
                ->whereYear('transaction_date', now()->year)
                ->count();
            $completedCount = DB::table('transaction_tbl')
                ->where('transaction_status', 'completed')
                ->count();
            $pendingCount = DB::table('transaction_tbl')
                ->where('transaction_status', 'pending')
                ->count();

            return response()->json([
                'success' => true,
                'message' => 'Transaction count retrieved successfully',
                'total_transactions' => $totalCount,
                'today_transactions' => $todayCount,
                'this_month_transactions' => $thisMonthCount,
                'completed_transactions' => $completedCount,
                'pending_transactions' => $pendingCount
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'An error occurred while retrieving transaction count',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get transactions by user (Improved version of your original).
     */
    public function getByUser($userId)
    {
        try {
            $transactions = DB::table('transaction_tbl')
                ->leftJoin('user_tbl', DB::raw('CAST(transaction_tbl.userID AS CHAR)'), '=', DB::raw('CAST(user_tbl.userID AS CHAR)'))
                ->leftJoin('property_tbl', DB::raw('CAST(transaction_tbl.propertyID AS CHAR)'), '=', DB::raw('CAST(property_tbl.propertyID AS CHAR)'))
                ->select(
                    'transaction_tbl.*',
                    'user_tbl.firstName',
                    'user_tbl.lastName',
                    'user_tbl.email',
                    'property_tbl.propertyTitle',
                    'property_tbl.address as property_address'
                )
                ->where('transaction_tbl.userID', $userId)
                ->orderBy('transaction_tbl.transaction_date', 'desc')
                ->get();

            if ($transactions->isEmpty()) {
                return response()->json([
                    'success' => true,
                    'message' => 'No transactions found for this user',
                    'data' => [],
                    'count' => 0
                ]);
            }

            // Calculate user transaction statistics
            $userStats = [
                'total_transactions' => $transactions->count(),
                'total_amount' => $transactions->where('transaction_status', 'completed')->sum('amount'),
                'pending_amount' => $transactions->where('transaction_status', 'pending')->sum('amount'),
                'completed_transactions' => $transactions->where('transaction_status', 'completed')->count(),
                'pending_transactions' => $transactions->where('transaction_status', 'pending')->count(),
                'failed_transactions' => $transactions->where('transaction_status', 'failed')->count()
            ];

            return response()->json([
                'success' => true,
                'message' => 'User transactions retrieved successfully',
                'data' => $transactions,
                'count' => $transactions->count(),
                'user_statistics' => $userStats,
                'user_id' => $userId
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'An error occurred while retrieving user transactions',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get transactions by status.
     */
    public function getByStatus($status)
    {
        $validator = Validator::make(['status' => $status], [
            'status' => 'required|in:pending,completed,failed,cancelled,refunded'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid status. Must be: pending, completed, failed, cancelled, or refunded',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $transactions = DB::table('transaction_tbl')
                ->leftJoin('user_tbl', DB::raw('CAST(transaction_tbl.userID AS CHAR)'), '=', DB::raw('CAST(user_tbl.userID AS CHAR)'))
                ->leftJoin('property_tbl', DB::raw('CAST(transaction_tbl.propertyID AS CHAR)'), '=', DB::raw('CAST(property_tbl.propertyID AS CHAR)'))
                ->select('transaction_tbl.*', 'user_tbl.firstName', 'user_tbl.lastName', 'property_tbl.propertyTitle')
                ->where('transaction_tbl.transaction_status', $status)
                ->orderBy('transaction_tbl.transaction_date', 'desc')
                ->get();

            return response()->json([
                'success' => true,
                'message' => 'Transactions retrieved successfully',
                'data' => $transactions,
                'count' => $transactions->count(),
                'total_amount' => $transactions->sum('amount'),
                'status' => $status
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'An error occurred while retrieving transactions',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get transactions by type.
     */
    public function getByType($type)
    {
        $validator = Validator::make(['type' => $type], [
            'type' => 'required|in:rent_payment,deposit,refund,fee,commission,penalty,maintenance,other'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid transaction type',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $transactions = DB::table('transaction_tbl')
                ->leftJoin('user_tbl', DB::raw('CAST(transaction_tbl.userID AS CHAR)'), '=', DB::raw('CAST(user_tbl.userID AS CHAR)'))
                ->leftJoin('property_tbl', DB::raw('CAST(transaction_tbl.propertyID AS CHAR)'), '=', DB::raw('CAST(property_tbl.propertyID AS CHAR)'))
                ->select('transaction_tbl.*', 'user_tbl.firstName', 'user_tbl.lastName', 'property_tbl.propertyTitle')
                ->where('transaction_tbl.transaction_type', $type)
                ->orderBy('transaction_tbl.transaction_date', 'desc')
                ->get();

            return response()->json([
                'success' => true,
                'message' => 'Transactions retrieved successfully',
                'data' => $transactions,
                'count' => $transactions->count(),
                'total_amount' => $transactions->sum('amount'),
                'transaction_type' => $type
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'An error occurred while retrieving transactions',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get transaction statistics and analytics.
     */
    public function getStats()
    {
        try {
            $totalTransactions = DB::table('transaction_tbl')->count();
            $totalAmount = DB::table('transaction_tbl')
                ->where('transaction_status', 'completed')
                ->sum('amount');
            
            $statusStats = DB::table('transaction_tbl')
                ->select('transaction_status', DB::raw('count(*) as count'), DB::raw('sum(amount) as total_amount'))
                ->groupBy('transaction_status')
                ->get();

            $typeStats = DB::table('transaction_tbl')
                ->select('transaction_type', DB::raw('count(*) as count'), DB::raw('sum(amount) as total_amount'))
                ->groupBy('transaction_type')
                ->orderBy('total_amount', 'desc')
                ->get();

            $paymentMethodStats = DB::table('transaction_tbl')
                ->select('payment_method', DB::raw('count(*) as count'), DB::raw('sum(amount) as total_amount'))
                ->groupBy('payment_method')
                ->orderBy('count', 'desc')
                ->get();

            $monthlyStats = DB::table('transaction_tbl')
                ->select(
                    DB::raw('YEAR(transaction_date) as year'),
                    DB::raw('MONTH(transaction_date) as month'),
                    DB::raw('count(*) as transaction_count'),
                    DB::raw('sum(amount) as total_amount')
                )
                ->where('transaction_date', '>=', now()->subMonths(12))
                ->groupBy('year', 'month')
                ->orderBy('year', 'desc')
                ->orderBy('month', 'desc')
                ->get();

            $todayStats = DB::table('transaction_tbl')
                ->whereDate('transaction_date', today())
                ->selectRaw('count(*) as count, sum(amount) as amount')
                ->first();

            return response()->json([
                'success' => true,
                'message' => 'Transaction statistics retrieved successfully',
                'total_transactions' => $totalTransactions,
                'total_completed_amount' => round($totalAmount, 2),
                'today_transactions' => $todayStats->count,
                'today_amount' => round($todayStats->amount ?? 0, 2),
                'status_breakdown' => $statusStats,
                'type_breakdown' => $typeStats,
                'payment_method_breakdown' => $paymentMethodStats,
                'monthly_trends' => $monthlyStats
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'An error occurred while retrieving transaction statistics',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get transactions by date range.
     */
    public function getByDateRange(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation error',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $transactions = DB::table('transaction_tbl')
                ->leftJoin('user_tbl', DB::raw('CAST(transaction_tbl.userID AS CHAR)'), '=', DB::raw('CAST(user_tbl.userID AS CHAR)'))
                ->leftJoin('property_tbl', DB::raw('CAST(transaction_tbl.propertyID AS CHAR)'), '=', DB::raw('CAST(property_tbl.propertyID AS CHAR)'))
                ->select('transaction_tbl.*', 'user_tbl.firstName', 'user_tbl.lastName', 'property_tbl.propertyTitle')
                ->whereDate('transaction_tbl.transaction_date', '>=', $request->start_date)
                ->whereDate('transaction_tbl.transaction_date', '<=', $request->end_date)
                ->orderBy('transaction_tbl.transaction_date', 'desc')
                ->get();

            $rangeStats = [
                'total_transactions' => $transactions->count(),
                'total_amount' => $transactions->sum('amount'),
                'completed_amount' => $transactions->where('transaction_status', 'completed')->sum('amount'),
                'pending_amount' => $transactions->where('transaction_status', 'pending')->sum('amount'),
                'average_transaction_amount' => $transactions->count() > 0 ? round($transactions->avg('amount'), 2) : 0
            ];

            return response()->json([
                'success' => true,
                'message' => 'Transactions retrieved successfully',
                'data' => $transactions,
                'count' => $transactions->count(),
                'date_range' => [
                    'start' => $request->start_date,
                    'end' => $request->end_date
                ],
                'range_statistics' => $rangeStats
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'An error occurred while retrieving transactions',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Search transactions.
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
            $transactions = DB::table('transaction_tbl')
                ->leftJoin('user_tbl', DB::raw('CAST(transaction_tbl.userID AS CHAR)'), '=', DB::raw('CAST(user_tbl.userID AS CHAR)'))
                ->leftJoin('property_tbl', DB::raw('CAST(transaction_tbl.propertyID AS CHAR)'), '=', DB::raw('CAST(property_tbl.propertyID AS CHAR)'))
                ->select('transaction_tbl.*', 'user_tbl.firstName', 'user_tbl.lastName', 'property_tbl.propertyTitle')
                ->where(function($q) use ($query) {
                    $q->where('transaction_tbl.transaction_id', 'LIKE', "%{$query}%")
                      ->orWhere('transaction_tbl.reference_number', 'LIKE', "%{$query}%")
                      ->orWhere('transaction_tbl.gateway_reference', 'LIKE', "%{$query}%")
                      ->orWhere('user_tbl.firstName', 'LIKE', "%{$query}%")
                      ->orWhere('user_tbl.lastName', 'LIKE', "%{$query}%")
                      ->orWhere('user_tbl.email', 'LIKE', "%{$query}%");
                })
                ->orderBy('transaction_tbl.transaction_date', 'desc')
                ->get();

            return response()->json([
                'success' => true,
                'message' => 'Transaction search results',
                'data' => $transactions,
                'count' => $transactions->count(),
                'search_query' => $query
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'An error occurred while searching transactions',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}