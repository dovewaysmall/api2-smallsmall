<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Storage;

class PayoutController extends Controller
{
    /**
     * Display a listing of all payouts.
     */
    public function index()
    {
        try {
            $payouts = DB::table('payout')
                ->leftJoin('user_tbl', 'payout.landlord_id', '=', 'user_tbl.userID')
                ->select(
                    'payout.*',
                    'user_tbl.firstName as landlord_firstName',
                    'user_tbl.lastName as landlord_lastName',
                    'user_tbl.email as landlord_email',
                    'user_tbl.phone as landlord_phone'
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
     * Display the specified payout.
     */
    public function show(string $id)
    {
        try {
            $payout = DB::table('payout')
                ->leftJoin('user_tbl', 'payout.landlord_id', '=', 'user_tbl.userID')
                ->select(
                    'payout.*',
                    'user_tbl.firstName as landlord_firstName',
                    'user_tbl.lastName as landlord_lastName',
                    'user_tbl.email as landlord_email',
                    'user_tbl.phone as landlord_phone'
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
     * Store a newly created payout.
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'landlord_id' => 'required|string|max:20',
            'amount' => 'required|numeric|min:0',
            'payout_status' => 'required|in:pending,approved,disbursed',
            'upload_receipt' => 'required|string|max:100',
            'date_paid' => 'nullable|date',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation error',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $data = [
                'landlord_id' => $request->landlord_id,
                'amount' => $request->amount,
                'payout_status' => $request->payout_status,
                'upload_receipt' => $request->upload_receipt,
                'date_paid' => $request->date_paid,
            ];

            $payoutId = DB::table('payout')->insertGetId($data);

            if ($payoutId) {
                $createdPayout = DB::table('payout')
                    ->leftJoin('user_tbl', 'payout.landlord_id', '=', 'user_tbl.userID')
                    ->select(
                        'payout.*',
                        'user_tbl.firstName as landlord_firstName',
                        'user_tbl.lastName as landlord_lastName'
                    )
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
            'landlord_id' => 'sometimes|string|max:20',
            'amount' => 'sometimes|numeric|min:0',
            'payout_status' => 'sometimes|in:pending,approved,disbursed',
            'upload_receipt' => 'sometimes|string|max:100',
            'date_paid' => 'nullable|date',
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
                'landlord_id', 'amount', 'payout_status', 'upload_receipt', 'date_paid'
            ]);

            DB::table('payout')->where('id', $id)->update($updateData);
            
            $updatedPayout = DB::table('payout')
                ->leftJoin('user_tbl', 'payout.landlord_id', '=', 'user_tbl.userID')
                ->select(
                    'payout.*',
                    'user_tbl.firstName as landlord_firstName',
                    'user_tbl.lastName as landlord_lastName'
                )
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
            'status' => 'required|in:pending,approved,disbursed'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid status. Must be: pending, approved, or disbursed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $payouts = DB::table('payout')
                ->leftJoin('user_tbl', 'payout.landlord_id', '=', 'user_tbl.userID')
                ->select(
                    'payout.*',
                    'user_tbl.firstName as landlord_firstName',
                    'user_tbl.lastName as landlord_lastName'
                )
                ->where('payout.payout_status', $status)
                ->orderBy('payout.id', 'desc')
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
    public function getByLandlord($landlordId)
    {
        try {
            $payouts = DB::table('payout')
                ->leftJoin('user_tbl', 'payout.landlord_id', '=', 'user_tbl.userID')
                ->select(
                    'payout.*',
                    'user_tbl.firstName as landlord_firstName',
                    'user_tbl.lastName as landlord_lastName'
                )
                ->where('payout.landlord_id', $landlordId)
                ->orderBy('payout.id', 'desc')
                ->get();

            return response()->json([
                'success' => true,
                'message' => 'Landlord payouts retrieved successfully',
                'data' => $payouts,
                'count' => $payouts->count(),
                'landlord_id' => $landlordId
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
     * Get landlords for dropdown list.
     */
    public function getLandlords()
    {
        try {
            $landlords = DB::table('user_tbl')
                ->where('user_type', 'landlord')
                ->select('userID', 'firstName', 'lastName', 'email')
                ->orderBy('firstName', 'desc')
                ->get();

            return response()->json([
                'success' => true,
                'message' => 'Landlords retrieved successfully',
                'data' => $landlords,
                'count' => $landlords->count()
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
     * Get payout statistics.
     */
    public function getStats()
    {
        try {
            $totalPayouts = DB::table('payout')->count();
            $pendingPayouts = DB::table('payout')->where('payout_status', 'pending')->count();
            $approvedPayouts = DB::table('payout')->where('payout_status', 'approved')->count();
            $disbursedPayouts = DB::table('payout')->where('payout_status', 'disbursed')->count();

            $statusStats = DB::table('payout')
                ->select('payout_status as status_field', DB::raw('count(*) as count'))
                ->groupBy('payout_status')
                ->get();

            $totalAmount = DB::table('payout')->sum('amount');
            $disbursedAmount = DB::table('payout')
                ->where('payout_status', 'disbursed')
                ->sum('amount');

            $monthlyPayouts = DB::table('payout')
                ->where('date_paid', '>=', now()->subDays(30))
                ->count();

            return response()->json([
                'success' => true,
                'message' => 'Payout statistics retrieved successfully',
                'total_payouts' => $totalPayouts,
                'pending_payouts' => $pendingPayouts,
                'approved_payouts' => $approvedPayouts,
                'disbursed_payouts' => $disbursedPayouts,
                'monthly_payouts' => $monthlyPayouts,
                'total_amount' => round($totalAmount, 2),
                'disbursed_amount' => round($disbursedAmount, 2),
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
}