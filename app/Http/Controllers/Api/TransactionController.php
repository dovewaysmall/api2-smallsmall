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
                ->select(
                    'transaction_tbl.*',
                    'user_tbl.firstName as user_firstName',
                    'user_tbl.lastName as user_lastName',
                    'user_tbl.email as user_email',
                    'user_tbl.phone as user_phone'
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
                ->select(
                    'transaction_tbl.*',
                    'user_tbl.firstName as user_firstName',
                    'user_tbl.lastName as user_lastName',
                    'user_tbl.email as user_email',
                    'user_tbl.phone as user_phone',
                    'user_tbl.user_type'
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
            'type' => 'required|in:rss,furnisure,deposit,refund,fee,commission,penalty,maintenance,other',
            'amount' => 'required|numeric|min:0',
            'payment_type' => 'required|in:paystack,bank_transfer,card,cash,mobile_money,cheque,wallet,transfer,flutterwave,crypto,other',
            'status' => 'nullable|in:pending,completed,failed,cancelled,refunded,approved',
            'reference_id' => 'nullable|string|max:255',
            'verification_id' => 'nullable|string|max:255',
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
            
            // Get next ID
            $nextId = DB::table('transaction_tbl')->max('id') + 1;

            $data = [
                'id' => $nextId,
                'transaction_id' => $transactionId,
                'userID' => $request->userID,
                'type' => $request->type,
                'amount' => $request->amount,
                'payment_type' => $request->payment_type,
                'status' => $request->status ?? 'pending',
                'reference_id' => $request->reference_id ?? '',
                'verification_id' => $request->verification_id ?? '',
                'transaction_date' => now(),
            ];

            $inserted = DB::table('transaction_tbl')->insert($data);

            if ($inserted) {
                $createdTransaction = DB::table('transaction_tbl')
                    ->leftJoin('user_tbl', DB::raw('CAST(transaction_tbl.userID AS CHAR)'), '=', DB::raw('CAST(user_tbl.userID AS CHAR)'))
                    ->select('transaction_tbl.*', 'user_tbl.firstName', 'user_tbl.lastName', 'user_tbl.email')
                    ->where('transaction_tbl.id', $nextId)
                    ->first();

                return response()->json([
                    'success' => true,
                    'message' => 'Transaction created successfully',
                    'data' => $createdTransaction,
                    'id' => $nextId
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
            'status' => 'sometimes|in:pending,completed,failed,cancelled,refunded',
            'amount' => 'sometimes|numeric|min:0',
            'payment_type' => 'sometimes|in:paystack,bank_transfer,card,cash,mobile_money,cheque,wallet,other',
            'reference_id' => 'nullable|string|max:255',
            'verification_id' => 'nullable|string|max:255',
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
                'status', 'amount', 'payment_type', 
                'reference_id', 'verification_id'
            ]);

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
            
            return response()->json([
                'success' => true,
                'message' => 'Transaction count retrieved successfully',
                'count' => $totalCount
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
                ->select(
                    'transaction_tbl.*',
                    'user_tbl.firstName',
                    'user_tbl.lastName',
                    'user_tbl.email'
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
                'total_amount' => $transactions->where('status', 'completed')->sum(function($t) { return is_numeric($t->amount) ? (float)$t->amount : 0; }),
                'pending_amount' => $transactions->where('status', 'pending')->sum(function($t) { return is_numeric($t->amount) ? (float)$t->amount : 0; }),
                'completed_transactions' => $transactions->where('status', 'completed')->count(),
                'pending_transactions' => $transactions->where('status', 'pending')->count(),
                'failed_transactions' => $transactions->where('status', 'failed')->count()
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
                ->select('transaction_tbl.*', 'user_tbl.firstName', 'user_tbl.lastName')
                ->where('transaction_tbl.status', $status)
                ->orderBy('transaction_tbl.transaction_date', 'desc')
                ->get();

            $totalAmount = $transactions->sum(function($transaction) {
                return is_numeric($transaction->amount) ? (float)$transaction->amount : 0;
            });

            return response()->json([
                'success' => true,
                'message' => 'Transactions retrieved successfully',
                'data' => $transactions,
                'count' => $transactions->count(),
                'total_amount' => $totalAmount,
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
            'type' => 'required|in:rss,furnisure,deposit,refund,fee,commission,penalty,maintenance,other'
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
                ->select('transaction_tbl.*', 'user_tbl.firstName', 'user_tbl.lastName')
                ->where('transaction_tbl.type', $type)
                ->orderBy('transaction_tbl.transaction_date', 'desc')
                ->get();

            $totalAmount = $transactions->sum(function($transaction) {
                return is_numeric($transaction->amount) ? (float)$transaction->amount : 0;
            });

            return response()->json([
                'success' => true,
                'message' => 'Transactions retrieved successfully',
                'data' => $transactions,
                'count' => $transactions->count(),
                'total_amount' => $totalAmount,
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
                ->where('status', 'completed')
                ->sum(DB::raw('CAST(amount AS DECIMAL(10,2))'));
            
            $statusStats = DB::table('transaction_tbl')
                ->select('status', DB::raw('count(*) as count'), DB::raw('sum(CAST(amount AS DECIMAL(10,2))) as total_amount'))
                ->groupBy('status')
                ->get();

            $typeStats = DB::table('transaction_tbl')
                ->select('type', DB::raw('count(*) as count'), DB::raw('sum(amount) as total_amount'))
                ->groupBy('type')
                ->orderBy('total_amount', 'desc')
                ->get();

            $paymentMethodStats = DB::table('transaction_tbl')
                ->select('payment_type', DB::raw('count(*) as count'), DB::raw('sum(amount) as total_amount'))
                ->groupBy('payment_type')
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
                ->select('transaction_tbl.*', 'user_tbl.firstName', 'user_tbl.lastName')
                ->whereDate('transaction_tbl.transaction_date', '>=', $request->start_date)
                ->whereDate('transaction_tbl.transaction_date', '<=', $request->end_date)
                ->orderBy('transaction_tbl.transaction_date', 'desc')
                ->get();

            $rangeStats = [
                'total_transactions' => $transactions->count(),
                'total_amount' => $transactions->sum(function($t) { return is_numeric($t->amount) ? (float)$t->amount : 0; }),
                'completed_amount' => $transactions->where('status', 'completed')->sum(function($t) { return is_numeric($t->amount) ? (float)$t->amount : 0; }),
                'pending_amount' => $transactions->where('status', 'pending')->sum(function($t) { return is_numeric($t->amount) ? (float)$t->amount : 0; }),
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
                ->select('transaction_tbl.*', 'user_tbl.firstName', 'user_tbl.lastName')
                ->where(function($q) use ($query) {
                    $q->where('transaction_tbl.transaction_id', 'LIKE', "%{$query}%")
                      ->orWhere('transaction_tbl.reference_id', 'LIKE', "%{$query}%")
                      ->orWhere('transaction_tbl.verification_id', 'LIKE', "%{$query}%")
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