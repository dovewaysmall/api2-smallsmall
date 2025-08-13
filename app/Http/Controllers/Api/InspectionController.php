<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class InspectionController extends Controller
{
    /**
     * Display a listing of all inspections (Improved version of your original).
     */
    public function index()
    {
        try {
            $inspections = DB::table('inspection_tbl')
                ->join('user_tbl', DB::raw('CAST(inspection_tbl.userID AS CHAR)'), '=', DB::raw('CAST(user_tbl.userID AS CHAR)'))
                ->join('property_tbl', DB::raw('CAST(inspection_tbl.propertyID AS CHAR)'), '=', DB::raw('CAST(property_tbl.propertyID AS CHAR)'))
                ->leftJoin('admin_tbl', DB::raw('CAST(inspection_tbl.assigned_tsr AS CHAR)'), '=', DB::raw('CAST(admin_tbl.adminID AS CHAR)'))
                ->select(
                    // User information
                    'user_tbl.firstName',
                    'user_tbl.lastName', 
                    'user_tbl.email',
                    'user_tbl.phone',
                    'user_tbl.verified',
                    // Inspection details
                    'inspection_tbl.id',
                    'inspection_tbl.inspectionID',
                    'inspection_tbl.userID',
                    'inspection_tbl.propertyID',
                    'inspection_tbl.inspectionDate',
                    'inspection_tbl.updated_inspection_date',
                    'inspection_tbl.inspectionType',
                    'inspection_tbl.assigned_tsr',
                    'inspection_tbl.inspection_status',
                    'inspection_tbl.date_inspection_completed_canceled',
                    'inspection_tbl.inspection_remarks',
                    'inspection_tbl.comment',
                    'inspection_tbl.follow_up_stage',
                    'inspection_tbl.customer_inspec_feedback',
                    'inspection_tbl.cx_feedback_details',
                    'inspection_tbl.platform',
                    'inspection_tbl.dateOfEntry',
                    // Property information
                    'property_tbl.propertyTitle',
                    // TSR information
                    'admin_tbl.firstName as tsr_firstName',
                    'admin_tbl.lastName as tsr_lastName'
                )
                ->orderBy('inspection_tbl.id', 'desc')
                ->get();

            return response()->json([
                'success' => true,
                'message' => 'Inspections retrieved successfully',
                'data' => $inspections,
                'count' => $inspections->count()
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'An error occurred while retrieving inspections',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get specific inspection by ID (Improved version of your original).
     */
    public function show(string $id)
    {
        try {
            $inspection = DB::table('inspection_tbl')
                ->join('user_tbl', DB::raw('CAST(inspection_tbl.userID AS CHAR)'), '=', DB::raw('CAST(user_tbl.userID AS CHAR)'))
                ->join('property_tbl', DB::raw('CAST(inspection_tbl.propertyID AS CHAR)'), '=', DB::raw('CAST(property_tbl.propertyID AS CHAR)'))
                ->leftJoin('admin_tbl', DB::raw('CAST(inspection_tbl.assigned_tsr AS CHAR)'), '=', DB::raw('CAST(admin_tbl.adminID AS CHAR)'))
                ->select(
                    // User information
                    'user_tbl.firstName',
                    'user_tbl.lastName', 
                    'user_tbl.email',
                    'user_tbl.phone',
                    'user_tbl.verified',
                    // Inspection details
                    'inspection_tbl.id',
                    'inspection_tbl.inspectionID',
                    'inspection_tbl.userID',
                    'inspection_tbl.propertyID',
                    'inspection_tbl.inspectionDate',
                    'inspection_tbl.updated_inspection_date',
                    'inspection_tbl.inspectionType',
                    'inspection_tbl.assigned_tsr',
                    'inspection_tbl.inspection_status',
                    'inspection_tbl.date_inspection_completed_canceled',
                    'inspection_tbl.inspection_remarks',
                    'inspection_tbl.comment',
                    'inspection_tbl.follow_up_stage',
                    'inspection_tbl.customer_inspec_feedback',
                    'inspection_tbl.cx_feedback_details',
                    'inspection_tbl.platform',
                    'inspection_tbl.dateOfEntry',
                    // Property information
                    'property_tbl.propertyTitle',
                    // TSR information
                    'admin_tbl.firstName as tsr_firstName',
                    'admin_tbl.lastName as tsr_lastName'
                )
                ->where('inspection_tbl.id', $id)
                ->first();

            if (!$inspection) {
                return response()->json([
                    'success' => false,
                    'message' => 'Inspection not found'
                ], 404);
            }

            // Get all available TSRs from admin_tbl
            $allTsrs = DB::table('admin_tbl')
                ->select('adminID', 'firstName', 'lastName', 'email', 'phone', 'role', 'department', 'staff_dept', 'status')
                ->where('staff_dept', 'tsr')
                ->where('status', 'active')
                ->orderBy('firstName', 'asc')
                ->get();

            // Mark the currently assigned TSR and separate others
            $assignedTsr = null;
            $availableTsrs = [];

            foreach ($allTsrs as $tsr) {
                $tsrData = [
                    'adminID' => $tsr->adminID,
                    'firstName' => $tsr->firstName,
                    'lastName' => $tsr->lastName,
                    'email' => $tsr->email,
                    'phone' => $tsr->phone,
                    'role' => $tsr->role,
                    'department' => $tsr->department,
                    'status' => $tsr->status,
                    'is_assigned' => $tsr->adminID == $inspection->assigned_tsr
                ];

                if ($tsr->adminID == $inspection->assigned_tsr) {
                    $assignedTsr = $tsrData;
                } else {
                    $availableTsrs[] = $tsrData;
                }
            }

            return response()->json([
                'success' => true,
                'message' => 'Inspection retrieved successfully',
                'data' => $inspection,
                'assigned_tsr' => $assignedTsr,
                'available_tsrs' => $availableTsrs,
                'tsr_summary' => [
                    'total_available_tsrs' => count($allTsrs),
                    'has_assigned_tsr' => !is_null($assignedTsr),
                    'unassigned_tsrs_count' => count($availableTsrs)
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'An error occurred while retrieving inspection',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Create a new inspection.
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'inspectionID' => 'required|string|max:255',
            'propertyID' => 'required|string|max:255',
            'userID' => 'required|string|max:255',
            'inspectionDate' => 'required|date',
            'inspectionType' => 'required|in:Physical,Virtual,Remote',
            'assigned_tsr' => 'nullable|string|max:255',
            'inspection_status' => 'nullable|string|max:100',
            'inspection_remarks' => 'nullable|string|max:1000',
            'comment' => 'nullable|string|max:1000',
            'follow_up_stage' => 'nullable|string|max:255',
            'customer_inspec_feedback' => 'nullable|string|max:1000',
            'cx_feedback_details' => 'nullable|string|max:1000',
            'platform' => 'nullable|string|max:100',
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
                'inspectionID' => $request->inspectionID,
                'propertyID' => $request->propertyID,
                'userID' => $request->userID,
                'inspectionDate' => $request->inspectionDate,
                'inspectionType' => $request->inspectionType,
                'assigned_tsr' => $request->assigned_tsr,
                'inspection_status' => $request->inspection_status,
                'inspection_remarks' => $request->inspection_remarks,
                'comment' => $request->comment,
                'follow_up_stage' => $request->follow_up_stage,
                'customer_inspec_feedback' => $request->customer_inspec_feedback,
                'cx_feedback_details' => $request->cx_feedback_details,
                'platform' => $request->platform,
                'dateOfEntry' => now(),
            ];

            $inspectionId = DB::table('inspection_tbl')->insertGetId($data);
            $inspection = DB::table('inspection_tbl')->where('id', $inspectionId)->first();

            return response()->json([
                'success' => true,
                'message' => 'Inspection created successfully',
                'data' => $inspection,
                'id' => $inspectionId
            ], 201);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'An error occurred while creating inspection',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update the specified inspection.
     */
    public function update(Request $request, string $id)
    {
        $inspection = DB::table('inspection_tbl')->where('id', $id)->first();

        if (!$inspection) {
            return response()->json([
                'success' => false,
                'message' => 'Inspection not found'
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'inspectionDate' => 'sometimes|date',
            'updated_inspection_date' => 'sometimes|date',
            'inspectionType' => 'sometimes|in:Physical,Virtual,Remote',
            'assigned_tsr' => 'nullable|string|max:255',
            'inspection_status' => 'nullable|string|max:100',
            'date_inspection_completed_canceled' => 'nullable|date',
            'inspection_remarks' => 'nullable|string|max:1000',
            'comment' => 'nullable|string|max:1000',
            'follow_up_stage' => 'nullable|string|max:255',
            'customer_inspec_feedback' => 'nullable|string|max:1000',
            'cx_feedback_details' => 'nullable|string|max:1000',
            'platform' => 'nullable|string|max:100',
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
                'inspectionDate', 'updated_inspection_date', 'inspectionType', 
                'assigned_tsr', 'inspection_status', 'date_inspection_completed_canceled',
                'inspection_remarks', 'comment', 'follow_up_stage', 
                'customer_inspec_feedback', 'cx_feedback_details', 'platform'
            ]);

            DB::table('inspection_tbl')->where('id', $id)->update($updateData);
            $updatedInspection = DB::table('inspection_tbl')->where('id', $id)->first();

            return response()->json([
                'success' => true,
                'message' => 'Inspection updated successfully',
                'data' => $updatedInspection
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'An error occurred while updating inspection',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Remove the specified inspection.
     */
    public function destroy(string $id)
    {
        $inspection = DB::table('inspection_tbl')->where('id', $id)->first();

        if (!$inspection) {
            return response()->json([
                'success' => false,
                'message' => 'Inspection not found'
            ], 404);
        }

        try {
            DB::table('inspection_tbl')->where('id', $id)->delete();

            return response()->json([
                'success' => true,
                'message' => 'Inspection deleted successfully'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'An error occurred while deleting inspection',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get inspections by status.
     */
    public function getByStatus($status)
    {
        try {
            $inspections = DB::table('inspection_tbl')
                ->join('user_tbl', DB::raw('CAST(inspection_tbl.userID AS CHAR)'), '=', DB::raw('CAST(user_tbl.userID AS CHAR)'))
                ->join('property_tbl', DB::raw('CAST(inspection_tbl.propertyID AS CHAR)'), '=', DB::raw('CAST(property_tbl.propertyID AS CHAR)'))
                ->leftJoin('admin_tbl', DB::raw('CAST(inspection_tbl.assigned_tsr AS CHAR)'), '=', DB::raw('CAST(admin_tbl.adminID AS CHAR)'))
                ->select(
                    'user_tbl.firstName', 'user_tbl.lastName', 'user_tbl.email', 'user_tbl.phone',
                    'inspection_tbl.*', 'property_tbl.propertyTitle',
                    'admin_tbl.firstName as tsr_firstName', 'admin_tbl.lastName as tsr_lastName'
                )
                ->where('inspection_tbl.inspection_status', $status)
                ->orderBy('inspection_tbl.id', 'desc')
                ->get();

            return response()->json([
                'success' => true,
                'message' => 'Inspections retrieved successfully',
                'data' => $inspections,
                'count' => $inspections->count(),
                'status' => $status
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'An error occurred while retrieving inspections',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get inspections by type.
     */
    public function getByType($type)
    {
        $validator = Validator::make(['type' => $type], [
            'type' => 'required|in:Physical,Virtual,Remote'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid inspection type. Must be Physical, Virtual, or Remote',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $inspections = DB::table('inspection_tbl')
                ->join('user_tbl', DB::raw('CAST(inspection_tbl.userID AS CHAR)'), '=', DB::raw('CAST(user_tbl.userID AS CHAR)'))
                ->join('property_tbl', DB::raw('CAST(inspection_tbl.propertyID AS CHAR)'), '=', DB::raw('CAST(property_tbl.propertyID AS CHAR)'))
                ->leftJoin('admin_tbl', DB::raw('CAST(inspection_tbl.assigned_tsr AS CHAR)'), '=', DB::raw('CAST(admin_tbl.adminID AS CHAR)'))
                ->select(
                    'user_tbl.firstName', 'user_tbl.lastName', 'user_tbl.email',
                    'inspection_tbl.*', 'property_tbl.propertyTitle',
                    'admin_tbl.firstName as tsr_firstName', 'admin_tbl.lastName as tsr_lastName'
                )
                ->where('inspection_tbl.inspectionType', $type)
                ->orderBy('inspection_tbl.inspectionDate', 'desc')
                ->get();

            return response()->json([
                'success' => true,
                'message' => 'Inspections retrieved successfully',
                'data' => $inspections,
                'count' => $inspections->count(),
                'inspection_type' => $type
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'An error occurred while retrieving inspections',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get inspections by user.
     */
    public function getByUser($userID)
    {
        try {
            $inspections = DB::table('inspection_tbl')
                ->join('user_tbl', DB::raw('CAST(inspection_tbl.userID AS CHAR)'), '=', DB::raw('CAST(user_tbl.userID AS CHAR)'))
                ->join('property_tbl', DB::raw('CAST(inspection_tbl.propertyID AS CHAR)'), '=', DB::raw('CAST(property_tbl.propertyID AS CHAR)'))
                ->leftJoin('admin_tbl', DB::raw('CAST(inspection_tbl.assigned_tsr AS CHAR)'), '=', DB::raw('CAST(admin_tbl.adminID AS CHAR)'))
                ->select(
                    'user_tbl.firstName', 'user_tbl.lastName', 'user_tbl.email',
                    'inspection_tbl.*', 'property_tbl.propertyTitle',
                    'admin_tbl.firstName as tsr_firstName', 'admin_tbl.lastName as tsr_lastName'
                )
                ->where('inspection_tbl.userID', $userID)
                ->orderBy('inspection_tbl.inspectionDate', 'desc')
                ->get();

            if ($inspections->isEmpty()) {
                return response()->json([
                    'success' => true,
                    'message' => 'No inspections found for this user',
                    'data' => [],
                    'count' => 0
                ]);
            }

            return response()->json([
                'success' => true,
                'message' => 'User inspections retrieved successfully',
                'data' => $inspections,
                'count' => $inspections->count(),
                'userID' => $userID
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'An error occurred while retrieving user inspections',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get inspection count.
     */
    public function count()
    {
        try {
            // Count all records in inspection_tbl
            $totalCount = DB::table('inspection_tbl')->count('*');
            
            return response()->json([
                'success' => true,
                'message' => 'Inspection count retrieved successfully',
                'count' => $totalCount,
                'table' => 'inspection_tbl'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'An error occurred while retrieving inspection count',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get inspection count for current month.
     */
    public function monthlyCount()
    {
        try {
            $count = DB::table('inspection_tbl')
                ->whereMonth('inspectionDate', now()->month)
                ->whereYear('inspectionDate', now()->year)
                ->count();
            
            return response()->json([
                'success' => true,
                'message' => 'Monthly inspection count retrieved successfully',
                'count' => $count,
                'month' => now()->format('F Y')
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'An error occurred while retrieving monthly inspection count',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get inspection statistics.
     */
    public function getStats()
    {
        try {
            $totalInspections = DB::table('inspection_tbl')->count();
            
            $typeStats = DB::table('inspection_tbl')
                ->select('inspectionType', DB::raw('count(*) as count'))
                ->groupBy('inspectionType')
                ->get();

            $statusStats = DB::table('inspection_tbl')
                ->select('inspection_status', DB::raw('count(*) as count'))
                ->whereNotNull('inspection_status')
                ->groupBy('inspection_status')
                ->get();

            $recentInspections = DB::table('inspection_tbl')
                ->where('inspectionDate', '>=', now()->subDays(30))
                ->count();

            $upcomingInspections = DB::table('inspection_tbl')
                ->where('inspectionDate', '>=', now())
                ->count();

            return response()->json([
                'success' => true,
                'message' => 'Inspection statistics retrieved successfully',
                'total_inspections' => $totalInspections,
                'recent_inspections_30_days' => $recentInspections,
                'upcoming_inspections' => $upcomingInspections,
                'inspection_type_breakdown' => $typeStats,
                'inspection_status_breakdown' => $statusStats
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'An error occurred while retrieving inspection statistics',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get inspections by date range.
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
            $inspections = DB::table('inspection_tbl')
                ->join('user_tbl', DB::raw('CAST(inspection_tbl.userID AS CHAR)'), '=', DB::raw('CAST(user_tbl.userID AS CHAR)'))
                ->join('property_tbl', DB::raw('CAST(inspection_tbl.propertyID AS CHAR)'), '=', DB::raw('CAST(property_tbl.propertyID AS CHAR)'))
                ->leftJoin('admin_tbl', DB::raw('CAST(inspection_tbl.assigned_tsr AS CHAR)'), '=', DB::raw('CAST(admin_tbl.adminID AS CHAR)'))
                ->select(
                    'user_tbl.firstName', 'user_tbl.lastName', 'user_tbl.email',
                    'inspection_tbl.*', 'property_tbl.propertyTitle',
                    'admin_tbl.firstName as tsr_firstName', 'admin_tbl.lastName as tsr_lastName'
                )
                ->whereDate('inspection_tbl.inspectionDate', '>=', $request->start_date)
                ->whereDate('inspection_tbl.inspectionDate', '<=', $request->end_date)
                ->orderBy('inspection_tbl.inspectionDate', 'desc')
                ->get();

            return response()->json([
                'success' => true,
                'message' => 'Inspections retrieved successfully',
                'data' => $inspections,
                'count' => $inspections->count(),
                'date_range' => [
                    'start' => $request->start_date,
                    'end' => $request->end_date
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'An error occurred while retrieving inspections',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get inspections scheduled for this week.
     */
    public function getThisWeek()
    {
        try {
            $startOfWeek = now()->startOfWeek();
            $endOfWeek = now()->endOfWeek();

            $inspections = DB::table('inspection_tbl')
                ->join('user_tbl', DB::raw('CAST(inspection_tbl.userID AS CHAR)'), '=', DB::raw('CAST(user_tbl.userID AS CHAR)'))
                ->join('property_tbl', DB::raw('CAST(inspection_tbl.propertyID AS CHAR)'), '=', DB::raw('CAST(property_tbl.propertyID AS CHAR)'))
                ->leftJoin('admin_tbl', DB::raw('CAST(inspection_tbl.assigned_tsr AS CHAR)'), '=', DB::raw('CAST(admin_tbl.adminID AS CHAR)'))
                ->select(
                    'user_tbl.firstName',
                    'user_tbl.lastName', 
                    'user_tbl.email',
                    'user_tbl.phone',
                    'user_tbl.verified',
                    'inspection_tbl.id',
                    'inspection_tbl.inspectionID',
                    'inspection_tbl.userID',
                    'inspection_tbl.propertyID',
                    'inspection_tbl.inspectionDate',
                    'inspection_tbl.updated_inspection_date',
                    'inspection_tbl.inspectionType',
                    'inspection_tbl.assigned_tsr',
                    'inspection_tbl.inspection_status',
                    'inspection_tbl.date_inspection_completed_canceled',
                    'inspection_tbl.inspection_remarks',
                    'inspection_tbl.comment',
                    'inspection_tbl.follow_up_stage',
                    'inspection_tbl.customer_inspec_feedback',
                    'inspection_tbl.cx_feedback_details',
                    'inspection_tbl.platform',
                    'inspection_tbl.dateOfEntry',
                    'property_tbl.propertyTitle',
                    'admin_tbl.firstName as tsr_firstName',
                    'admin_tbl.lastName as tsr_lastName'
                )
                ->whereDate('inspection_tbl.inspectionDate', '>=', $startOfWeek)
                ->whereDate('inspection_tbl.inspectionDate', '<=', $endOfWeek)
                ->orderBy('inspection_tbl.inspectionDate', 'desc')
                ->get();

            return response()->json([
                'success' => true,
                'message' => 'Inspections for this week retrieved successfully',
                'data' => $inspections,
                'count' => $inspections->count(),
                'period' => [
                    'start' => $startOfWeek->format('Y-m-d H:i:s'),
                    'end' => $endOfWeek->format('Y-m-d H:i:s')
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'An error occurred while retrieving inspections for this week',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get inspections scheduled for this month.
     */
    public function getThisMonth()
    {
        try {
            $startOfMonth = now()->startOfMonth();
            $endOfMonth = now()->endOfMonth();

            $inspections = DB::table('inspection_tbl')
                ->join('user_tbl', DB::raw('CAST(inspection_tbl.userID AS CHAR)'), '=', DB::raw('CAST(user_tbl.userID AS CHAR)'))
                ->join('property_tbl', DB::raw('CAST(inspection_tbl.propertyID AS CHAR)'), '=', DB::raw('CAST(property_tbl.propertyID AS CHAR)'))
                ->leftJoin('admin_tbl', DB::raw('CAST(inspection_tbl.assigned_tsr AS CHAR)'), '=', DB::raw('CAST(admin_tbl.adminID AS CHAR)'))
                ->select(
                    'user_tbl.firstName',
                    'user_tbl.lastName', 
                    'user_tbl.email',
                    'user_tbl.phone',
                    'user_tbl.verified',
                    'inspection_tbl.id',
                    'inspection_tbl.inspectionID',
                    'inspection_tbl.userID',
                    'inspection_tbl.propertyID',
                    'inspection_tbl.inspectionDate',
                    'inspection_tbl.updated_inspection_date',
                    'inspection_tbl.inspectionType',
                    'inspection_tbl.assigned_tsr',
                    'inspection_tbl.inspection_status',
                    'inspection_tbl.date_inspection_completed_canceled',
                    'inspection_tbl.inspection_remarks',
                    'inspection_tbl.comment',
                    'inspection_tbl.follow_up_stage',
                    'inspection_tbl.customer_inspec_feedback',
                    'inspection_tbl.cx_feedback_details',
                    'inspection_tbl.platform',
                    'inspection_tbl.dateOfEntry',
                    'property_tbl.propertyTitle',
                    'admin_tbl.firstName as tsr_firstName',
                    'admin_tbl.lastName as tsr_lastName'
                )
                ->whereDate('inspection_tbl.inspectionDate', '>=', $startOfMonth)
                ->whereDate('inspection_tbl.inspectionDate', '<=', $endOfMonth)
                ->orderBy('inspection_tbl.inspectionDate', 'desc')
                ->get();

            return response()->json([
                'success' => true,
                'message' => 'Inspections for this month retrieved successfully',
                'data' => $inspections,
                'count' => $inspections->count(),
                'period' => [
                    'start' => $startOfMonth->format('Y-m-d H:i:s'),
                    'end' => $endOfMonth->format('Y-m-d H:i:s')
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'An error occurred while retrieving inspections for this month',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get inspections scheduled for this year.
     */
    public function getThisYear()
    {
        try {
            $startOfYear = now()->startOfYear();
            $endOfYear = now()->endOfYear();

            $inspections = DB::table('inspection_tbl')
                ->join('user_tbl', DB::raw('CAST(inspection_tbl.userID AS CHAR)'), '=', DB::raw('CAST(user_tbl.userID AS CHAR)'))
                ->join('property_tbl', DB::raw('CAST(inspection_tbl.propertyID AS CHAR)'), '=', DB::raw('CAST(property_tbl.propertyID AS CHAR)'))
                ->leftJoin('admin_tbl', DB::raw('CAST(inspection_tbl.assigned_tsr AS CHAR)'), '=', DB::raw('CAST(admin_tbl.adminID AS CHAR)'))
                ->select(
                    'user_tbl.firstName',
                    'user_tbl.lastName', 
                    'user_tbl.email',
                    'user_tbl.phone',
                    'user_tbl.verified',
                    'inspection_tbl.id',
                    'inspection_tbl.inspectionID',
                    'inspection_tbl.userID',
                    'inspection_tbl.propertyID',
                    'inspection_tbl.inspectionDate',
                    'inspection_tbl.updated_inspection_date',
                    'inspection_tbl.inspectionType',
                    'inspection_tbl.assigned_tsr',
                    'inspection_tbl.inspection_status',
                    'inspection_tbl.date_inspection_completed_canceled',
                    'inspection_tbl.inspection_remarks',
                    'inspection_tbl.comment',
                    'inspection_tbl.follow_up_stage',
                    'inspection_tbl.customer_inspec_feedback',
                    'inspection_tbl.cx_feedback_details',
                    'inspection_tbl.platform',
                    'inspection_tbl.dateOfEntry',
                    'property_tbl.propertyTitle',
                    'admin_tbl.firstName as tsr_firstName',
                    'admin_tbl.lastName as tsr_lastName'
                )
                ->whereDate('inspection_tbl.inspectionDate', '>=', $startOfYear)
                ->whereDate('inspection_tbl.inspectionDate', '<=', $endOfYear)
                ->orderBy('inspection_tbl.inspectionDate', 'desc')
                ->get();

            return response()->json([
                'success' => true,
                'message' => 'Inspections for this year retrieved successfully',
                'data' => $inspections,
                'count' => $inspections->count(),
                'period' => [
                    'start' => $startOfYear->format('Y-m-d H:i:s'),
                    'end' => $endOfYear->format('Y-m-d H:i:s')
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'An error occurred while retrieving inspections for this year',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
