<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class PayoutController extends Controller
{
    /**
     * Display a listing of all payouts (Improved version of your original).
     */
    public function index()
    {
        try {
            $payouts = DB::table('payout')
                ->leftJoin('user_tbl', DB::raw('CAST(payout.payee_id AS CHAR)'), '=', DB::raw('CAST(user_tbl.userID AS CHAR)'))
                ->leftJoin('user_tbl as authorizer', DB::raw('CAST(payout.authorized_by AS CHAR)'), '=', DB::raw('CAST(authorizer.userID AS CHAR)'))
                ->select(
                    'payout.*',
                    'user_tbl.firstName as payee_firstName',
                    'user_tbl.lastName as payee_lastName',
                    'user_tbl.email as payee_email',
                    'user_tbl.phone as payee_phone',
                    'authorizer.firstName as authorizer_firstName',
                    'authorizer.lastName as authorizer_lastName'
                )
                ->orderBy('payout.id', 'desc')
                ->get();

            return response()->json([
                'success' => true,
                'message' => 'Payouts retrieved successfully',
                'data' => $payouts,
                'count' => $payouts->count()
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'An error occurred while retrieving payouts',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Display the specified payout (Improved version of your original).
     */
    public function show(string $id)
    {
        try {
            $payout = DB::table('payout')
                ->leftJoin('user_tbl', DB::raw('CAST(payout.payee_id AS CHAR)'), '=', DB::raw('CAST(user_tbl.userID AS CHAR)'))
                ->leftJoin('user_tbl as authorizer', DB::raw('CAST(payout.authorized_by AS CHAR)'), '=', DB::raw('CAST(authorizer.userID AS CHAR)'))
                ->select(
                    'payout.*',
                    'user_tbl.firstName as payee_firstName',
                    'user_tbl.lastName as payee_lastName',
                    'user_tbl.email as payee_email',
                    'user_tbl.phone as payee_phone',
                    'authorizer.firstName as authorizer_firstName',
                    'authorizer.lastName as authorizer_lastName'
                )
                ->where('payout.id', $id)
                ->first();

            if (!$payout) {
                return response()->json([
                    'success' => false,
                    'message' => 'Payout not found'
                ], 404);
            }

            return response()->json([
                'success' => true,
                'message' => 'Payout retrieved successfully',
                'data' => $payout
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'An error occurred while retrieving payout',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Store a newly created payout (Improved version of your original).
     */
    public function store(Request $request)
    {
        // Enhanced validation rules
        $validator = Validator::make($request->all(), [
            'payee_id' => 'required|string|max:255',
            'next_payout' => 'required|numeric|min:0',
            'next_payout_date' => 'required|date|after_or_equal:today',
            'authorized_by' => 'required|string|max:255',
            'date_paid' => 'nullable|date',
            'payout_method' => 'nullable|string|in:bank_transfer,mobile_money,cash,cheque',
            'bank_details' => 'nullable|string|max:500',
            'reference_number' => 'nullable|string|max:255',
            'notes' => 'nullable|string|max:1000',
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
                'payee_id' => $request->payee_id,
                'next_payout' => $request->next_payout,
                'next_payout_date' => $request->next_payout_date,
                'payout_status' => 'pending',
                'authorized_by' => $request->authorized_by,
                'date_paid' => $request->date_paid,
                'payout_method' => $request->payout_method,
                'bank_details' => $request->bank_details,
                'reference_number' => $request->reference_number,
                'notes' => $request->notes,
                'date_created' => now(),
            ];

            // Insert payout and get ID
            $payoutId = DB::table('payout')->insertGetId($data);

            if ($payoutId) {
                // Retrieve the created payout with user details
                $createdPayout = DB::table('payout')
                    ->leftJoin('user_tbl', DB::raw('CAST(payout.payee_id AS CHAR)'), '=', DB::raw('CAST(user_tbl.userID AS CHAR)'))
                    ->select('payout.*', 'user_tbl.firstName', 'user_tbl.lastName', 'user_tbl.email')
                    ->where('payout.id', $payoutId)
                    ->first();

                return response()->json([
                    'success' => true,
                    'message' => 'Payout created successfully',
                    'data' => $createdPayout,
                    'id' => $payoutId
                ], 201);
            } else {
                return response()->json([
                    'success' => false,
                    'message' => 'Failed to create payout'
                ], 500);
            }

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'An error occurred while creating payout',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update the specified payout.
     */
    public function update(Request $request, string $id)
    {
        $payout = DB::table('payout')->where('id', $id)->first();

        if (!$payout) {
            return response()->json([
                'success' => false,
                'message' => 'Payout not found'
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'next_payout' => 'sometimes|numeric|min:0',
            'next_payout_date' => 'sometimes|date',
            'payout_status' => 'sometimes|in:pending,approved,paid,cancelled,failed',
            'date_paid' => 'nullable|date',
            'payout_method' => 'nullable|string|in:bank_transfer,mobile_money,cash,cheque',
            'bank_details' => 'nullable|string|max:500',
            'reference_number' => 'nullable|string|max:255',
            'notes' => 'nullable|string|max:1000',
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
                'next_payout', 'next_payout_date', 'payout_status', 'date_paid',
                'payout_method', 'bank_details', 'reference_number', 'notes'
            ]);

            // Auto-set date_paid if status is changed to paid
            if ($request->payout_status === 'paid' && !$request->date_paid) {
                $updateData['date_paid'] = now();
            }

            DB::table('payout')->where('id', $id)->update($updateData);
            
            $updatedPayout = DB::table('payout')
                ->leftJoin('user_tbl', DB::raw('CAST(payout.payee_id AS CHAR)'), '=', DB::raw('CAST(user_tbl.userID AS CHAR)'))
                ->select('payout.*', 'user_tbl.firstName', 'user_tbl.lastName', 'user_tbl.email')
                ->where('payout.id', $id)
                ->first();

            return response()->json([
                'success' => true,
                'message' => 'Payout updated successfully',
                'data' => $updatedPayout
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'An error occurred while updating payout',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Remove the specified payout.
     */
    public function destroy(string $id)
    {
        $payout = DB::table('payout')->where('id', $id)->first();

        if (!$payout) {
            return response()->json([
                'success' => false,
                'message' => 'Payout not found'
            ], 404);
        }

        try {
            DB::table('payout')->where('id', $id)->delete();

            return response()->json([
                'success' => true,
                'message' => 'Payout deleted successfully'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'An error occurred while deleting payout',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get payouts by status.
     */
    public function getByStatus($status)
    {
        $validator = Validator::make(['status' => $status], [
            'status' => 'required|in:pending,approved,paid,cancelled,failed'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid status. Must be: pending, approved, paid, cancelled, or failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $payouts = DB::table('payout')
                ->leftJoin('user_tbl', DB::raw('CAST(payout.payee_id AS CHAR)'), '=', DB::raw('CAST(user_tbl.userID AS CHAR)'))
                ->select('payout.*', 'user_tbl.firstName', 'user_tbl.lastName', 'user_tbl.email')
                ->where('payout.payout_status', $status)
                ->orderBy('payout.next_payout_date', 'asc')
                ->get();

            return response()->json([
                'success' => true,
                'message' => 'Payouts retrieved successfully',
                'data' => $payouts,
                'count' => $payouts->count(),
                'status' => $status
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'An error occurred while retrieving payouts',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get payouts by payee.
     */
    public function getByPayee($payeeId)
    {
        try {
            $payouts = DB::table('payout')
                ->leftJoin('user_tbl', DB::raw('CAST(payout.payee_id AS CHAR)'), '=', DB::raw('CAST(user_tbl.userID AS CHAR)'))
                ->select('payout.*', 'user_tbl.firstName', 'user_tbl.lastName', 'user_tbl.email')
                ->where('payout.payee_id', $payeeId)
                ->orderBy('payout.next_payout_date', 'desc')
                ->get();

            if ($payouts->isEmpty()) {
                return response()->json([
                    'success' => true,
                    'message' => 'No payouts found for this payee',
                    'data' => [],
                    'count' => 0
                ]);
            }

            return response()->json([
                'success' => true,
                'message' => 'Payee payouts retrieved successfully',
                'data' => $payouts,
                'count' => $payouts->count(),
                'payee_id' => $payeeId
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'An error occurred while retrieving payouts',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get payouts due within specified days.
     */
    public function getDue($days = 7)
    {
        try {
            $payouts = DB::table('payout')
                ->leftJoin('user_tbl', DB::raw('CAST(payout.payee_id AS CHAR)'), '=', DB::raw('CAST(user_tbl.userID AS CHAR)'))
                ->select('payout.*', 'user_tbl.firstName', 'user_tbl.lastName', 'user_tbl.email', 'user_tbl.phone')
                ->where('payout.payout_status', 'pending')
                ->whereDate('payout.next_payout_date', '<=', now()->addDays($days))
                ->orderBy('payout.next_payout_date', 'asc')
                ->get();

            return response()->json([
                'success' => true,
                'message' => "Payouts due within {$days} days retrieved successfully",
                'data' => $payouts,
                'count' => $payouts->count(),
                'total_amount' => $payouts->sum('next_payout'),
                'days_ahead' => $days
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'An error occurred while retrieving due payouts',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get payout statistics.
     */
    public function getStats()
    {
        try {
            $totalPayouts = DB::table('payout')->count();
            $pendingPayouts = DB::table('payout')->where('payout_status', 'pending')->count();
            $paidPayouts = DB::table('payout')->where('payout_status', 'paid')->count();
            
            $totalPending = DB::table('payout')
                ->where('payout_status', 'pending')
                ->sum('next_payout');
            
            $totalPaid = DB::table('payout')
                ->where('payout_status', 'paid')
                ->sum('next_payout');

            $statusStats = DB::table('payout')
                ->select('payout_status', DB::raw('count(*) as count'), DB::raw('sum(next_payout) as total_amount'))
                ->groupBy('payout_status')
                ->get();

            $overdue = DB::table('payout')
                ->where('payout_status', 'pending')
                ->whereDate('next_payout_date', '<', now())
                ->count();

            $dueThisWeek = DB::table('payout')
                ->where('payout_status', 'pending')
                ->whereDate('next_payout_date', '>=', now())
                ->whereDate('next_payout_date', '<=', now()->addDays(7))
                ->count();

            return response()->json([
                'success' => true,
                'message' => 'Payout statistics retrieved successfully',
                'total_payouts' => $totalPayouts,
                'pending_payouts' => $pendingPayouts,
                'paid_payouts' => $paidPayouts,
                'overdue_payouts' => $overdue,
                'due_this_week' => $dueThisWeek,
                'total_pending_amount' => round($totalPending, 2),
                'total_paid_amount' => round($totalPaid, 2),
                'status_breakdown' => $statusStats
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'An error occurred while retrieving payout statistics',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get payouts by date range.
     */
    public function getByDateRange(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',
            'date_field' => 'sometimes|in:next_payout_date,date_paid,date_created'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation error',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $dateField = $request->date_field ?? 'next_payout_date';
            
            $payouts = DB::table('payout')
                ->leftJoin('user_tbl', DB::raw('CAST(payout.payee_id AS CHAR)'), '=', DB::raw('CAST(user_tbl.userID AS CHAR)'))
                ->select('payout.*', 'user_tbl.firstName', 'user_tbl.lastName', 'user_tbl.email')
                ->whereDate("payout.{$dateField}", '>=', $request->start_date)
                ->whereDate("payout.{$dateField}", '<=', $request->end_date)
                ->orderBy("payout.{$dateField}", 'desc')
                ->get();

            return response()->json([
                'success' => true,
                'message' => 'Payouts retrieved successfully',
                'data' => $payouts,
                'count' => $payouts->count(),
                'total_amount' => $payouts->sum('next_payout'),
                'date_range' => [
                    'start' => $request->start_date,
                    'end' => $request->end_date,
                    'field' => $dateField
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'An error occurred while retrieving payouts',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}