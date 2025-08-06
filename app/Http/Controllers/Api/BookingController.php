<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class BookingController extends Controller
{
    /**
     * Get all bookings.
     */
    public function index()
    {
        $bookings = DB::table('bookings')->get();
        
        return response()->json([
            'success' => true,
            'message' => 'Bookings retrieved successfully',
            'data' => $bookings,
            'count' => $bookings->count()
        ]);
    }

    /**
     * Get bookings by userID.
     */
    public function getUserBookings($userID)
    {
        $bookings = DB::table('bookings')
            ->where('userID', $userID)
            ->get();
        
        if ($bookings->isEmpty()) {
            return response()->json([
                'success' => false,
                'message' => 'No bookings found for this user',
                'data' => [],
                'count' => 0
            ], 404);
        }
        
        return response()->json([
            'success' => true,
            'message' => 'User bookings retrieved successfully',
            'data' => $bookings,
            'count' => $bookings->count(),
            'userID' => $userID
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
