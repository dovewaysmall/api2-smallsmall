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
     * Display a listing of all repairs.
     */
    public function index()
    {
        try {
            $repairs = DB::table('repairs')
                ->leftJoin('user_tbl', DB::raw('CAST(repairs.apartment_owner_id AS CHAR)'), '=', DB::raw('CAST(user_tbl.userID AS CHAR)'))
                ->select(
                    'repairs.*',
                    'user_tbl.firstName as requester_firstName',
                    'user_tbl.lastName as requester_lastName',
                    'user_tbl.email as requester_email',
                    'user_tbl.phone as requester_phone'
                )
                ->orderBy('repairs.repair_date', 'desc')
                ->get();

            return response()->json([
                'success' => true,
                'message' => 'Repairs retrieved successfully',
                'data' => $repairs,
                'count' => $repairs->count()
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'An error occurred while retrieving repairs',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Display the specified repair.
     */
    public function show(string $id)
    {
        try {
            $repair = DB::table('repairs')
                ->leftJoin('user_tbl', DB::raw('CAST(repairs.apartment_owner_id AS CHAR)'), '=', DB::raw('CAST(user_tbl.userID AS CHAR)'))
                ->select(
                    'repairs.*',
                    'user_tbl.firstName as requester_firstName',
                    'user_tbl.lastName as requester_lastName',
                    'user_tbl.email as requester_email',
                    'user_tbl.phone as requester_phone'
                )
                ->where('repairs.id', $id)
                ->first();

            if (!$repair) {
                return response()->json([
                    'success' => false,
                    'message' => 'Repair not found'
                ], 404);
            }

            return response()->json([
                'success' => true,
                'message' => 'Repair retrieved successfully',
                'data' => $repair
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'An error occurred while retrieving repair',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Store a newly created repair.
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'items_repaired' => 'required|string|max:2000',
            'apartment_owner_id' => 'required|string|max:255',
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

            $nextId = DB::table('repairs')->max('id') + 1;
            
            $data = [
                'id' => $nextId,
                'items_repaired' => $request->items_repaired,
                'apartment_owner_id' => $request->apartment_owner_id,
                'repair_amount' => 0,
            ];

            $inserted = DB::table('repairs')->insert($data);
            $repairId = $inserted ? $nextId : false;

            if ($repairId) {
                $createdRepair = DB::table('repairs')
                    ->where('repairs.id', $repairId)
                    ->first();

                return response()->json([
                    'success' => true,
                    'message' => 'Repair created successfully',
                    'data' => $createdRepair,
                    'id' => $repairId
                ], 201);
            } else {
                return response()->json([
                    'success' => false,
                    'message' => 'Failed to create repair'
                ], 500);
            }

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'An error occurred while creating repair',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update the specified repair.
     */
    public function update(Request $request, string $id)
    {
        $repair = DB::table('repairs')->where('id', $id)->first();

        if (!$repair) {
            return response()->json([
                'success' => false,
                'message' => 'Repair not found'
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'items_repaired' => 'sometimes|string|max:2000',
            'property_id' => 'sometimes|string|max:50',
            'repair_amount' => 'nullable|numeric|min:0',
            'repair_status' => 'sometimes|in:pending,in_progress,completed,cancelled',
            'repair_done_by' => 'nullable|string|max:255',
            'feedback' => 'nullable|string|max:2000',
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
                'items_repaired', 'property_id', 'repair_amount', 'repair_status', 'repair_done_by', 'feedback'
            ]);

            // Update image data if new images were uploaded
            if (!empty($newImagesPaths)) {
                $updateData['image_folder'] = $imageFolder;
                $updateData['images_paths'] = json_encode($allImagesPaths);
            }

            DB::table('repairs')->where('id', $id)->update($updateData);
            
            $updatedRepair = DB::table('repairs')
                ->leftJoin('user_tbl', DB::raw('CAST(repairs.apartment_owner_id AS CHAR)'), '=', DB::raw('CAST(user_tbl.userID AS CHAR)'))
                ->leftJoin('property_tbl', DB::raw('CAST(repairs.property_id AS CHAR)'), '=', DB::raw('CAST(property_tbl.propertyID AS CHAR)'))
                ->leftJoin('user_tbl as handler', DB::raw('CAST(repairs.repair_done_by AS CHAR)'), '=', DB::raw('CAST(handler.userID AS CHAR)'))
                ->select(
                    'repairs.*',
                    'user_tbl.firstName as requester_firstName',
                    'user_tbl.lastName as requester_lastName',
                    'property_tbl.propertyTitle as property_title',
                    'handler.firstName as handler_firstName',
                    'handler.lastName as handler_lastName'
                )
                ->where('repairs.id', $id)
                ->first();

            return response()->json([
                'success' => true,
                'message' => 'Repair updated successfully',
                'data' => $updatedRepair
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'An error occurred while updating repair',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Remove the specified repair.
     */
    public function destroy(string $id)
    {
        $repair = DB::table('repairs')->where('id', $id)->first();

        if (!$repair) {
            return response()->json([
                'success' => false,
                'message' => 'Repair not found'
            ], 404);
        }

        try {
            // Delete images if they exist
            if ($repair->image_folder) {
                Storage::disk('public')->deleteDirectory('repair_images/' . $repair->image_folder);
            }

            DB::table('repairs')->where('id', $id)->delete();

            return response()->json([
                'success' => true,
                'message' => 'Repair deleted successfully'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'An error occurred while deleting repair',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get repairs by user.
     */
    public function getByUser($userId)
    {
        try {
            $repairs = DB::table('repairs')
                ->leftJoin('user_tbl', DB::raw('CAST(repairs.apartment_owner_id AS CHAR)'), '=', DB::raw('CAST(user_tbl.userID AS CHAR)'))
                ->leftJoin('property_tbl', DB::raw('CAST(repairs.property_id AS CHAR)'), '=', DB::raw('CAST(property_tbl.propertyID AS CHAR)'))
                ->leftJoin('user_tbl as handler', DB::raw('CAST(repairs.repair_done_by AS CHAR)'), '=', DB::raw('CAST(handler.userID AS CHAR)'))
                ->select(
                    'repairs.*',
                    'property_tbl.propertyTitle as property_title',
                    'handler.firstName as handler_firstName',
                    'handler.lastName as handler_lastName'
                )
                ->where('repairs.apartment_owner_id', $userId)
                ->orderBy('repairs.repair_date', 'desc')
                ->get();

            return response()->json([
                'success' => true,
                'message' => 'User repairs retrieved successfully',
                'data' => $repairs,
                'count' => $repairs->count(),
                'user_id' => $userId
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'An error occurred while retrieving repairs',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get repairs by status.
     */
    public function getByStatus($status)
    {
        $validator = Validator::make(['status' => $status], [
            'status' => 'required|in:pending,in_progress,completed,cancelled'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid status. Must be: pending, in_progress, completed, or cancelled',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $repairs = DB::table('repairs')
                ->leftJoin('user_tbl', DB::raw('CAST(repairs.apartment_owner_id AS CHAR)'), '=', DB::raw('CAST(user_tbl.userID AS CHAR)'))
                ->leftJoin('property_tbl', DB::raw('CAST(repairs.property_id AS CHAR)'), '=', DB::raw('CAST(property_tbl.propertyID AS CHAR)'))
                ->leftJoin('user_tbl as handler', DB::raw('CAST(repairs.repair_done_by AS CHAR)'), '=', DB::raw('CAST(handler.userID AS CHAR)'))
                ->select(
                    'repairs.*',
                    'user_tbl.firstName as requester_firstName',
                    'user_tbl.lastName as requester_lastName',
                    'property_tbl.propertyTitle as property_title',
                    'handler.firstName as handler_firstName',
                    'handler.lastName as handler_lastName'
                )
                ->where('repairs.repair_status', $status)
                ->orderBy('repairs.repair_date', 'desc')
                ->get();

            return response()->json([
                'success' => true,
                'message' => 'Repairs retrieved successfully',
                'data' => $repairs,
                'count' => $repairs->count(),
                'status' => $status
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'An error occurred while retrieving repairs',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get repairs by type.
     */
    public function getByType($type)
    {
        try {
            $repairs = DB::table('repairs')
                ->leftJoin('user_tbl', DB::raw('CAST(repairs.apartment_owner_id AS CHAR)'), '=', DB::raw('CAST(user_tbl.userID AS CHAR)'))
                ->leftJoin('property_tbl', DB::raw('CAST(repairs.property_id AS CHAR)'), '=', DB::raw('CAST(property_tbl.propertyID AS CHAR)'))
                ->leftJoin('user_tbl as handler', DB::raw('CAST(repairs.repair_done_by AS CHAR)'), '=', DB::raw('CAST(handler.userID AS CHAR)'))
                ->select(
                    'repairs.*',
                    'user_tbl.firstName as requester_firstName',
                    'user_tbl.lastName as requester_lastName',
                    'property_tbl.propertyTitle as property_title',
                    'handler.firstName as handler_firstName',
                    'handler.lastName as handler_lastName'
                )
                ->where('repairs.items_repaired', 'LIKE', "%{$type}%")
                ->orderBy('repairs.repair_date', 'desc')
                ->get();

            return response()->json([
                'success' => true,
                'message' => 'Repairs retrieved successfully',
                'data' => $repairs,
                'count' => $repairs->count(),
                'repair_type' => $type
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'An error occurred while retrieving repairs',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get repair statistics.
     */
    public function getStats()
    {
        try {
            $totalRequests = DB::table('repairs')->count();
            $pendingRequests = DB::table('repairs')->where('repair_status', 'pending')->count();
            $inProgressRequests = DB::table('repairs')->where('repair_status', 'in_progress')->count();
            $completedRequests = DB::table('repairs')->where('repair_status', 'completed')->count();

            $statusStats = DB::table('repairs')
                ->select('repair_status as status_field', DB::raw('count(*) as count'))
                ->groupBy('repair_status')
                ->get();

            $typeStats = DB::table('repairs')
                ->select('items_repaired', DB::raw('count(*) as count'))
                ->groupBy('items_repaired')
                ->orderBy('count', 'desc')
                ->get();

            $totalCost = DB::table('repairs')
                ->where('repair_status', 'completed')
                ->sum('repair_amount');

            $monthlyRequests = DB::table('repairs')
                ->where('repair_date', '>=', now()->subDays(30))
                ->count();

            return response()->json([
                'success' => true,
                'message' => 'Repair statistics retrieved successfully',
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