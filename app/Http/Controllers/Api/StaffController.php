<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Storage;

class StaffController extends Controller
{
    /**
     * Display a listing of all staff (Improved version of your original).
     */
    public function index()
    {
        try {
            $staff = DB::table('admin_tbl')
                ->select(
                    'adminID',
                    'firstName',
                    'lastName', 
                    'email',
                    'phone',
                    'role',
                    'department',
                    'status',
                    'profile_picture',
                    'date_hired',
                    'salary',
                    'manager_id',
                    'last_login',
                    'created_at',
                    'updated_at'
                )
                ->orderBy('created_at', 'desc')
                ->get();

            return response()->json([
                'success' => true,
                'message' => 'Staff retrieved successfully',
                'data' => $staff,
                'count' => $staff->count()
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'An error occurred while retrieving staff',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Display the specified staff member (Improved version of your original).
     */
    public function show(string $id)
    {
        try {
            $staff = DB::table('admin_tbl')
                ->leftJoin('admin_tbl as manager', 'admin_tbl.manager_id', '=', 'manager.adminID')
                ->select(
                    'admin_tbl.*',
                    'manager.firstName as manager_firstName',
                    'manager.lastName as manager_lastName',
                    'manager.email as manager_email'
                )
                ->where('admin_tbl.adminID', $id)
                ->first();

            if (!$staff) {
                return response()->json([
                    'success' => false,
                    'message' => 'Staff member not found'
                ], 404);
            }

            // Get staff members under this person (if they are a manager)
            $subordinates = DB::table('admin_tbl')
                ->where('manager_id', $id)
                ->select('adminID', 'firstName', 'lastName', 'email', 'role', 'department')
                ->get();

            return response()->json([
                'success' => true,
                'message' => 'Staff member retrieved successfully',
                'data' => [
                    'staff_info' => $staff,
                    'subordinates' => $subordinates,
                    'subordinates_count' => $subordinates->count()
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'An error occurred while retrieving staff member',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Store a newly created staff member.
     */
    public function store(Request $request)
    {
        // Enhanced validation rules
        $validator = Validator::make($request->all(), [
            'firstName' => 'required|string|max:255',
            'lastName' => 'required|string|max:255',
            'email' => 'required|email|unique:admin_tbl,email|max:255',
            'phone' => 'required|string|max:20',
            'password' => 'required|string|min:8|max:255',
            'role' => 'required|string|in:FX,HR,IT,Finance,Marketing,Operations,Management,Admin,Support,Sales',
            'department' => 'required|string|max:100',
            'salary' => 'nullable|numeric|min:0',
            'date_hired' => 'nullable|date',
            'manager_id' => 'nullable|string|exists:admin_tbl,adminID',
            'status' => 'nullable|in:active,inactive,suspended,terminated',
            'profile_picture' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:2048',
            'address' => 'nullable|string|max:500',
            'emergency_contact' => 'nullable|string|max:255',
            'emergency_phone' => 'nullable|string|max:20',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation error',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            // Handle profile picture upload
            $profilePicturePath = null;
            if ($request->hasFile('profile_picture')) {
                $image = $request->file('profile_picture');
                $imageName = 'staff_' . time() . '.' . $image->getClientOriginalExtension();
                $profilePicturePath = $image->storeAs('staff_images', $imageName, 'public');
            }

            // Generate unique staff ID
            $staffId = 'STF' . date('Y') . mt_rand(1000, 9999);

            // Prepare data for insertion
            $data = [
                'adminID' => $staffId,
                'firstName' => $request->firstName,
                'lastName' => $request->lastName,
                'email' => $request->email,
                'phone' => $request->phone,
                'password' => bcrypt($request->password),
                'role' => $request->role,
                'department' => $request->department,
                'salary' => $request->salary,
                'date_hired' => $request->date_hired ?? now()->toDateString(),
                'manager_id' => $request->manager_id,
                'status' => $request->status ?? 'active',
                'profile_picture' => $profilePicturePath,
                'address' => $request->address,
                'emergency_contact' => $request->emergency_contact,
                'emergency_phone' => $request->emergency_phone,
                'created_at' => now(),
                'updated_at' => now(),
            ];

            // Insert staff member
            $inserted = DB::table('admin_tbl')->insert($data);

            if ($inserted) {
                // Retrieve the created staff member (excluding password)
                $createdStaff = DB::table('admin_tbl')
                    ->where('adminID', $staffId)
                    ->select('adminID', 'firstName', 'lastName', 'email', 'phone', 'role', 
                             'department', 'status', 'profile_picture', 'date_hired', 'salary', 'created_at')
                    ->first();

                return response()->json([
                    'success' => true,
                    'message' => 'Staff member created successfully',
                    'data' => $createdStaff,
                    'staff_id' => $staffId
                ], 201);
            } else {
                return response()->json([
                    'success' => false,
                    'message' => 'Failed to create staff member'
                ], 500);
            }

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'An error occurred while creating staff member',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update the specified staff member.
     */
    public function update(Request $request, string $id)
    {
        $staff = DB::table('admin_tbl')->where('adminID', $id)->first();

        if (!$staff) {
            return response()->json([
                'success' => false,
                'message' => 'Staff member not found'
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'firstName' => 'sometimes|string|max:255',
            'lastName' => 'sometimes|string|max:255',
            'email' => 'sometimes|email|unique:admin_tbl,email,' . $id . ',adminID|max:255',
            'phone' => 'sometimes|string|max:20',
            'role' => 'sometimes|string|in:FX,HR,IT,Finance,Marketing,Operations,Management,Admin,Support,Sales',
            'department' => 'sometimes|string|max:100',
            'salary' => 'nullable|numeric|min:0',
            'manager_id' => 'nullable|string|exists:admin_tbl,adminID',
            'status' => 'sometimes|in:active,inactive,suspended,terminated',
            'profile_picture' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:2048',
            'address' => 'nullable|string|max:500',
            'emergency_contact' => 'nullable|string|max:255',
            'emergency_phone' => 'nullable|string|max:20',
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
                'firstName', 'lastName', 'email', 'phone', 'role', 'department',
                'salary', 'manager_id', 'status', 'address', 'emergency_contact', 'emergency_phone'
            ]);

            // Handle profile picture upload
            if ($request->hasFile('profile_picture')) {
                // Delete old profile picture if exists
                if ($staff->profile_picture) {
                    Storage::disk('public')->delete($staff->profile_picture);
                }

                $image = $request->file('profile_picture');
                $imageName = 'staff_' . time() . '.' . $image->getClientOriginalExtension();
                $updateData['profile_picture'] = $image->storeAs('staff_images', $imageName, 'public');
            }

            $updateData['updated_at'] = now();

            DB::table('admin_tbl')->where('adminID', $id)->update($updateData);
            
            $updatedStaff = DB::table('admin_tbl')
                ->where('adminID', $id)
                ->select('adminID', 'firstName', 'lastName', 'email', 'phone', 'role',
                         'department', 'status', 'profile_picture', 'salary', 'updated_at')
                ->first();

            return response()->json([
                'success' => true,
                'message' => 'Staff member updated successfully',
                'data' => $updatedStaff
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'An error occurred while updating staff member',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Remove the specified staff member.
     */
    public function destroy(string $id)
    {
        $staff = DB::table('admin_tbl')->where('adminID', $id)->first();

        if (!$staff) {
            return response()->json([
                'success' => false,
                'message' => 'Staff member not found'
            ], 404);
        }

        try {
            // Delete profile picture if exists
            if ($staff->profile_picture) {
                Storage::disk('public')->delete($staff->profile_picture);
            }

            DB::table('admin_tbl')->where('adminID', $id)->delete();

            return response()->json([
                'success' => true,
                'message' => 'Staff member deleted successfully'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'An error occurred while deleting staff member',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get staff by role (Improved version of your original).
     */
    public function getByRole($role)
    {
        $validator = Validator::make(['role' => $role], [
            'role' => 'required|in:FX,HR,IT,Finance,Marketing,Operations,Management,Admin,Support,Sales'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid role',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $staff = DB::table('admin_tbl')
                ->leftJoin('admin_tbl as manager', 'admin_tbl.manager_id', '=', 'manager.adminID')
                ->select(
                    'admin_tbl.*',
                    'manager.firstName as manager_firstName',
                    'manager.lastName as manager_lastName'
                )
                ->where('admin_tbl.role', $role)
                ->orderBy('admin_tbl.firstName', 'asc')
                ->get();

            return response()->json([
                'success' => true,
                'message' => 'Staff retrieved successfully',
                'data' => $staff,
                'count' => $staff->count(),
                'role' => $role
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'An error occurred while retrieving staff',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get staff by department.
     */
    public function getByDepartment($department)
    {
        try {
            $staff = DB::table('admin_tbl')
                ->leftJoin('admin_tbl as manager', 'admin_tbl.manager_id', '=', 'manager.adminID')
                ->select(
                    'admin_tbl.*',
                    'manager.firstName as manager_firstName',
                    'manager.lastName as manager_lastName'
                )
                ->where('admin_tbl.department', $department)
                ->orderBy('admin_tbl.role', 'asc')
                ->get();

            return response()->json([
                'success' => true,
                'message' => 'Department staff retrieved successfully',
                'data' => $staff,
                'count' => $staff->count(),
                'department' => $department
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'An error occurred while retrieving department staff',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get active staff members.
     */
    public function getActive()
    {
        try {
            $staff = DB::table('admin_tbl')
                ->where('status', 'active')
                ->select('adminID', 'firstName', 'lastName', 'email', 'phone', 'role', 
                         'department', 'profile_picture', 'date_hired')
                ->orderBy('firstName', 'asc')
                ->get();

            return response()->json([
                'success' => true,
                'message' => 'Active staff retrieved successfully',
                'data' => $staff,
                'count' => $staff->count()
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'An error occurred while retrieving active staff',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Search staff members.
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
            $staff = DB::table('admin_tbl')
                ->where(function($q) use ($query) {
                    $q->where('firstName', 'LIKE', "%{$query}%")
                      ->orWhere('lastName', 'LIKE', "%{$query}%")
                      ->orWhere('email', 'LIKE', "%{$query}%")
                      ->orWhere('role', 'LIKE', "%{$query}%")
                      ->orWhere('department', 'LIKE', "%{$query}%");
                })
                ->select('adminID', 'firstName', 'lastName', 'email', 'phone', 'role',
                         'department', 'status', 'profile_picture', 'date_hired')
                ->orderBy('firstName', 'asc')
                ->get();

            return response()->json([
                'success' => true,
                'message' => 'Staff search results',
                'data' => $staff,
                'count' => $staff->count(),
                'search_query' => $query
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'An error occurred while searching staff',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get staff statistics.
     */
    public function getStats()
    {
        try {
            $totalStaff = DB::table('admin_tbl')->count();
            $activeStaff = DB::table('admin_tbl')->where('status', 'active')->count();
            $inactiveStaff = DB::table('admin_tbl')->where('status', 'inactive')->count();
            
            $roleStats = DB::table('admin_tbl')
                ->select('role', DB::raw('count(*) as count'))
                ->groupBy('role')
                ->orderBy('count', 'desc')
                ->get();

            $departmentStats = DB::table('admin_tbl')
                ->select('department', DB::raw('count(*) as count'))
                ->groupBy('department')
                ->orderBy('count', 'desc')
                ->get();

            $statusStats = DB::table('admin_tbl')
                ->select('status', DB::raw('count(*) as count'))
                ->groupBy('status')
                ->get();

            $recentHires = DB::table('admin_tbl')
                ->where('date_hired', '>=', now()->subDays(30))
                ->count();

            $averageSalary = DB::table('admin_tbl')
                ->where('status', 'active')
                ->whereNotNull('salary')
                ->avg('salary');

            return response()->json([
                'success' => true,
                'message' => 'Staff statistics retrieved successfully',
                'total_staff' => $totalStaff,
                'active_staff' => $activeStaff,
                'inactive_staff' => $inactiveStaff,
                'recent_hires_30_days' => $recentHires,
                'average_salary' => round($averageSalary ?? 0, 2),
                'role_breakdown' => $roleStats,
                'department_breakdown' => $departmentStats,
                'status_breakdown' => $statusStats
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'An error occurred while retrieving staff statistics',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get managers and their subordinates.
     */
    public function getManagersHierarchy()
    {
        try {
            $managers = DB::table('admin_tbl')
                ->whereIn('adminID', function($query) {
                    $query->select('manager_id')
                          ->from('admin_tbl')
                          ->whereNotNull('manager_id')
                          ->distinct();
                })
                ->select('adminID', 'firstName', 'lastName', 'email', 'role', 'department')
                ->get();

            $hierarchy = [];
            foreach ($managers as $manager) {
                $subordinates = DB::table('admin_tbl')
                    ->where('manager_id', $manager->adminID)
                    ->select('adminID', 'firstName', 'lastName', 'email', 'role', 'department', 'status')
                    ->get();

                $hierarchy[] = [
                    'manager' => $manager,
                    'subordinates' => $subordinates,
                    'subordinates_count' => $subordinates->count()
                ];
            }

            return response()->json([
                'success' => true,
                'message' => 'Managers hierarchy retrieved successfully',
                'data' => $hierarchy,
                'managers_count' => count($hierarchy)
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'An error occurred while retrieving managers hierarchy',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get CX staff members (Improved version of your original).
     */
    public function getCXStaff($id = null)
    {
        try {
            $query = DB::table('admin_tbl')
                ->leftJoin('admin_tbl as manager', 'admin_tbl.manager_id', '=', 'manager.adminID')
                ->select(
                    'admin_tbl.adminID',
                    'admin_tbl.firstName',
                    'admin_tbl.lastName',
                    'admin_tbl.email',
                    'admin_tbl.phone',
                    'admin_tbl.role',
                    'admin_tbl.department',
                    'admin_tbl.staff_dept',
                    'admin_tbl.status',
                    'admin_tbl.profile_picture',
                    'admin_tbl.date_hired',
                    'admin_tbl.salary',
                    'admin_tbl.last_login',
                    'admin_tbl.created_at',
                    'manager.firstName as manager_firstName',
                    'manager.lastName as manager_lastName'
                )
                ->where('admin_tbl.staff_dept', 'cx');

            if ($id) {
                $cxStaff = $query->where('admin_tbl.adminID', $id)->first();
                
                if (!$cxStaff) {
                    return response()->json([
                        'success' => false,
                        'message' => 'CX staff member not found'
                    ], 404);
                }

                // Get CX staff performance metrics (if available)
                $performanceMetrics = [
                    'total_calls_handled' => DB::table('call_logs')
                        ->where('assigned_staff', $id)
                        ->count(),
                    'customer_ratings' => DB::table('feedback')
                        ->where('staff_id', $id)
                        ->avg('rate'),
                    'resolved_issues' => DB::table('repair_request')
                        ->where('assigned_technician', $id)
                        ->where('repair_request_status', 'completed')
                        ->count()
                ];

                return response()->json([
                    'success' => true,
                    'message' => 'CX staff member retrieved successfully',
                    'data' => [
                        'staff_info' => $cxStaff,
                        'performance_metrics' => $performanceMetrics
                    ]
                ]);
            } else {
                $cxStaff = $query->orderBy('admin_tbl.firstName', 'asc')->get();

                // Get CX department statistics
                $cxStats = [
                    'total_cx_staff' => $cxStaff->count(),
                    'active_cx_staff' => $cxStaff->where('status', 'active')->count(),
                    'average_experience' => $cxStaff->where('date_hired', '!=', null)
                        ->map(function($staff) {
                            return now()->diffInMonths($staff->date_hired);
                        })->avg(),
                    'roles_breakdown' => $cxStaff->groupBy('role')->map(function($group) {
                        return $group->count();
                    })
                ];

                return response()->json([
                    'success' => true,
                    'message' => 'CX staff retrieved successfully',
                    'data' => $cxStaff,
                    'count' => $cxStaff->count(),
                    'cx_statistics' => $cxStats
                ]);
            }

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'An error occurred while retrieving CX staff',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get CX staff performance dashboard.
     */
    public function getCXDashboard()
    {
        try {
            // Get all active CX staff
            $cxStaff = DB::table('admin_tbl')
                ->where('staff_dept', 'cx')
                ->where('status', 'active')
                ->get();

            $dashboard = [];
            
            foreach ($cxStaff as $staff) {
                $staffMetrics = [
                    'staff_info' => [
                        'adminID' => $staff->adminID,
                        'name' => $staff->firstName . ' ' . $staff->lastName,
                        'email' => $staff->email,
                        'role' => $staff->role,
                        'profile_picture' => $staff->profile_picture
                    ],
                    'metrics' => [
                        'calls_handled_today' => DB::table('call_logs')
                            ->where('assigned_staff', $staff->adminID)
                            ->whereDate('date_of_call', today())
                            ->count(),
                        'calls_handled_month' => DB::table('call_logs')
                            ->where('assigned_staff', $staff->adminID)
                            ->whereMonth('date_of_call', now()->month)
                            ->count(),
                        'avg_customer_rating' => round(DB::table('feedback')
                            ->where('staff_id', $staff->adminID)
                            ->avg('rate') ?? 0, 2),
                        'resolved_repairs' => DB::table('repair_request')
                            ->where('assigned_technician', $staff->adminID)
                            ->where('repair_request_status', 'completed')
                            ->count(),
                        'pending_repairs' => DB::table('repair_request')
                            ->where('assigned_technician', $staff->adminID)
                            ->where('repair_request_status', 'pending')
                            ->count()
                    ]
                ];
                
                $dashboard[] = $staffMetrics;
            }

            // Overall CX department metrics
            $overallMetrics = [
                'total_cx_staff' => count($dashboard),
                'total_calls_today' => array_sum(array_column(array_column($dashboard, 'metrics'), 'calls_handled_today')),
                'total_calls_month' => array_sum(array_column(array_column($dashboard, 'metrics'), 'calls_handled_month')),
                'avg_department_rating' => round(array_sum(array_column(array_column($dashboard, 'metrics'), 'avg_customer_rating')) / max(count($dashboard), 1), 2),
                'total_resolved_repairs' => array_sum(array_column(array_column($dashboard, 'metrics'), 'resolved_repairs')),
                'total_pending_repairs' => array_sum(array_column(array_column($dashboard, 'metrics'), 'pending_repairs'))
            ];

            return response()->json([
                'success' => true,
                'message' => 'CX staff dashboard retrieved successfully',
                'staff_performance' => $dashboard,
                'department_overview' => $overallMetrics
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'An error occurred while retrieving CX dashboard',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get TSR staff members (Improved version of your original).
     */
    public function getTSRStaff($id = null)
    {
        try {
            $query = DB::table('admin_tbl')
                ->leftJoin('admin_tbl as manager', 'admin_tbl.manager_id', '=', 'manager.adminID')
                ->select(
                    'admin_tbl.adminID',
                    'admin_tbl.firstName',
                    'admin_tbl.lastName',
                    'admin_tbl.email',
                    'admin_tbl.phone',
                    'admin_tbl.role',
                    'admin_tbl.department',
                    'admin_tbl.staff_dept',
                    'admin_tbl.status',
                    'admin_tbl.profile_picture',
                    'admin_tbl.date_hired',
                    'admin_tbl.salary',
                    'admin_tbl.last_login',
                    'admin_tbl.created_at',
                    'manager.firstName as manager_firstName',
                    'manager.lastName as manager_lastName'
                )
                ->where('admin_tbl.staff_dept', 'tsr');

            if ($id) {
                $tsrStaff = $query->where('admin_tbl.adminID', $id)->first();
                
                if (!$tsrStaff) {
                    return response()->json([
                        'success' => false,
                        'message' => 'TSR staff member not found'
                    ], 404);
                }

                // Get TSR staff performance metrics
                $performanceMetrics = [
                    'total_inspections' => DB::table('inspection_tbl')
                        ->where('assigned_tsr', $id)
                        ->count(),
                    'completed_inspections' => DB::table('inspection_tbl')
                        ->where('assigned_tsr', $id)
                        ->where('inspection_status', 'completed')
                        ->count(),
                    'pending_inspections' => DB::table('inspection_tbl')
                        ->where('assigned_tsr', $id)
                        ->where('inspection_status', 'pending')
                        ->count(),
                    'inspections_this_month' => DB::table('inspection_tbl')
                        ->where('assigned_tsr', $id)
                        ->whereMonth('inspectionDate', now()->month)
                        ->count(),
                    'average_completion_time' => DB::table('inspection_tbl')
                        ->where('assigned_tsr', $id)
                        ->where('inspection_status', 'completed')
                        ->whereNotNull('date_inspection_completed_canceled')
                        ->selectRaw('AVG(DATEDIFF(date_inspection_completed_canceled, inspectionDate)) as avg_days')
                        ->first()->avg_days ?? 0,
                    'customer_feedback_rating' => DB::table('inspection_tbl')
                        ->where('assigned_tsr', $id)
                        ->whereNotNull('customer_inspec_feedback')
                        ->avg('customer_inspec_feedback') ?? 0
                ];

                return response()->json([
                    'success' => true,
                    'message' => 'TSR staff member retrieved successfully',
                    'data' => [
                        'staff_info' => $tsrStaff,
                        'performance_metrics' => $performanceMetrics
                    ]
                ]);
            } else {
                $tsrStaff = $query->orderBy('admin_tbl.firstName', 'asc')->get();

                // Get TSR department statistics
                $tsrStats = [
                    'total_tsr_staff' => $tsrStaff->count(),
                    'active_tsr_staff' => $tsrStaff->where('status', 'active')->count(),
                    'average_experience' => $tsrStaff->where('date_hired', '!=', null)
                        ->map(function($staff) {
                            return now()->diffInMonths($staff->date_hired);
                        })->avg(),
                    'roles_breakdown' => $tsrStaff->groupBy('role')->map(function($group) {
                        return $group->count();
                    }),
                    'total_inspections_assigned' => DB::table('inspection_tbl')
                        ->whereIn('assigned_tsr', $tsrStaff->pluck('adminID'))
                        ->count(),
                    'total_completed_inspections' => DB::table('inspection_tbl')
                        ->whereIn('assigned_tsr', $tsrStaff->pluck('adminID'))
                        ->where('inspection_status', 'completed')
                        ->count()
                ];

                return response()->json([
                    'success' => true,
                    'message' => 'TSR staff retrieved successfully',
                    'data' => $tsrStaff,
                    'count' => $tsrStaff->count(),
                    'tsr_statistics' => $tsrStats
                ]);
            }

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'An error occurred while retrieving TSR staff',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get TSR staff performance dashboard.
     */
    public function getTSRDashboard()
    {
        try {
            // Get all active TSR staff
            $tsrStaff = DB::table('admin_tbl')
                ->where('staff_dept', 'tsr')
                ->where('status', 'active')
                ->get();

            $dashboard = [];
            
            foreach ($tsrStaff as $staff) {
                $staffMetrics = [
                    'staff_info' => [
                        'adminID' => $staff->adminID,
                        'name' => $staff->firstName . ' ' . $staff->lastName,
                        'email' => $staff->email,
                        'role' => $staff->role,
                        'profile_picture' => $staff->profile_picture
                    ],
                    'metrics' => [
                        'inspections_today' => DB::table('inspection_tbl')
                            ->where('assigned_tsr', $staff->adminID)
                            ->whereDate('inspectionDate', today())
                            ->count(),
                        'inspections_this_week' => DB::table('inspection_tbl')
                            ->where('assigned_tsr', $staff->adminID)
                            ->whereBetween('inspectionDate', [now()->startOfWeek(), now()->endOfWeek()])
                            ->count(),
                        'inspections_this_month' => DB::table('inspection_tbl')
                            ->where('assigned_tsr', $staff->adminID)
                            ->whereMonth('inspectionDate', now()->month)
                            ->count(),
                        'completed_inspections' => DB::table('inspection_tbl')
                            ->where('assigned_tsr', $staff->adminID)
                            ->where('inspection_status', 'completed')
                            ->count(),
                        'pending_inspections' => DB::table('inspection_tbl')
                            ->where('assigned_tsr', $staff->adminID)
                            ->where('inspection_status', 'pending')
                            ->count(),
                        'overdue_inspections' => DB::table('inspection_tbl')
                            ->where('assigned_tsr', $staff->adminID)
                            ->where('inspection_status', 'pending')
                            ->whereDate('inspectionDate', '<', today())
                            ->count(),
                        'avg_completion_time' => round(DB::table('inspection_tbl')
                            ->where('assigned_tsr', $staff->adminID)
                            ->where('inspection_status', 'completed')
                            ->whereNotNull('date_inspection_completed_canceled')
                            ->selectRaw('AVG(DATEDIFF(date_inspection_completed_canceled, inspectionDate)) as avg_days')
                            ->first()->avg_days ?? 0, 1),
                        'customer_satisfaction' => round(DB::table('inspection_tbl')
                            ->where('assigned_tsr', $staff->adminID)
                            ->whereNotNull('customer_inspec_feedback')
                            ->avg('customer_inspec_feedback') ?? 0, 2)
                    ]
                ];
                
                $dashboard[] = $staffMetrics;
            }

            // Overall TSR department metrics
            $overallMetrics = [
                'total_tsr_staff' => count($dashboard),
                'total_inspections_today' => array_sum(array_column(array_column($dashboard, 'metrics'), 'inspections_today')),
                'total_inspections_week' => array_sum(array_column(array_column($dashboard, 'metrics'), 'inspections_this_week')),
                'total_inspections_month' => array_sum(array_column(array_column($dashboard, 'metrics'), 'inspections_this_month')),
                'total_completed' => array_sum(array_column(array_column($dashboard, 'metrics'), 'completed_inspections')),
                'total_pending' => array_sum(array_column(array_column($dashboard, 'metrics'), 'pending_inspections')),
                'total_overdue' => array_sum(array_column(array_column($dashboard, 'metrics'), 'overdue_inspections')),
                'avg_department_completion_time' => round(array_sum(array_column(array_column($dashboard, 'metrics'), 'avg_completion_time')) / max(count($dashboard), 1), 1),
                'avg_department_satisfaction' => round(array_sum(array_column(array_column($dashboard, 'metrics'), 'customer_satisfaction')) / max(count($dashboard), 1), 2)
            ];

            return response()->json([
                'success' => true,
                'message' => 'TSR staff dashboard retrieved successfully',
                'staff_performance' => $dashboard,
                'department_overview' => $overallMetrics
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'An error occurred while retrieving TSR dashboard',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get TSR workload distribution.
     */
    public function getTSRWorkload()
    {
        try {
            $tsrStaff = DB::table('admin_tbl')
                ->where('staff_dept', 'tsr')
                ->where('status', 'active')
                ->get();

            $workloadData = [];

            foreach ($tsrStaff as $staff) {
                $workload = [
                    'staff_info' => [
                        'adminID' => $staff->adminID,
                        'name' => $staff->firstName . ' ' . $staff->lastName,
                        'email' => $staff->email
                    ],
                    'current_workload' => [
                        'assigned_inspections' => DB::table('inspection_tbl')
                            ->where('assigned_tsr', $staff->adminID)
                            ->whereIn('inspection_status', ['pending', 'in_progress'])
                            ->count(),
                        'scheduled_today' => DB::table('inspection_tbl')
                            ->where('assigned_tsr', $staff->adminID)
                            ->whereDate('inspectionDate', today())
                            ->whereIn('inspection_status', ['pending', 'in_progress'])
                            ->count(),
                        'scheduled_this_week' => DB::table('inspection_tbl')
                            ->where('assigned_tsr', $staff->adminID)
                            ->whereBetween('inspectionDate', [now()->startOfWeek(), now()->endOfWeek()])
                            ->whereIn('inspection_status', ['pending', 'in_progress'])
                            ->count(),
                        'overdue_count' => DB::table('inspection_tbl')
                            ->where('assigned_tsr', $staff->adminID)
                            ->where('inspection_status', 'pending')
                            ->whereDate('inspectionDate', '<', today())
                            ->count()
                    ],
                    'capacity_status' => 'available' // This can be calculated based on workload rules
                ];

                // Determine capacity status
                $totalWorkload = $workload['current_workload']['assigned_inspections'];
                if ($totalWorkload >= 15) {
                    $workload['capacity_status'] = 'overloaded';
                } elseif ($totalWorkload >= 10) {
                    $workload['capacity_status'] = 'busy';
                } elseif ($totalWorkload >= 5) {
                    $workload['capacity_status'] = 'moderate';
                } else {
                    $workload['capacity_status'] = 'available';
                }

                $workloadData[] = $workload;
            }

            // Sort by workload (ascending for assignment purposes)
            usort($workloadData, function($a, $b) {
                return $a['current_workload']['assigned_inspections'] - $b['current_workload']['assigned_inspections'];
            });

            return response()->json([
                'success' => true,
                'message' => 'TSR workload distribution retrieved successfully',
                'data' => $workloadData,
                'summary' => [
                    'available_tsr' => count(array_filter($workloadData, function($tsr) {
                        return $tsr['capacity_status'] === 'available';
                    })),
                    'busy_tsr' => count(array_filter($workloadData, function($tsr) {
                        return in_array($tsr['capacity_status'], ['busy', 'moderate']);
                    })),
                    'overloaded_tsr' => count(array_filter($workloadData, function($tsr) {
                        return $tsr['capacity_status'] === 'overloaded';
                    }))
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'An error occurred while retrieving TSR workload',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}