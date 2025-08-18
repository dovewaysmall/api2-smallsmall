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
     * Display a listing of all repair requests.
     */
    public function index()
    {
        try {
            $repairs = DB::table('repair_request')
                ->leftJoin('user_tbl', DB::raw('CAST(repair_request.userId AS CHAR)'), '=', DB::raw('CAST(user_tbl.userID AS CHAR)'))
                ->select(
                    'repair_request.*',
                    'user_tbl.firstName as requester_firstName',
                    'user_tbl.lastName as requester_lastName',
                    'user_tbl.email as requester_email',
                    'user_tbl.phone as requester_phone'
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
     * Display the specified repair request.
     */
    public function show(string $id)
    {
        try {
            $repair = DB::table('repair_request')
                ->leftJoin('user_tbl', DB::raw('CAST(repair_request.userId AS CHAR)'), '=', DB::raw('CAST(user_tbl.userID AS CHAR)'))
                ->select(
                    'repair_request.*',
                    'user_tbl.firstName as requester_firstName',
                    'user_tbl.lastName as requester_lastName',
                    'user_tbl.email as requester_email',
                    'user_tbl.phone as requester_phone'
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
     * Store a newly created repair request.
     */
    public function store(Request $request)
    {
        // Validation rules for your requirements (simplified for current table)
        $validator = Validator::make($request->all(), [
            'type_of_repair' => 'required|string|in:plumbing,electrical,hvac,appliance,structural,painting,flooring,roofing,pest_control,carpentry,general_maintenance,other',
            'details' => 'required|string|max:2000',
            'userId' => 'required|string|max:255',
            'repair_request_status' => 'nullable|in:pending,in_progress,completed,cancelled,on_hold',
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
                $imageFolder = 'repair_' . time() . '_' . mt_rand(1000, 9999);
                
                foreach ($images as $index => $image) {
                    $imageName = $imageFolder . '_' . $index . '.' . $image->getClientOriginalExtension();
                    $imagePath = $image->storeAs('repair_images/' . $imageFolder, $imageName, 'public');
                    $imagesPaths[] = $imagePath;
                }
            }

            // Prepare data for insertion (matching current table structure)
            $data = [
                'type_of_repair' => $request->type_of_repair,
                'details' => $request->details,
                'userId' => $request->userId,
                'repair_request_date' => now(),
                'repair_request_status' => $request->repair_request_status ?? 'pending',
            ];

            // Insert repair request and get ID
            $repairId = DB::table('repair_request')->insertGetId($data);

            if ($repairId) {
                // Retrieve the created repair request with related data
                $createdRepair = DB::table('repair_request')
                    ->leftJoin('user_tbl', DB::raw('CAST(repair_request.userId AS CHAR)'), '=', DB::raw('CAST(user_tbl.userID AS CHAR)'))
                    ->select(
                        'repair_request.*',
                        'user_tbl.firstName as requester_firstName',
                        'user_tbl.lastName as requester_lastName'
                    )
                    ->where('repair_request.id', $repairId)
                    ->first();

                return response()->json([
                    'success' => true,
                    'message' => 'Repair request created successfully',
                    'data' => $createdRepair,
                    'id' => $repairId
                ], 201);
            } else {
                return response()->json([
                    'success' => false,
                    'message' => 'Failed to create repair request'
                ], 500);
            }

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'An error occurred while creating repair request',
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
            'title' => 'sometimes|string|max:255',
            'type_of_repair' => 'sometimes|string|in:plumbing,electrical,hvac,appliance,structural,painting,flooring,roofing,pest_control,carpentry,general_maintenance,other',
            'cost_of_repair' => 'nullable|numeric|min:0',
            'propertyID' => 'sometimes|string|max:255',
            'description' => 'sometimes|string|max:2000',
            'handler_id' => 'nullable|string|max:255',
            'status' => 'sometimes|in:pending,in_progress,completed,cancelled,on_hold',
            'feedback' => 'nullable|string|max:1000',
            'images' => 'nullable|array|max:10',
            'images.*' => 'image|mimes:jpeg,png,jpg,gif,webp|max:5120',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation error',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            // Handle new image uploads if provided
            $imageFolder = $repair->image_folder;
            $existingImagesPaths = json_decode($repair->images_paths ?? '[]', true);
            $newImagesPaths = [];
            
            if ($request->hasFile('images')) {
                $images = $request->file('images');
                if (!$imageFolder) {
                    $imageFolder = 'repair_' . time() . '_' . mt_rand(1000, 9999);
                }
                
                foreach ($images as $index => $image) {
                    $imageName = $imageFolder . '_' . time() . '_' . $index . '.' . $image->getClientOriginalExtension();
                    $imagePath = $image->storeAs('repair_images/' . $imageFolder, $imageName, 'public');
                    $newImagesPaths[] = $imagePath;
                }
                
                // Combine existing and new images
                $allImagesPaths = array_merge($existingImagesPaths, $newImagesPaths);
            } else {
                $allImagesPaths = $existingImagesPaths;
            }

            $updateData = $request->only([
                'title', 'type_of_repair', 'cost_of_repair', 'propertyID', 
                'description', 'handler_id', 'status', 'feedback'
            ]);

            // Sync status fields for backward compatibility
            if (isset($updateData['status'])) {
                $updateData['repair_request_status'] = $updateData['status'];
            }
            if (isset($updateData['description'])) {
                $updateData['details'] = $updateData['description'];
            }

            // Update image data if new images were uploaded
            if (!empty($newImagesPaths)) {
                $updateData['image_folder'] = $imageFolder;
                $updateData['images_paths'] = json_encode($allImagesPaths);
            }

            DB::table('repair_request')->where('id', $id)->update($updateData);
            
            $updatedRepair = DB::table('repair_request')
                ->leftJoin('user_tbl', DB::raw('CAST(repair_request.userId AS CHAR)'), '=', DB::raw('CAST(user_tbl.userID AS CHAR)'))
                ->leftJoin('property_tbl', DB::raw('CAST(repair_request.propertyID AS CHAR)'), '=', DB::raw('CAST(property_tbl.propertyID AS CHAR)'))
                ->leftJoin('user_tbl as handler', DB::raw('CAST(repair_request.handler_id AS CHAR)'), '=', DB::raw('CAST(handler.userID AS CHAR)'))
                ->select(
                    'repair_request.*',
                    'user_tbl.firstName as requester_firstName',
                    'user_tbl.lastName as requester_lastName',
                    'property_tbl.propertyTitle as property_title',
                    'handler.firstName as handler_firstName',
                    'handler.lastName as handler_lastName'
                )
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
     * Get repair requests by user.
     */
    public function getByUser($userId)
    {
        try {
            $repairs = DB::table('repair_request')
                ->leftJoin('user_tbl', DB::raw('CAST(repair_request.userId AS CHAR)'), '=', DB::raw('CAST(user_tbl.userID AS CHAR)'))
                ->leftJoin('property_tbl', DB::raw('CAST(repair_request.propertyID AS CHAR)'), '=', DB::raw('CAST(property_tbl.propertyID AS CHAR)'))
                ->leftJoin('user_tbl as handler', DB::raw('CAST(repair_request.handler_id AS CHAR)'), '=', DB::raw('CAST(handler.userID AS CHAR)'))
                ->select(
                    'repair_request.*',
                    'property_tbl.propertyTitle as property_title',
                    'handler.firstName as handler_firstName',
                    'handler.lastName as handler_lastName'
                )
                ->where('repair_request.userId', $userId)
                ->orderBy('repair_request.repair_request_date', 'desc')
                ->get();

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
                ->leftJoin('user_tbl as handler', DB::raw('CAST(repair_request.handler_id AS CHAR)'), '=', DB::raw('CAST(handler.userID AS CHAR)'))
                ->select(
                    'repair_request.*',
                    'user_tbl.firstName as requester_firstName',
                    'user_tbl.lastName as requester_lastName',
                    'property_tbl.propertyTitle as property_title',
                    'handler.firstName as handler_firstName',
                    'handler.lastName as handler_lastName'
                )
                ->where('repair_request.status', $status)
                ->orWhere('repair_request.repair_request_status', $status)
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
            'type' => 'required|in:plumbing,electrical,hvac,appliance,structural,painting,flooring,roofing,pest_control,carpentry,general_maintenance,other'
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
                ->leftJoin('property_tbl', DB::raw('CAST(repair_request.propertyID AS CHAR)'), '=', DB::raw('CAST(property_tbl.propertyID AS CHAR)'))
                ->leftJoin('user_tbl as handler', DB::raw('CAST(repair_request.handler_id AS CHAR)'), '=', DB::raw('CAST(handler.userID AS CHAR)'))
                ->select(
                    'repair_request.*',
                    'user_tbl.firstName as requester_firstName',
                    'user_tbl.lastName as requester_lastName',
                    'property_tbl.propertyTitle as property_title',
                    'handler.firstName as handler_firstName',
                    'handler.lastName as handler_lastName'
                )
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
     * Get repair request statistics.
     */
    public function getStats()
    {
        try {
            $totalRequests = DB::table('repair_request')->count();
            $pendingRequests = DB::table('repair_request')
                ->where('status', 'pending')
                ->orWhere('repair_request_status', 'pending')
                ->count();
            $inProgressRequests = DB::table('repair_request')
                ->where('status', 'in_progress')
                ->orWhere('repair_request_status', 'in_progress')
                ->count();
            $completedRequests = DB::table('repair_request')
                ->where('status', 'completed')
                ->orWhere('repair_request_status', 'completed')
                ->count();

            $statusStats = DB::table('repair_request')
                ->selectRaw('
                    COALESCE(status, repair_request_status, "pending") as status_field,
                    count(*) as count
                ')
                ->groupBy('status_field')
                ->get();

            $typeStats = DB::table('repair_request')
                ->select('type_of_repair', DB::raw('count(*) as count'))
                ->groupBy('type_of_repair')
                ->orderBy('count', 'desc')
                ->get();

            $totalCost = DB::table('repair_request')
                ->where('status', 'completed')
                ->orWhere('repair_request_status', 'completed')
                ->sum('cost_of_repair');

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
                'total_repair_cost' => round($totalCost, 2),
                'status_breakdown' => $statusStats,
                'type_breakdown' => $typeStats
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'An error occurred while retrieving repair statistics',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}