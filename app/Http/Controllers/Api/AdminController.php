<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AdminController extends Controller
{
    /**
     * Get all CX staff members.
     */
    public function getCXUsers()
    {
        $cxUsers = DB::table('admin_tbl')
            ->where('staff_dept', 'cx')
            ->select('firstName', 'lastName', 'adminID', 'email')
            ->get();
        
        return response()->json([
            'success' => true,
            'message' => 'CX users retrieved successfully',
            'data' => $cxUsers,
            'count' => $cxUsers->count()
        ]);
    }

    /**
     * Get specific CX user by ID.
     */
    public function getCXUser($id)
    {
        $cxUser = DB::table('admin_tbl')
            ->where('staff_dept', 'cx')
            ->where('adminID', $id)
            ->select('firstName', 'lastName', 'adminID', 'email')
            ->first();
        
        if (!$cxUser) {
            return response()->json([
                'success' => false,
                'message' => 'CX user not found'
            ], 404);
        }
        
        return response()->json([
            'success' => true,
            'message' => 'CX user retrieved successfully',
            'data' => $cxUser
        ]);
    }

    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        //
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        //
    }
}
