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
                ->leftJoin('property_tbl', DB::raw('CAST(repairs.property_id AS CHAR)'), '=', DB::raw('CAST(property_tbl.propertyID AS CHAR)'))
                ->select(
                    'repairs.*',
                    'property_tbl.propertyTitle as property_title',
                    'property_tbl.address as property_address'
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
                ->leftJoin('property_tbl', DB::raw('CAST(repairs.property_id AS CHAR)'), '=', DB::raw('CAST(property_tbl.propertyID AS CHAR)'))
                ->select(
                    'repairs.*',
                    'property_tbl.propertyTitle as property_title',
                    'property_tbl.address as property_address'
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
            'title_of_repair' => 'required|string|max:100',
            'property_id' => 'required|string|max:50',
            'type_of_repair' => 'nullable|string|max:100',
            'items_repaired' => 'required|string',
            'who_is_handling_the_repair' => 'nullable|string|max:100',
            'description_of_the_repair' => 'nullable|string',
            'cost_of_repair' => 'required|numeric|min:0',
            'repair_status' => 'required|in:pending,on going,completed',
            'feedback' => 'nullable|string',
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
                'title_of_repair' => $request->title_of_repair,
                'property_id' => $request->property_id,
                'type_of_repair' => $request->type_of_repair ?? null,
                'items_repaired' => $request->items_repaired,
                'who_is_handling_the_repair' => $request->who_is_handling_repair ?? $request->who_is_handling_the_repair ?? null,
                'description_of_the_repair' => $request->description_of_repair ?? $request->input('description_of_the repair') ?? $request->description_of_the_repair ?? null,
                'cost_of_repair' => $request->cost_of_repair,
                'repair_status' => $request->repair_status,
                'feedback' => $request->feedback ?? null,
                'image_folder' => $imageFolder,
                'images_paths' => !empty($imagesPaths) ? json_encode($imagesPaths) : null,
                'repair_date' => now()->toDateString(),
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
            'title_of_repair' => 'sometimes|string|max:100',
            'property_id' => 'sometimes|string|max:50',
            'type_of_repair' => 'sometimes|string|max:100',
            'items_repaired' => 'sometimes|string',
            'who_is_handling_the_repair' => 'sometimes|string|max:100',
            'description_of_the_repair' => 'sometimes|string',
            'cost_of_repair' => 'sometimes|numeric|min:0',
            'repair_status' => 'sometimes|in:pending,on going,completed',
            'feedback' => 'sometimes|string',
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
                'title_of_repair', 'property_id', 'type_of_repair', 'items_repaired', 'who_is_handling_the_repair', 'cost_of_repair', 'repair_status', 'feedback'
            ]);
            
            // Handle description field with backward compatibility
            if ($request->has('description_of_repair')) {
                $updateData['description_of_the_repair'] = $request->description_of_repair;
            } elseif ($request->has('description_of_the repair')) {
                $updateData['description_of_the_repair'] = $request->input('description_of_the repair');
            } elseif ($request->has('description_of_the_repair')) {
                $updateData['description_of_the_repair'] = $request->description_of_the_repair;
            }

            // Update image data if new images were uploaded
            if (!empty($newImagesPaths)) {
                $updateData['image_folder'] = $imageFolder;
                $updateData['images_paths'] = json_encode($allImagesPaths);
            }

            DB::table('repairs')->where('id', $id)->update($updateData);
            
            $updatedRepair = DB::table('repairs')
                ->leftJoin('property_tbl', DB::raw('CAST(repairs.property_id AS CHAR)'), '=', DB::raw('CAST(property_tbl.propertyID AS CHAR)'))
                ->leftJoin('user_tbl as handler', DB::raw('CAST(repairs.who_is_handling_the_repair AS CHAR)'), '=', DB::raw('CAST(handler.userID AS CHAR)'))
                ->select(
                    'repairs.*',
                    'property_tbl.propertyTitle as property_title',
                    'property_tbl.address as property_address',
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
                ->leftJoin('property_tbl', DB::raw('CAST(repairs.property_id AS CHAR)'), '=', DB::raw('CAST(property_tbl.propertyID AS CHAR)'))
                ->leftJoin('user_tbl as handler', DB::raw('CAST(repairs.who_is_handling_the_repair AS CHAR)'), '=', DB::raw('CAST(handler.userID AS CHAR)'))
                ->select(
                    'repairs.*',
                    'property_tbl.propertyTitle as property_title',
                    'property_tbl.address as property_address',
                    'handler.firstName as handler_firstName',
                    'handler.lastName as handler_lastName'
                )
                ->where('repairs.property_id', $userId)
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
            'status' => 'required|in:pending,on going,completed'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid status. Must be: pending, on going, or completed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $repairs = DB::table('repairs')
                ->leftJoin('property_tbl', DB::raw('CAST(repairs.property_id AS CHAR)'), '=', DB::raw('CAST(property_tbl.propertyID AS CHAR)'))
                ->leftJoin('user_tbl as handler', DB::raw('CAST(repairs.who_is_handling_the_repair AS CHAR)'), '=', DB::raw('CAST(handler.userID AS CHAR)'))
                ->select(
                    'repairs.*',
                    'property_tbl.propertyTitle as property_title',
                    'property_tbl.address as property_address',
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
                ->leftJoin('property_tbl', DB::raw('CAST(repairs.property_id AS CHAR)'), '=', DB::raw('CAST(property_tbl.propertyID AS CHAR)'))
                ->leftJoin('user_tbl as handler', DB::raw('CAST(repairs.who_is_handling_the_repair AS CHAR)'), '=', DB::raw('CAST(handler.userID AS CHAR)'))
                ->select(
                    'repairs.*',
                    'property_tbl.propertyTitle as property_title',
                    'property_tbl.address as property_address',
                    'handler.firstName as handler_firstName',
                    'handler.lastName as handler_lastName'
                )
                ->where('repairs.type_of_repair', 'LIKE', "%{$type}%")
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
     * Get repairs from this week.
     */
    public function getThisWeek()
    {
        try {
            $startOfWeek = now()->startOfWeek();
            $endOfWeek = now()->endOfWeek();
            
            $repairs = DB::table('repairs')
                ->leftJoin('property_tbl', DB::raw('CAST(repairs.property_id AS CHAR)'), '=', DB::raw('CAST(property_tbl.propertyID AS CHAR)'))
                ->select(
                    'repairs.*',
                    'property_tbl.propertyTitle as property_title',
                    'property_tbl.address as property_address'
                )
                ->whereDate('repairs.repair_date', '>=', $startOfWeek)
                ->whereDate('repairs.repair_date', '<=', $endOfWeek)
                ->orderBy('repairs.repair_date', 'desc')
                ->get();

            return response()->json([
                'success' => true,
                'message' => 'This week repairs retrieved successfully',
                'data' => $repairs,
                'count' => $repairs->count(),
                'period' => 'this_week',
                'start_date' => $startOfWeek->toDateString(),
                'end_date' => $endOfWeek->toDateString()
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'An error occurred while retrieving this week repairs',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get repairs from this month.
     */
    public function getThisMonth()
    {
        try {
            $startOfMonth = now()->startOfMonth();
            $endOfMonth = now()->endOfMonth();
            
            $repairs = DB::table('repairs')
                ->leftJoin('property_tbl', DB::raw('CAST(repairs.property_id AS CHAR)'), '=', DB::raw('CAST(property_tbl.propertyID AS CHAR)'))
                ->select(
                    'repairs.*',
                    'property_tbl.propertyTitle as property_title',
                    'property_tbl.address as property_address'
                )
                ->whereDate('repairs.repair_date', '>=', $startOfMonth)
                ->whereDate('repairs.repair_date', '<=', $endOfMonth)
                ->orderBy('repairs.repair_date', 'desc')
                ->get();

            return response()->json([
                'success' => true,
                'message' => 'This month repairs retrieved successfully',
                'data' => $repairs,
                'count' => $repairs->count(),
                'period' => 'this_month',
                'start_date' => $startOfMonth->toDateString(),
                'end_date' => $endOfMonth->toDateString()
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'An error occurred while retrieving this month repairs',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get repairs from this year.
     */
    public function getThisYear()
    {
        try {
            $startOfYear = now()->startOfYear();
            $endOfYear = now()->endOfYear();
            
            $repairs = DB::table('repairs')
                ->leftJoin('property_tbl', DB::raw('CAST(repairs.property_id AS CHAR)'), '=', DB::raw('CAST(property_tbl.propertyID AS CHAR)'))
                ->select(
                    'repairs.*',
                    'property_tbl.propertyTitle as property_title',
                    'property_tbl.address as property_address'
                )
                ->whereDate('repairs.repair_date', '>=', $startOfYear)
                ->whereDate('repairs.repair_date', '<=', $endOfYear)
                ->orderBy('repairs.repair_date', 'desc')
                ->get();

            return response()->json([
                'success' => true,
                'message' => 'This year repairs retrieved successfully',
                'data' => $repairs,
                'count' => $repairs->count(),
                'period' => 'this_year',
                'start_date' => $startOfYear->toDateString(),
                'end_date' => $endOfYear->toDateString()
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'An error occurred while retrieving this year repairs',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get repairs by priority.
     */
    public function getByPriority($priority)
    {
        try {
            $repairs = DB::table('repairs')
                ->leftJoin('property_tbl', DB::raw('CAST(repairs.property_id AS CHAR)'), '=', DB::raw('CAST(property_tbl.propertyID AS CHAR)'))
                ->leftJoin('user_tbl as handler', DB::raw('CAST(repairs.who_is_handling_the_repair AS CHAR)'), '=', DB::raw('CAST(handler.userID AS CHAR)'))
                ->select(
                    'repairs.*',
                    'property_tbl.propertyTitle as property_title',
                    'property_tbl.address as property_address',
                    'handler.firstName as handler_firstName',
                    'handler.lastName as handler_lastName'
                )
                ->where('repairs.repair_status', $priority)
                ->orderBy('repairs.repair_date', 'desc')
                ->get();

            return response()->json([
                'success' => true,
                'message' => 'Repairs retrieved successfully',
                'data' => $repairs,
                'count' => $repairs->count(),
                'priority' => $priority
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
     * Get repairs by date range.
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
            $repairs = DB::table('repairs')
                ->leftJoin('property_tbl', DB::raw('CAST(repairs.property_id AS CHAR)'), '=', DB::raw('CAST(property_tbl.propertyID AS CHAR)'))
                ->leftJoin('user_tbl as handler', DB::raw('CAST(repairs.who_is_handling_the_repair AS CHAR)'), '=', DB::raw('CAST(handler.userID AS CHAR)'))
                ->select(
                    'repairs.*',
                    'property_tbl.propertyTitle as property_title',
                    'property_tbl.address as property_address',
                    'handler.firstName as handler_firstName',
                    'handler.lastName as handler_lastName'
                )
                ->whereDate('repairs.repair_date', '>=', $request->start_date)
                ->whereDate('repairs.repair_date', '<=', $request->end_date)
                ->orderBy('repairs.repair_date', 'desc')
                ->get();

            return response()->json([
                'success' => true,
                'message' => 'Repairs retrieved successfully',
                'data' => $repairs,
                'count' => $repairs->count(),
                'start_date' => $request->start_date,
                'end_date' => $request->end_date
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
            $onGoingRequests = DB::table('repairs')->where('repair_status', 'on going')->count();
            $completedRequests = DB::table('repairs')->where('repair_status', 'completed')->count();

            $statusStats = DB::table('repairs')
                ->select('repair_status as status_field', DB::raw('count(*) as count'))
                ->groupBy('repair_status')
                ->get();

            $typeStats = DB::table('repairs')
                ->select('type_of_repair', DB::raw('count(*) as count'))
                ->groupBy('type_of_repair')
                ->orderBy('count', 'desc')
                ->get();

            $totalCost = DB::table('repairs')
                ->where('repair_status', 'completed')
                ->sum('cost_of_repair');

            $monthlyRequests = DB::table('repairs')
                ->where('repair_date', '>=', now()->subDays(30))
                ->count();

            return response()->json([
                'success' => true,
                'message' => 'Repair statistics retrieved successfully',
                'total_requests' => $totalRequests,
                'pending_requests' => $pendingRequests,
                'on_going_requests' => $onGoingRequests,
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