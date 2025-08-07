<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Storage;

class RepairController extends Controller
{
    /**
     * Display a listing of all repair requests (Improved version of your original).
     */
    public function index()
    {
        try {
            $repairs = DB::table('repair_request')
                ->leftJoin('user_tbl', DB::raw('CAST(repair_request.userId AS CHAR)'), '=', DB::raw('CAST(user_tbl.userID AS CHAR)'))
                ->leftJoin('property_tbl', DB::raw('CAST(repair_request.propertyID AS CHAR)'), '=', DB::raw('CAST(property_tbl.propertyID AS CHAR)'))
                ->select(
                    'repair_request.*',
                    'user_tbl.firstName as user_firstName',
                    'user_tbl.lastName as user_lastName',
                    'user_tbl.email as user_email',
                    'user_tbl.phone as user_phone',
                    'property_tbl.propertyTitle',
                    'property_tbl.address as property_address'
                )
                ->orderBy('repair_request.repair_request_date', 'desc')
                ->get();

            return response()->json([
                'success' => true,
                'message' => 'Repair requests retrieved successfully',
                'data' => $repairs,
                'count' => $repairs->count()
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'An error occurred while retrieving repair requests',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Display the specified repair request (Improved version of your original).
     */
    public function show(string $id)
    {
        try {
            $repair = DB::table('repair_request')
                ->leftJoin('user_tbl', DB::raw('CAST(repair_request.userId AS CHAR)'), '=', DB::raw('CAST(user_tbl.userID AS CHAR)'))
                ->leftJoin('property_tbl', DB::raw('CAST(repair_request.propertyID AS CHAR)'), '=', DB::raw('CAST(property_tbl.propertyID AS CHAR)'))
                ->leftJoin('user_tbl as technician', DB::raw('CAST(repair_request.assigned_technician AS CHAR)'), '=', DB::raw('CAST(technician.userID AS CHAR)'))
                ->select(
                    'repair_request.*',
                    'user_tbl.firstName as user_firstName',
                    'user_tbl.lastName as user_lastName',
                    'user_tbl.email as user_email',
                    'user_tbl.phone as user_phone',
                    'property_tbl.propertyTitle',
                    'property_tbl.address as property_address',
                    'technician.firstName as technician_firstName',
                    'technician.lastName as technician_lastName'
                )
                ->where('repair_request.id', $id)
                ->first();

            if (!$repair) {
                return response()->json([
                    'success' => false,
                    'message' => 'Repair request not found'
                ], 404);
            }

            return response()->json([
                'success' => true,
                'message' => 'Repair request retrieved successfully',
                'data' => $repair
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'An error occurred while retrieving repair request',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Store a newly created repair request (Improved version of your original).
     */
    public function store(Request $request)
    {
        // Enhanced validation rules
        $validator = Validator::make($request->all(), [
            'type_of_repair' => 'required|string|in:plumbing,electrical,hvac,appliance,structural,painting,flooring,roofing,pest_control,general_maintenance,other',
            'details' => 'required|string|max:2000',
            'userId' => 'required|string|max:255',
            'propertyID' => 'nullable|string|max:255',
            'priority' => 'nullable|in:low,medium,high,emergency',
            'preferred_date' => 'nullable|date|after_or_equal:today',
            'contact_method' => 'nullable|in:phone,email,both',
            'images' => 'nullable|array|max:5',
            'images.*' => 'image|mimes:jpeg,png,jpg,gif|max:2048',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation error',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            // Handle image uploads
            $imageFolder = null;
            $imagesPaths = [];
            
            if ($request->hasFile('images')) {
                $images = $request->file('images');
                $imageFolder = 'repair_' . time();
                
                foreach ($images as $index => $image) {
                    $imageName = $imageFolder . '_' . $index . '.' . $image->getClientOriginalExtension();
                    $imagePath = $image->storeAs('repair_images/' . $imageFolder, $imageName, 'public');
                    $imagesPaths[] = $imagePath;
                }
            }

            // Prepare data for insertion
            $data = [
                'request_id' => 'REP-' . time() . '-' . mt_rand(1000, 9999),
                'type_of_repair' => $request->type_of_repair,
                'details' => $request->details,
                'userId' => $request->userId,
                'propertyID' => $request->propertyID,
                'priority' => $request->priority ?? 'medium',
                'preferred_date' => $request->preferred_date,
                'contact_method' => $request->contact_method ?? 'phone',
                'repair_request_status' => 'pending',
                'repair_request_date' => now(),
                'image_folder' => $imageFolder,
                'images_paths' => json_encode($imagesPaths),
                'estimated_cost' => null,
                'actual_cost' => null,
            ];

            // Insert repair request and get ID
            $repairId = DB::table('repair_request')->insertGetId($data);

            if ($repairId) {
                // Retrieve the created repair request with user details
                $createdRepair = DB::table('repair_request')
                    ->leftJoin('user_tbl', DB::raw('CAST(repair_request.userId AS CHAR)'), '=', DB::raw('CAST(user_tbl.userID AS CHAR)'))
                    ->select('repair_request.*', 'user_tbl.firstName', 'user_tbl.lastName', 'user_tbl.email')
                    ->where('repair_request.id', $repairId)
                    ->first();

                return response()->json([
                    'success' => true,
                    'message' => 'Repair request submitted successfully',
                    'data' => $createdRepair,
                    'id' => $repairId
                ], 201);
            } else {
                return response()->json([
                    'success' => false,
                    'message' => 'Failed to submit repair request'
                ], 500);
            }

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'An error occurred while submitting repair request',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update the specified repair request.
     */
    public function update(Request $request, string $id)
    {
        $repair = DB::table('repair_request')->where('id', $id)->first();

        if (!$repair) {
            return response()->json([
                'success' => false,
                'message' => 'Repair request not found'
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'repair_request_status' => 'sometimes|in:pending,in_progress,completed,cancelled,on_hold',
            'assigned_technician' => 'nullable|string|max:255',
            'scheduled_date' => 'nullable|date',
            'completion_date' => 'nullable|date',
            'estimated_cost' => 'nullable|numeric|min:0',
            'actual_cost' => 'nullable|numeric|min:0',
            'technician_notes' => 'nullable|string|max:1000',
            'admin_notes' => 'nullable|string|max:1000',
            'priority' => 'sometimes|in:low,medium,high,emergency',
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
                'repair_request_status', 'assigned_technician', 'scheduled_date',
                'completion_date', 'estimated_cost', 'actual_cost', 
                'technician_notes', 'admin_notes', 'priority'
            ]);

            // Auto-set completion date if status is completed
            if ($request->repair_request_status === 'completed' && !$request->completion_date) {
                $updateData['completion_date'] = now();
            }

            DB::table('repair_request')->where('id', $id)->update($updateData);
            
            $updatedRepair = DB::table('repair_request')
                ->leftJoin('user_tbl', DB::raw('CAST(repair_request.userId AS CHAR)'), '=', DB::raw('CAST(user_tbl.userID AS CHAR)'))
                ->select('repair_request.*', 'user_tbl.firstName', 'user_tbl.lastName')
                ->where('repair_request.id', $id)
                ->first();

            return response()->json([
                'success' => true,
                'message' => 'Repair request updated successfully',
                'data' => $updatedRepair
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'An error occurred while updating repair request',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Remove the specified repair request.
     */
    public function destroy(string $id)
    {
        $repair = DB::table('repair_request')->where('id', $id)->first();

        if (!$repair) {
            return response()->json([
                'success' => false,
                'message' => 'Repair request not found'
            ], 404);
        }

        try {
            // Delete images if they exist
            if ($repair->image_folder) {
                Storage::disk('public')->deleteDirectory('repair_images/' . $repair->image_folder);
            }

            DB::table('repair_request')->where('id', $id)->delete();

            return response()->json([
                'success' => true,
                'message' => 'Repair request deleted successfully'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'An error occurred while deleting repair request',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get repair requests by user (Improved version of your original).
     */
    public function getByUser($userId)
    {
        try {
            $repairs = DB::table('repair_request')
                ->leftJoin('user_tbl', DB::raw('CAST(repair_request.userId AS CHAR)'), '=', DB::raw('CAST(user_tbl.userID AS CHAR)'))
                ->leftJoin('property_tbl', DB::raw('CAST(repair_request.propertyID AS CHAR)'), '=', DB::raw('CAST(property_tbl.propertyID AS CHAR)'))
                ->select('repair_request.*', 'user_tbl.firstName', 'user_tbl.lastName', 'property_tbl.propertyTitle')
                ->where('repair_request.userId', $userId)
                ->orderBy('repair_request.repair_request_date', 'desc')
                ->get();

            if ($repairs->isEmpty()) {
                return response()->json([
                    'success' => true,
                    'message' => 'No repair requests found for this user',
                    'data' => [],
                    'count' => 0
                ]);
            }

            return response()->json([
                'success' => true,
                'message' => 'User repair requests retrieved successfully',
                'data' => $repairs,
                'count' => $repairs->count(),
                'user_id' => $userId
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'An error occurred while retrieving repair requests',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get repair requests by status.
     */
    public function getByStatus($status)
    {
        $validator = Validator::make(['status' => $status], [
            'status' => 'required|in:pending,in_progress,completed,cancelled,on_hold'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid status. Must be: pending, in_progress, completed, cancelled, or on_hold',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $repairs = DB::table('repair_request')
                ->leftJoin('user_tbl', DB::raw('CAST(repair_request.userId AS CHAR)'), '=', DB::raw('CAST(user_tbl.userID AS CHAR)'))
                ->leftJoin('property_tbl', DB::raw('CAST(repair_request.propertyID AS CHAR)'), '=', DB::raw('CAST(property_tbl.propertyID AS CHAR)'))
                ->select('repair_request.*', 'user_tbl.firstName', 'user_tbl.lastName', 'property_tbl.propertyTitle')
                ->where('repair_request.repair_request_status', $status)
                ->orderBy('repair_request.repair_request_date', 'desc')
                ->get();

            return response()->json([
                'success' => true,
                'message' => 'Repair requests retrieved successfully',
                'data' => $repairs,
                'count' => $repairs->count(),
                'status' => $status
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'An error occurred while retrieving repair requests',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get repair requests by type.
     */
    public function getByType($type)
    {
        $validator = Validator::make(['type' => $type], [
            'type' => 'required|in:plumbing,electrical,hvac,appliance,structural,painting,flooring,roofing,pest_control,general_maintenance,other'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid repair type',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $repairs = DB::table('repair_request')
                ->leftJoin('user_tbl', DB::raw('CAST(repair_request.userId AS CHAR)'), '=', DB::raw('CAST(user_tbl.userID AS CHAR)'))
                ->select('repair_request.*', 'user_tbl.firstName', 'user_tbl.lastName')
                ->where('repair_request.type_of_repair', $type)
                ->orderBy('repair_request.repair_request_date', 'desc')
                ->get();

            return response()->json([
                'success' => true,
                'message' => 'Repair requests retrieved successfully',
                'data' => $repairs,
                'count' => $repairs->count(),
                'repair_type' => $type
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'An error occurred while retrieving repair requests',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get repair requests by priority.
     */
    public function getByPriority($priority)
    {
        $validator = Validator::make(['priority' => $priority], [
            'priority' => 'required|in:low,medium,high,emergency'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid priority. Must be: low, medium, high, or emergency',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $repairs = DB::table('repair_request')
                ->leftJoin('user_tbl', DB::raw('CAST(repair_request.userId AS CHAR)'), '=', DB::raw('CAST(user_tbl.userID AS CHAR)'))
                ->select('repair_request.*', 'user_tbl.firstName', 'user_tbl.lastName')
                ->where('repair_request.priority', $priority)
                ->orderBy('repair_request.repair_request_date', 'desc')
                ->get();

            return response()->json([
                'success' => true,
                'message' => 'Repair requests retrieved successfully',
                'data' => $repairs,
                'count' => $repairs->count(),
                'priority' => $priority
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'An error occurred while retrieving repair requests',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get repair request statistics.
     */
    public function getStats()
    {
        try {
            $totalRequests = DB::table('repair_request')->count();
            $pendingRequests = DB::table('repair_request')->where('repair_request_status', 'pending')->count();
            $inProgressRequests = DB::table('repair_request')->where('repair_request_status', 'in_progress')->count();
            $completedRequests = DB::table('repair_request')->where('repair_request_status', 'completed')->count();

            $statusStats = DB::table('repair_request')
                ->select('repair_request_status', DB::raw('count(*) as count'))
                ->groupBy('repair_request_status')
                ->get();

            $typeStats = DB::table('repair_request')
                ->select('type_of_repair', DB::raw('count(*) as count'))
                ->groupBy('type_of_repair')
                ->orderBy('count', 'desc')
                ->get();

            $priorityStats = DB::table('repair_request')
                ->select('priority', DB::raw('count(*) as count'))
                ->groupBy('priority')
                ->get();

            $avgCompletionTime = DB::table('repair_request')
                ->where('repair_request_status', 'completed')
                ->whereNotNull('completion_date')
                ->selectRaw('AVG(DATEDIFF(completion_date, repair_request_date)) as avg_days')
                ->first();

            $totalCost = DB::table('repair_request')
                ->where('repair_request_status', 'completed')
                ->sum('actual_cost');

            $monthlyRequests = DB::table('repair_request')
                ->where('repair_request_date', '>=', now()->subDays(30))
                ->count();

            return response()->json([
                'success' => true,
                'message' => 'Repair request statistics retrieved successfully',
                'total_requests' => $totalRequests,
                'pending_requests' => $pendingRequests,
                'in_progress_requests' => $inProgressRequests,
                'completed_requests' => $completedRequests,
                'monthly_requests' => $monthlyRequests,
                'average_completion_days' => round($avgCompletionTime->avg_days ?? 0, 1),
                'total_repair_cost' => round($totalCost, 2),
                'status_breakdown' => $statusStats,
                'type_breakdown' => $typeStats,
                'priority_breakdown' => $priorityStats
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'An error occurred while retrieving repair statistics',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get repair requests by date range.
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
            $repairs = DB::table('repair_request')
                ->leftJoin('user_tbl', DB::raw('CAST(repair_request.userId AS CHAR)'), '=', DB::raw('CAST(user_tbl.userID AS CHAR)'))
                ->select('repair_request.*', 'user_tbl.firstName', 'user_tbl.lastName')
                ->whereDate('repair_request.repair_request_date', '>=', $request->start_date)
                ->whereDate('repair_request.repair_request_date', '<=', $request->end_date)
                ->orderBy('repair_request.repair_request_date', 'desc')
                ->get();

            return response()->json([
                'success' => true,
                'message' => 'Repair requests retrieved successfully',
                'data' => $repairs,
                'count' => $repairs->count(),
                'date_range' => [
                    'start' => $request->start_date,
                    'end' => $request->end_date
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'An error occurred while retrieving repair requests',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}