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
                ->orderBy('payout.next_payout_date', 'desc')
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
     * Store a newly created payout.
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'payee_id' => 'required|string|max:255',
            'amount' => 'required|numeric|min:0',
            'next_payout_date' => 'required|date',
            'payout_status' => 'nullable|in:pending,approved,disbursed',
            'authorized_by' => 'nullable|string|max:255',
            'date_paid' => 'nullable|date',
            'receipts' => 'nullable|array|max:5',
            'receipts.*' => 'file|mimes:pdf,jpeg,png,jpg,gif|max:10240',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation error',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            // Handle receipt uploads
            $receiptFolder = null;
            $receiptPaths = [];
            
            if ($request->hasFile('receipts')) {
                $receipts = $request->file('receipts');
                $receiptFolder = 'payout_' . time() . '_' . mt_rand(1000, 9999);
                
                foreach ($receipts as $index => $receipt) {
                    $receiptName = $receiptFolder . '_' . $index . '.' . $receipt->getClientOriginalExtension();
                    $receiptPath = $receipt->storeAs('payout_receipts/' . $receiptFolder, $receiptName, 'public');
                    $receiptPaths[] = $receiptPath;
                }
            }

            $nextId = DB::table('payout')->max('id') + 1;
            
            $data = [
                'id' => $nextId,
                'payee_id' => $request->payee_id,
                'amount' => $request->amount,
                'next_payout' => $request->amount, // Keep for backward compatibility
                'next_payout_date' => $request->next_payout_date,
                'payout_status' => $request->payout_status ?? 'pending',
                'authorized_by' => $request->authorized_by,
                'date_paid' => $request->date_paid,
                'receipt_folder' => $receiptFolder,
                'receipt_paths' => !empty($receiptPaths) ? json_encode($receiptPaths) : null,
            ];

            $inserted = DB::table('payout')->insert($data);
            $payoutId = $inserted ? $nextId : false;

            if ($payoutId) {
                $createdPayout = DB::table('payout')
                    ->leftJoin('user_tbl', DB::raw('CAST(payout.payee_id AS CHAR)'), '=', DB::raw('CAST(user_tbl.userID AS CHAR)'))
                    ->select(
                        'payout.*',
                        'user_tbl.firstName as payee_firstName',
                        'user_tbl.lastName as payee_lastName'
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
            'payee_id' => 'sometimes|string|max:255',
            'amount' => 'sometimes|numeric|min:0',
            'next_payout_date' => 'sometimes|date',
            'payout_status' => 'sometimes|in:pending,approved,disbursed',
            'authorized_by' => 'nullable|string|max:255',
            'date_paid' => 'nullable|date',
            'receipts' => 'nullable|array|max:5',
            'receipts.*' => 'file|mimes:pdf,jpeg,png,jpg,gif|max:10240',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation error',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            // Handle new receipt uploads if provided
            $receiptFolder = $payout->receipt_folder;
            $existingReceiptPaths = json_decode($payout->receipt_paths ?? '[]', true);
            $newReceiptPaths = [];
            
            if ($request->hasFile('receipts')) {
                $receipts = $request->file('receipts');
                if (!$receiptFolder) {
                    $receiptFolder = 'payout_' . time() . '_' . mt_rand(1000, 9999);
                }
                
                foreach ($receipts as $index => $receipt) {
                    $receiptName = $receiptFolder . '_' . time() . '_' . $index . '.' . $receipt->getClientOriginalExtension();
                    $receiptPath = $receipt->storeAs('payout_receipts/' . $receiptFolder, $receiptName, 'public');
                    $newReceiptPaths[] = $receiptPath;
                }
                
                // Combine existing and new receipts
                $allReceiptPaths = array_merge($existingReceiptPaths, $newReceiptPaths);
            } else {
                $allReceiptPaths = $existingReceiptPaths;
            }

            $updateData = $request->only([
                'payee_id', 'amount', 'next_payout_date', 'payout_status', 'authorized_by', 'date_paid'
            ]);

            // Sync amount with next_payout for backward compatibility
            if (isset($updateData['amount'])) {
                $updateData['next_payout'] = $updateData['amount'];
            }

            // Update receipt data if new receipts were uploaded
            if (!empty($newReceiptPaths)) {
                $updateData['receipt_folder'] = $receiptFolder;
                $updateData['receipt_paths'] = json_encode($allReceiptPaths);
            }

            DB::table('payout')->where('id', $id)->update($updateData);
            
            $updatedPayout = DB::table('payout')
                ->leftJoin('user_tbl', DB::raw('CAST(payout.payee_id AS CHAR)'), '=', DB::raw('CAST(user_tbl.userID AS CHAR)'))
                ->leftJoin('user_tbl as authorizer', DB::raw('CAST(payout.authorized_by AS CHAR)'), '=', DB::raw('CAST(authorizer.userID AS CHAR)'))
                ->select(
                    'payout.*',
                    'user_tbl.firstName as payee_firstName',
                    'user_tbl.lastName as payee_lastName',
                    'authorizer.firstName as authorizer_firstName',
                    'authorizer.lastName as authorizer_lastName'
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
            // Delete receipts if they exist
            if ($payout->receipt_folder) {
                Storage::disk('public')->deleteDirectory('payout_receipts/' . $payout->receipt_folder);
            }

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
                ->leftJoin('user_tbl', DB::raw('CAST(payout.payee_id AS CHAR)'), '=', DB::raw('CAST(user_tbl.userID AS CHAR)'))
                ->leftJoin('user_tbl as authorizer', DB::raw('CAST(payout.authorized_by AS CHAR)'), '=', DB::raw('CAST(authorizer.userID AS CHAR)'))
                ->select(
                    'payout.*',
                    'user_tbl.firstName as payee_firstName',
                    'user_tbl.lastName as payee_lastName',
                    'authorizer.firstName as authorizer_firstName',
                    'authorizer.lastName as authorizer_lastName'
                )
                ->where('payout.payout_status', $status)
                ->orderBy('payout.next_payout_date', 'desc')
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
                ->leftJoin('user_tbl as authorizer', DB::raw('CAST(payout.authorized_by AS CHAR)'), '=', DB::raw('CAST(authorizer.userID AS CHAR)'))
                ->select(
                    'payout.*',
                    'user_tbl.firstName as payee_firstName',
                    'user_tbl.lastName as payee_lastName',
                    'authorizer.firstName as authorizer_firstName',
                    'authorizer.lastName as authorizer_lastName'
                )
                ->where('payout.payee_id', $payeeId)
                ->orderBy('payout.next_payout_date', 'desc')
                ->get();

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
     * Get landlords for dropdown list.
     */
    public function getLandlords()
    {
        try {
            $landlords = DB::table('user_tbl')
                ->where('user_type', 'landlord')
                ->select('userID', 'firstName', 'lastName', 'email')
                ->orderBy('firstName', 'asc')
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
                ->where('next_payout_date', '>=', now()->subDays(30))
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