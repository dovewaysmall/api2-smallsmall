<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class FeedbackController extends Controller
{
    /**
     * Display a listing of all feedback.
     */
    public function index()
    {
        $feedback = DB::table('feedback')
            ->orderBy('feedback_date', 'desc')
            ->get();

        return response()->json([
            'success' => true,
            'message' => 'Feedback retrieved successfully',
            'data' => $feedback,
            'count' => $feedback->count()
        ]);
    }

    /**
     * Store a newly created feedback (Improved version of your original).
     */
    public function store(Request $request)
    {
        // Enhanced validation rules
        $validator = Validator::make($request->all(), [
            'satisfaction' => 'required|integer|min:1|max:5',
            'rate' => 'required|integer|min:1|max:5', 
            'comment' => 'required|string|max:1000',
            'followUp' => 'nullable|string|max:500',
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
                'satisfaction' => $request->satisfaction,
                'rate' => $request->rate,
                'comment' => $request->comment,
                'followUp' => $request->followUp,
                'feedback_date' => now(),
            ];

            // Insert feedback and get ID
            $feedbackId = DB::table('feedback')->insertGetId($data);

            if ($feedbackId) {
                // Retrieve the created feedback
                $createdFeedback = DB::table('feedback')->where('id', $feedbackId)->first();

                return response()->json([
                    'success' => true,
                    'message' => 'Feedback saved successfully',
                    'data' => $createdFeedback,
                    'id' => $feedbackId
                ], 201);
            } else {
                return response()->json([
                    'success' => false,
                    'message' => 'Failed to save feedback'
                ], 500);
            }

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'An error occurred while saving feedback',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Display the specified feedback.
     */
    public function show(string $id)
    {
        $feedback = DB::table('feedback')->where('id', $id)->first();

        if (!$feedback) {
            return response()->json([
                'success' => false,
                'message' => 'Feedback not found'
            ], 404);
        }

        return response()->json([
            'success' => true,
            'message' => 'Feedback retrieved successfully',
            'data' => $feedback
        ]);
    }

    /**
     * Update the specified feedback.
     */
    public function update(Request $request, string $id)
    {
        $feedback = DB::table('feedback')->where('id', $id)->first();

        if (!$feedback) {
            return response()->json([
                'success' => false,
                'message' => 'Feedback not found'
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'satisfaction' => 'sometimes|integer|min:1|max:5',
            'rate' => 'sometimes|integer|min:1|max:5',
            'comment' => 'sometimes|string|max:1000',
            'followUp' => 'nullable|string|max:500',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation error',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $updateData = $request->only(['satisfaction', 'rate', 'comment', 'followUp']);
            
            DB::table('feedback')->where('id', $id)->update($updateData);
            $updatedFeedback = DB::table('feedback')->where('id', $id)->first();

            return response()->json([
                'success' => true,
                'message' => 'Feedback updated successfully',
                'data' => $updatedFeedback
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'An error occurred while updating feedback',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Remove the specified feedback.
     */
    public function destroy(string $id)
    {
        $feedback = DB::table('feedback')->where('id', $id)->first();

        if (!$feedback) {
            return response()->json([
                'success' => false,
                'message' => 'Feedback not found'
            ], 404);
        }

        try {
            DB::table('feedback')->where('id', $id)->delete();

            return response()->json([
                'success' => true,
                'message' => 'Feedback deleted successfully'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'An error occurred while deleting feedback',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get feedback statistics and analytics.
     */
    public function getStats()
    {
        $totalFeedback = DB::table('feedback')->count();
        $averageRating = DB::table('feedback')->avg('rate');
        $averageSatisfaction = DB::table('feedback')->avg('satisfaction');
        
        // Rating distribution
        $ratingDistribution = DB::table('feedback')
            ->select('rate', DB::raw('count(*) as count'))
            ->groupBy('rate')
            ->orderBy('rate')
            ->get();

        // Satisfaction distribution 
        $satisfactionDistribution = DB::table('feedback')
            ->select('satisfaction', DB::raw('count(*) as count'))
            ->groupBy('satisfaction')
            ->orderBy('satisfaction')
            ->get();

        // Recent feedback (last 30 days)
        $recentFeedback = DB::table('feedback')
            ->where('feedback_date', '>=', now()->subDays(30))
            ->count();

        return response()->json([
            'success' => true,
            'message' => 'Feedback statistics retrieved successfully',
            'total_feedback' => $totalFeedback,
            'average_rating' => round($averageRating, 2),
            'average_satisfaction' => round($averageSatisfaction, 2),
            'recent_feedback_30_days' => $recentFeedback,
            'rating_distribution' => $ratingDistribution,
            'satisfaction_distribution' => $satisfactionDistribution
        ]);
    }

    /**
     * Get feedback by rating range.
     */
    public function getByRating($rating)
    {
        $validator = Validator::make(['rating' => $rating], [
            'rating' => 'required|integer|min:1|max:5'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid rating. Must be between 1 and 5',
                'errors' => $validator->errors()
            ], 422);
        }

        $feedback = DB::table('feedback')
            ->where('rate', $rating)
            ->orderBy('feedback_date', 'desc')
            ->get();

        return response()->json([
            'success' => true,
            'message' => 'Feedback retrieved successfully',
            'data' => $feedback,
            'count' => $feedback->count(),
            'rating' => $rating
        ]);
    }

    /**
     * Get feedback by date range.
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

        $feedback = DB::table('feedback')
            ->whereDate('feedback_date', '>=', $request->start_date)
            ->whereDate('feedback_date', '<=', $request->end_date)
            ->orderBy('feedback_date', 'desc')
            ->get();

        return response()->json([
            'success' => true,
            'message' => 'Feedback retrieved successfully',
            'data' => $feedback,
            'count' => $feedback->count(),
            'date_range' => [
                'start' => $request->start_date,
                'end' => $request->end_date
            ]
        ]);
    }
}
