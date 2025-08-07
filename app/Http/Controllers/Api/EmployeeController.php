<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class EmployeeController extends Controller
{
    /**
     * Display a listing of all employees.
     */
    public function index()
    {
        $employees = DB::table('employees')
            ->orderBy('date_created', 'desc')
            ->get();

        return response()->json([
            'success' => true,
            'message' => 'Employees retrieved successfully',
            'data' => $employees,
            'count' => $employees->count()
        ]);
    }

    /**
     * Store a newly created employee.
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'firstname' => 'required|string|max:255',
            'lastname' => 'required|string|max:255',
            'sex' => 'required|in:Male,Female',
            'address' => 'required|string|max:500',
            'state_of_origin' => 'required|string|max:100',
            'date_employed' => 'required|date',
            'role' => 'required|string|max:255',
            'department' => 'required|string|max:100',
            'line_manager' => 'nullable|integer',
            'start_salary' => 'required|numeric|min:0',
            'current_salary' => 'required|numeric|min:0',
            'job_description' => 'required|string|max:1000',
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
                'firstname' => $request->firstname,
                'lastname' => $request->lastname,
                'sex' => $request->sex,
                'address' => $request->address,
                'state_of_origin' => $request->state_of_origin,
                'date_employed' => $request->date_employed,
                'role' => $request->role,
                'department' => $request->department,
                'line_manager' => $request->line_manager,
                'start_salary' => $request->start_salary,
                'current_salary' => $request->current_salary,
                'job_description' => $request->job_description,
                'date_created' => now(),
            ];

            $employeeId = DB::table('employees')->insertGetId($data);
            $employee = DB::table('employees')->where('id', $employeeId)->first();

            return response()->json([
                'success' => true,
                'message' => 'Employee created successfully',
                'data' => $employee,
                'id' => $employeeId
            ], 201);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'An error occurred while creating employee',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Display the specified employee.
     */
    public function show(string $id)
    {
        $employee = DB::table('employees')->where('id', $id)->first();

        if (!$employee) {
            return response()->json([
                'success' => false,
                'message' => 'Employee not found'
            ], 404);
        }

        return response()->json([
            'success' => true,
            'message' => 'Employee retrieved successfully',
            'data' => $employee
        ]);
    }

    /**
     * Update the specified employee.
     */
    public function update(Request $request, string $id)
    {
        $employee = DB::table('employees')->where('id', $id)->first();

        if (!$employee) {
            return response()->json([
                'success' => false,
                'message' => 'Employee not found'
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'firstname' => 'sometimes|string|max:255',
            'lastname' => 'sometimes|string|max:255',
            'sex' => 'sometimes|in:Male,Female',
            'address' => 'sometimes|string|max:500',
            'state_of_origin' => 'sometimes|string|max:100',
            'role' => 'sometimes|string|max:255',
            'department' => 'sometimes|string|max:100',
            'line_manager' => 'nullable|integer',
            'current_salary' => 'sometimes|numeric|min:0',
            'job_description' => 'sometimes|string|max:1000',
            'date_exited' => 'nullable|date',
            'exit_type' => 'nullable|string|max:100',
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
                'firstname', 'lastname', 'sex', 'address', 'state_of_origin',
                'role', 'department', 'line_manager', 'current_salary', 
                'job_description', 'date_exited', 'exit_type'
            ]);

            DB::table('employees')->where('id', $id)->update($updateData);
            $updatedEmployee = DB::table('employees')->where('id', $id)->first();

            return response()->json([
                'success' => true,
                'message' => 'Employee updated successfully',
                'data' => $updatedEmployee
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'An error occurred while updating employee',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Remove the specified employee.
     */
    public function destroy(string $id)
    {
        $employee = DB::table('employees')->where('id', $id)->first();

        if (!$employee) {
            return response()->json([
                'success' => false,
                'message' => 'Employee not found'
            ], 404);
        }

        try {
            DB::table('employees')->where('id', $id)->delete();

            return response()->json([
                'success' => true,
                'message' => 'Employee deleted successfully'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'An error occurred while deleting employee',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get employees by department.
     */
    public function getByDepartment($department)
    {
        $employees = DB::table('employees')
            ->where('department', $department)
            ->whereNull('date_exited') // Only active employees
            ->orderBy('date_created', 'desc')
            ->get();

        return response()->json([
            'success' => true,
            'message' => 'Employees retrieved successfully',
            'data' => $employees,
            'count' => $employees->count(),
            'department' => $department
        ]);
    }

    /**
     * Get active employees (not exited).
     */
    public function getActiveEmployees()
    {
        $employees = DB::table('employees')
            ->whereNull('date_exited')
            ->orderBy('date_created', 'desc')
            ->get();

        return response()->json([
            'success' => true,
            'message' => 'Active employees retrieved successfully',
            'data' => $employees,
            'count' => $employees->count()
        ]);
    }

    /**
     * Get employees count and statistics.
     */
    public function getEmployeeStats()
    {
        $totalCount = DB::table('employees')->count();
        $activeCount = DB::table('employees')->whereNull('date_exited')->count();
        $exitedCount = DB::table('employees')->whereNotNull('date_exited')->count();
        
        $departmentStats = DB::table('employees')
            ->select('department', DB::raw('count(*) as count'))
            ->whereNull('date_exited')
            ->groupBy('department')
            ->get();

        return response()->json([
            'success' => true,
            'message' => 'Employee statistics retrieved successfully',
            'total_employees' => $totalCount,
            'active_employees' => $activeCount,
            'exited_employees' => $exitedCount,
            'department_breakdown' => $departmentStats
        ]);
    }

    /**
     * Search employees by name or role.
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

        $query = $request->input('query');
        $employees = DB::table('employees')
            ->where(function($q) use ($query) {
                $q->where('firstname', 'LIKE', "%{$query}%")
                  ->orWhere('lastname', 'LIKE', "%{$query}%")
                  ->orWhere('role', 'LIKE', "%{$query}%");
            })
            ->orderBy('date_created', 'desc')
            ->get();

        return response()->json([
            'success' => true,
            'message' => 'Employee search results',
            'data' => $employees,
            'count' => $employees->count(),
            'search_query' => $query
        ]);
    }
}
