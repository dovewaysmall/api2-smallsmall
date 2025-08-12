<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;

class UserController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $users = User::all();
        
        return response()->json([
            'success' => true,
            'message' => 'Users retrieved successfully',
            'data' => $users
        ]);
    }

    /**
     * Get users count.
     */
    public function count()
    {
        $count = User::count();
        
        return response()->json([
            'success' => true,
            'message' => 'Users count retrieved successfully',
            'count' => $count
        ]);
    }

    /**
     * Get users count for current month.
     */
    public function monthlyCount()
    {
        $count = User::whereMonth('created_at', now()->month)
                    ->whereYear('created_at', now()->year)
                    ->count();
        
        return response()->json([
            'success' => true,
            'message' => 'Monthly users count retrieved successfully',
            'count' => $count,
            'month' => now()->format('F Y')
        ]);
    }

    /**
     * Get users count for current year.
     */
    public function yearlyCount()
    {
        $count = User::whereYear('created_at', now()->year)
                    ->count();
        
        return response()->json([
            'success' => true,
            'message' => 'Yearly users count retrieved successfully',
            'count' => $count,
            'year' => now()->year
        ]);
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
        $user = User::find($id);
        
        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'User not found'
            ], 404);
        }
        
        return response()->json([
            'success' => true,
            'message' => 'User retrieved successfully',
            'data' => $user
        ]);
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
