<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class CallLogController extends Controller
{
    /**
     * Display a listing of call logs.
     */
    public function index()
    {
        $callLogs = DB::table('call_logs')
            ->orderBy('date_of_call', 'desc')
            ->get();

        return response()->json([
            'success' => true,
            'message' => 'Call logs retrieved successfully',
            'data' => $callLogs,
            'count' => $callLogs->count()
        ]);
    }

    /**
     * Add a new call log entry.
     */
    public function store(Request $request)
    {
        // Validation rules
        $validator = Validator::make($request->all(), [
            'firstName' => 'required|string|max:255',
            'lastName' => 'required|string|max:255',
            'property_link' => 'nullable|url|max:500',
            'user_group' => 'required|string|max:100',
            'feedback' => 'required|string|max:1000',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation error',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            // Prepare data for insertion
            $data = [
                'firstName' => $request->firstName,
                'lastName' => $request->lastName,
                'property_link' => $request->property_link,
                'user_group' => $request->user_group,
                'feedback' => $request->feedback,
                'date_of_call' => now(),
            ];

            // Insert call log
            $callLogId = DB::table('call_logs')->insertGetId($data);

            if ($callLogId) {
                // Retrieve the created record
                $callLog = DB::table('call_logs')->where('id', $callLogId)->first();

                return response()->json([
                    'success' => true,
                    'message' => 'Call log added successfully',
                    'data' => $callLog,
                    'id' => $callLogId
                ], 201);
            } else {
                return response()->json([
                    'success' => false,
                    'message' => 'Failed to add call log'
                ], 500);
            }

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'An error occurred while adding call log',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get call logs by user group.
     */
    public function getByUserGroup($userGroup)
    {
        $callLogs = DB::table('call_logs')
            ->where('user_group', $userGroup)
            ->orderBy('date_of_call', 'desc')
            ->get();

        if ($callLogs->isEmpty()) {
            return response()->json([
                'success' => true,
                'message' => 'No call logs found for this user group',
                'data' => [],
                'count' => 0
            ]);
        }

        return response()->json([
            'success' => true,
            'message' => 'Call logs retrieved successfully',
            'data' => $callLogs,
            'count' => $callLogs->count(),
            'user_group' => $userGroup
        ]);
    }

    /**
     * Get call logs by date range.
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

        $callLogs = DB::table('call_logs')
            ->whereDate('date_of_call', '>=', $request->start_date)
            ->whereDate('date_of_call', '<=', $request->end_date)
            ->orderBy('date_of_call', 'desc')
            ->get();

        return response()->json([
            'success' => true,
            'message' => 'Call logs retrieved successfully',
            'data' => $callLogs,
            'count' => $callLogs->count(),
            'date_range' => [
                'start' => $request->start_date,
                'end' => $request->end_date
            ]
        ]);
    }

    /**
     * Get call logs count.
     */
    public function count()
    {
        $totalCount = DB::table('call_logs')->count();
        $todayCount = DB::table('call_logs')->whereDate('date_of_call', today())->count();
        $thisMonthCount = DB::table('call_logs')->whereMonth('date_of_call', now()->month)->count();

        return response()->json([
            'success' => true,
            'message' => 'Call logs count retrieved successfully',
            'total_count' => $totalCount,
            'today_count' => $todayCount,
            'this_month_count' => $thisMonthCount
        ]);
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
