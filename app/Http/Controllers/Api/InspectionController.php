<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class InspectionController extends Controller
{
    /**
     * Get TSR management data for inspection responses.
     */
    private function getTsrManagementData($assignedTsrId = null)
    {
        // Get all available TSRs from admin_tbl
        $allTsrs = DB::table('admin_tbl')
            ->select('adminID', 'firstName', 'lastName', 'email', 'phone', 'staff_dept', 'status')
            ->where('staff_dept', 'tsr')
            ->where('status', 'active')
            ->orderBy('firstName', 'desc')
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
                'staff_dept' => $tsr->staff_dept,
                'status' => $tsr->status,
                'is_assigned' => $tsr->adminID == $assignedTsrId
            ];

            if ($tsr->adminID == $assignedTsrId) {
                $assignedTsr = $tsrData;
            } else {
                $availableTsrs[] = $tsrData;
            }
        }

        return [
            'assigned_tsr' => $assignedTsr,
            'available_tsrs' => $availableTsrs,
            'tsr_summary' => [
                'total_available_tsrs' => count($allTsrs),
                'has_assigned_tsr' => !is_null($assignedTsr),
                'unassigned_tsrs_count' => count($availableTsrs)
            ]
        ];
    }
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

            // Get TSR management data
            $tsrData = $this->getTsrManagementData();

            return response()->json([
                'success' => true,
                'message' => 'Inspections retrieved successfully',
                'data' => $inspections,
                'count' => $inspections->count(),
                'assigned_tsr' => $tsrData['assigned_tsr'],
                'available_tsrs' => $tsrData['available_tsrs'],
                'tsr_summary' => $tsrData['tsr_summary']
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

            // Get TSR management data
            $tsrData = $this->getTsrManagementData($inspection->assigned_tsr);

            return response()->json([
                'success' => true,
                'message' => 'Inspection retrieved successfully',
                'data' => $inspection,
                'assigned_tsr' => $tsrData['assigned_tsr'],
                'available_tsrs' => $tsrData['available_tsrs'],
                'tsr_summary' => $tsrData['tsr_summary']
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
            'propertyID' => 'required|string|max:20',
            'userID' => 'required|string|max:20',
            'inspectionDate' => 'required|date',
            'inspectionType' => 'required|in:Physical,Virtual,Remote',
            'assigned_tsr' => 'nullable|string|max:10',
            'inspection_status' => 'nullable|in:pending-not-assigned,pending-assigned,completed,canceled,apartment-not-available,multiple-bookings,did-not-show-up',
            'inspection_remarks' => 'nullable|in:interested,uninterested,indecisive,rescheduled',
            'comment' => 'nullable|string',
            'follow_up_stage' => 'nullable|string|max:100',
            'customer_inspec_feedback' => 'nullable|string',
            'cx_feedback_details' => 'nullable|string',
            'platform' => 'nullable|string|max:50',
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
        // Force log the request for debugging
        error_log("INSPECTION UPDATE REQUEST - ID: $id, Data: " . json_encode($request->all()));
        
        $inspection = DB::table('inspection_tbl')->where('id', $id)->first();

        if (!$inspection) {
            return response()->json([
                'success' => false,
                'message' => 'Inspection not found'
            ], 404);
        }

        // Custom validation to handle ENUM fields more gracefully
        $rules = [
            'inspectionDate' => 'sometimes|date',
            'updated_inspection_date' => 'sometimes|nullable|date',
            'assigned_tsr' => 'sometimes|nullable|string|max:10',
            'date_inspection_completed_canceled' => 'sometimes|nullable|date',
            'comment' => 'sometimes|nullable|string',
            'follow_up_stage' => 'sometimes|nullable|string|max:100',
            'customer_inspec_feedback' => 'sometimes|nullable|string',
            'cx_feedback_details' => 'sometimes|nullable|string',
            'platform' => 'sometimes|nullable|string|max:50',
        ];

        // Add conditional validation for inspectionType
        if ($request->has('inspectionType') && !empty($request->inspectionType)) {
            $rules['inspectionType'] = 'in:Physical,Virtual,Remote';
        }

        // Add conditional validation for ENUM fields
        if ($request->has('inspection_status') && !empty($request->inspection_status)) {
            $rules['inspection_status'] = 'in:pending-not-assigned,pending-assigned,completed,canceled,apartment-not-available,multiple-bookings,did-not-show-up';
        }

        if ($request->has('inspection_remarks') && !empty($request->inspection_remarks)) {
            $rules['inspection_remarks'] = 'in:interested,uninterested,indecisive,rescheduled';
        }

        $validator = Validator::make($request->all(), $rules);

        if ($validator->fails()) {
            // Force log validation errors
            error_log("VALIDATION FAILED - ID: $id, Errors: " . json_encode($validator->errors()->toArray()));
            
            Log::warning('Inspection update validation failed', [
                'inspection_id' => $id,
                'validation_errors' => $validator->errors()->toArray(),
                'request_data' => $request->all()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Validation error. Please check the field values.',
                'errors' => $validator->errors(),
                'valid_inspection_status_values' => [
                    'pending-not-assigned', 'pending-assigned', 'completed', 
                    'canceled', 'apartment-not-available', 'multiple-bookings', 
                    'did-not-show-up'
                ],
                'valid_inspection_remarks_values' => [
                    'interested', 'uninterested', 'indecisive', 'rescheduled'
                ]
            ], 422);
        }

        try {
            // Build update data more carefully to handle ENUM fields
            $updateData = [];
            $allowedFields = [
                'inspectionDate', 'updated_inspection_date', 'inspectionType', 
                'assigned_tsr', 'inspection_status', 'date_inspection_completed_canceled',
                'inspection_remarks', 'comment', 'follow_up_stage', 
                'customer_inspec_feedback', 'cx_feedback_details', 'platform'
            ];

            foreach ($allowedFields as $field) {
                if ($request->has($field)) {
                    $value = $request->input($field);
                    
                    // Only include non-empty values or explicitly null values
                    if ($value !== null && $value !== '') {
                        $updateData[$field] = $value;
                    } elseif ($value === null && in_array($field, ['updated_inspection_date', 'assigned_tsr', 'inspection_status', 'date_inspection_completed_canceled', 'inspection_remarks', 'comment', 'follow_up_stage', 'customer_inspec_feedback', 'cx_feedback_details', 'platform'])) {
                        // Allow setting nullable fields to NULL
                        $updateData[$field] = null;
                    }
                }
            }

            // Log the update attempt for debugging
            error_log("UPDATE DATA PREPARED: " . json_encode($updateData));
            
            Log::info('Attempting to update inspection', [
                'inspection_id' => $id,
                'update_data' => $updateData,
                'request_data' => $request->all()
            ]);

            // Perform the update
            $updateResult = DB::table('inspection_tbl')->where('id', $id)->update($updateData);
            
            // Log the result
            Log::info('Inspection update result', [
                'inspection_id' => $id,
                'rows_affected' => $updateResult
            ]);

            // Fetch the updated inspection
            $updatedInspection = DB::table('inspection_tbl')->where('id', $id)->first();

            if (!$updatedInspection) {
                Log::error('Inspection not found after update', ['inspection_id' => $id]);
                return response()->json([
                    'success' => false,
                    'message' => 'Inspection updated but could not retrieve updated data'
                ], 500);
            }

            $message = 'Inspection updated successfully';
            if ($updateResult == 0) {
                $message = 'Update completed successfully. Note: No changes were detected (data may already match the submitted values).';
            }

            return response()->json([
                'success' => true,
                'message' => $message,
                'data' => $updatedInspection,
                'rows_affected' => $updateResult,
                'fields_processed' => array_keys($updateData),
                'debug_info' => [
                    'original_request' => $request->only(['inspectionType', 'inspection_status', 'inspection_remarks']),
                    'update_data_sent_to_db' => $updateData
                ]
            ]);

        } catch (\Exception $e) {
            // Enhanced error logging
            Log::error('Inspection update failed', [
                'inspection_id' => $id,
                'error_message' => $e->getMessage(),
                'error_code' => $e->getCode(),
                'stack_trace' => $e->getTraceAsString(),
                'request_data' => $request->all()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to update inspection. Please check the logs for details.',
                'error' => $e->getMessage(),
                'error_code' => $e->getCode()
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

            // Get TSR management data
            $tsrData = $this->getTsrManagementData();

            return response()->json([
                'success' => true,
                'message' => 'Inspections retrieved successfully',
                'data' => $inspections,
                'count' => $inspections->count(),
                'status' => $status,
                'assigned_tsr' => $tsrData['assigned_tsr'],
                'available_tsrs' => $tsrData['available_tsrs'],
                'tsr_summary' => $tsrData['tsr_summary']
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

            // Get TSR management data
            $tsrData = $this->getTsrManagementData();

            return response()->json([
                'success' => true,
                'message' => 'Inspections retrieved successfully',
                'data' => $inspections,
                'count' => $inspections->count(),
                'inspection_type' => $type,
                'assigned_tsr' => $tsrData['assigned_tsr'],
                'available_tsrs' => $tsrData['available_tsrs'],
                'tsr_summary' => $tsrData['tsr_summary']
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

            // Get TSR management data
            $tsrData = $this->getTsrManagementData();

            return response()->json([
                'success' => true,
                'message' => 'User inspections retrieved successfully',
                'data' => $inspections,
                'count' => $inspections->count(),
                'userID' => $userID,
                'assigned_tsr' => $tsrData['assigned_tsr'],
                'available_tsrs' => $tsrData['available_tsrs'],
                'tsr_summary' => $tsrData['tsr_summary']
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

            // Get TSR management data
            $tsrData = $this->getTsrManagementData();

            return response()->json([
                'success' => true,
                'message' => 'Inspections retrieved successfully',
                'data' => $inspections,
                'count' => $inspections->count(),
                'date_range' => [
                    'start' => $request->start_date,
                    'end' => $request->end_date
                ],
                'assigned_tsr' => $tsrData['assigned_tsr'],
                'available_tsrs' => $tsrData['available_tsrs'],
                'tsr_summary' => $tsrData['tsr_summary']
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

            // Get TSR management data
            $tsrData = $this->getTsrManagementData();

            return response()->json([
                'success' => true,
                'message' => 'Inspections for this week retrieved successfully',
                'data' => $inspections,
                'count' => $inspections->count(),
                'period' => [
                    'start' => $startOfWeek->format('Y-m-d H:i:s'),
                    'end' => $endOfWeek->format('Y-m-d H:i:s')
                ],
                'assigned_tsr' => $tsrData['assigned_tsr'],
                'available_tsrs' => $tsrData['available_tsrs'],
                'tsr_summary' => $tsrData['tsr_summary']
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

            // Get TSR management data
            $tsrData = $this->getTsrManagementData();

            return response()->json([
                'success' => true,
                'message' => 'Inspections for this month retrieved successfully',
                'data' => $inspections,
                'count' => $inspections->count(),
                'period' => [
                    'start' => $startOfMonth->format('Y-m-d H:i:s'),
                    'end' => $endOfMonth->format('Y-m-d H:i:s')
                ],
                'assigned_tsr' => $tsrData['assigned_tsr'],
                'available_tsrs' => $tsrData['available_tsrs'],
                'tsr_summary' => $tsrData['tsr_summary']
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

            // Get TSR management data
            $tsrData = $this->getTsrManagementData();

            return response()->json([
                'success' => true,
                'message' => 'Inspections for this year retrieved successfully',
                'data' => $inspections,
                'count' => $inspections->count(),
                'period' => [
                    'start' => $startOfYear->format('Y-m-d H:i:s'),
                    'end' => $endOfYear->format('Y-m-d H:i:s')
                ],
                'assigned_tsr' => $tsrData['assigned_tsr'],
                'available_tsrs' => $tsrData['available_tsrs'],
                'tsr_summary' => $tsrData['tsr_summary']
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'An error occurred while retrieving inspections for this year',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get count of pending inspections this month.
     */
    public function getPendingCountThisMonth()
    {
        try {
            $startOfMonth = now()->startOfMonth();
            $endOfMonth = now()->endOfMonth();

            // Count pending inspections this month
            $pendingCount = DB::table('inspection_tbl')
                ->where('inspection_status', 'pending')
                ->whereBetween('inspectionDate', [$startOfMonth, $endOfMonth])
                ->count();

            // Get additional statistics for this month
            $totalInspectionsThisMonth = DB::table('inspection_tbl')
                ->whereBetween('inspectionDate', [$startOfMonth, $endOfMonth])
                ->count();

            $completedCount = DB::table('inspection_tbl')
                ->where('inspection_status', 'completed')
                ->whereBetween('inspectionDate', [$startOfMonth, $endOfMonth])
                ->count();

            $canceledCount = DB::table('inspection_tbl')
                ->where('inspection_status', 'canceled')
                ->whereBetween('inspectionDate', [$startOfMonth, $endOfMonth])
                ->count();

            // Get status breakdown for this month
            $statusBreakdown = DB::table('inspection_tbl')
                ->select('inspection_status', DB::raw('count(*) as count'))
                ->whereBetween('inspectionDate', [$startOfMonth, $endOfMonth])
                ->whereNotNull('inspection_status')
                ->groupBy('inspection_status')
                ->get()
                ->keyBy('inspection_status');

            // Calculate pending percentage
            $pendingPercentage = $totalInspectionsThisMonth > 0 ? 
                round(($pendingCount / $totalInspectionsThisMonth) * 100, 2) : 0;

            return response()->json([
                'success' => true,
                'message' => 'Pending inspections count for this month retrieved successfully',
                'month' => now()->format('F Y'),
                'period' => [
                    'start' => $startOfMonth->format('Y-m-d H:i:s'),
                    'end' => $endOfMonth->format('Y-m-d H:i:s')
                ],
                'pending_count' => $pendingCount,
                'total_inspections_this_month' => $totalInspectionsThisMonth,
                'pending_percentage' => $pendingPercentage,
                'status_summary' => [
                    'pending' => $pendingCount,
                    'completed' => $completedCount,
                    'canceled' => $canceledCount,
                    'other' => $totalInspectionsThisMonth - ($pendingCount + $completedCount + $canceledCount)
                ],
                'detailed_status_breakdown' => $statusBreakdown,
                'insights' => [
                    'urgency_level' => $pendingPercentage >= 70 ? 'Critical' : ($pendingPercentage >= 40 ? 'High' : ($pendingPercentage >= 20 ? 'Medium' : 'Low')),
                    'completion_rate' => $totalInspectionsThisMonth > 0 ? round(($completedCount / $totalInspectionsThisMonth) * 100, 2) : 0,
                    'action_required' => $pendingCount > 0 ? "There are {$pendingCount} pending inspections requiring attention" : 'All inspections are up to date'
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'An error occurred while retrieving pending inspections count',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Debug endpoint to show validation rules and test data.
     */
    public function debugUpdate(Request $request, string $id)
    {
        $inspection = DB::table('inspection_tbl')->where('id', $id)->first();
        
        if (!$inspection) {
            return response()->json(['error' => 'Inspection not found'], 404);
        }

        // Analyze potential restrictions
        $restrictions = [];
        
        // Check if inspection is completed
        if ($inspection->inspection_status === 'completed') {
            $restrictions[] = 'Inspection is completed - typically restricts type changes';
        }
        
        // Check if inspection date is in the past
        $inspectionDate = strtotime($inspection->inspectionDate);
        if ($inspectionDate < strtotime('today')) {
            $restrictions[] = 'Inspection date is in the past - may restrict changes';
        }
        
        // Check if TSR is assigned
        if (!empty($inspection->assigned_tsr)) {
            $restrictions[] = 'TSR is assigned - may restrict type changes';
        }

        return response()->json([
            'inspection_id' => $id,
            'current_inspection' => $inspection,
            'request_data' => $request->all(),
            'potential_restrictions' => $restrictions,
            'validation_info' => [
                'valid_inspection_status_values' => [
                    'pending-not-assigned', 'pending-assigned', 'completed', 
                    'canceled', 'apartment-not-available', 'multiple-bookings', 
                    'did-not-show-up'
                ],
                'valid_inspection_remarks_values' => [
                    'interested', 'uninterested', 'indecisive', 'rescheduled'
                ],
                'valid_inspection_types' => ['Physical', 'Virtual', 'Remote'],
                'max_lengths' => [
                    'assigned_tsr' => 10,
                    'follow_up_stage' => 100,
                    'platform' => 50
                ]
            ],
            'api_parameter_validation' => [
                'all_database_columns_covered' => true,
                'parameter_mapping' => [
                    'inspectionDate' => 'datetime - Basic date validation',
                    'updated_inspection_date' => 'datetime|nullable - Optional update date',
                    'inspectionType' => 'enum(Physical,Virtual,Remote) - Conditional validation',
                    'assigned_tsr' => 'varchar(10)|nullable - TSR assignment',
                    'inspection_status' => 'enum - 7 valid statuses, conditional validation',
                    'date_inspection_completed_canceled' => 'datetime|nullable - Completion date',
                    'inspection_remarks' => 'enum - 4 valid remarks, conditional validation',
                    'comment' => 'text|nullable - Free text comments',
                    'follow_up_stage' => 'varchar(100)|nullable - Follow-up information',
                    'customer_inspec_feedback' => 'text|nullable - Customer feedback',
                    'cx_feedback_details' => 'text|nullable - CX team feedback details',
                    'platform' => 'varchar(50)|nullable - Platform information'
                ],
                'validation_issues' => []
            ],
            'inspectionType_update_test' => [
                'current_value' => $inspection->inspectionType,
                'can_theoretically_update' => true,
                'api_allows_update' => true,
                'validation_rule' => 'in:Physical,Virtual,Remote (conditional)',
                'note' => 'inspectionType is in allowed fields and has proper validation'
            ]
        ]);
    }
}
