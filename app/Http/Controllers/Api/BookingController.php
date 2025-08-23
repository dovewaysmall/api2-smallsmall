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
     * Get total bookings count.
     */
    public function count()
    {
        $count = DB::table('bookings')->count();
        
        return response()->json([
            'success' => true,
            'message' => 'Bookings count retrieved successfully',
            'count' => $count
        ]);
    }

    /**
     * Get subscriptions due this month.
     */
    public function getSubscriptionsDueThisMonth()
    {
        $currentMonth = date('m');
        $currentYear = date('Y');
        
        $subscriptionsDue = DB::table('bookings')
            ->join('user_tbl', DB::raw('CAST(bookings.userID AS CHAR)'), '=', DB::raw('CAST(user_tbl.userID AS CHAR)'))
            ->join('property_tbl', DB::raw('CAST(bookings.propertyID AS CHAR)'), '=', DB::raw('CAST(property_tbl.propertyID AS CHAR)'))
            ->select(
                'bookings.*',
                'user_tbl.firstName',
                'user_tbl.lastName', 
                'user_tbl.email',
                'user_tbl.phone',
                'property_tbl.propertyTitle',
                'property_tbl.address',
                'property_tbl.propertyType',
                'property_tbl.price'
            )
            ->whereRaw('MONTH(bookings.next_rental) = ?', [$currentMonth])
            ->whereRaw('YEAR(bookings.next_rental) = ?', [$currentYear])
            ->whereNotNull('bookings.next_rental')
            ->where('bookings.rent_status', '!=', 'Terminated')
            ->orderBy('bookings.next_rental', 'desc')
            ->get();

        if ($subscriptionsDue->isEmpty()) {
            return response()->json([
                'success' => true,
                'message' => 'No subscriptions due this month',
                'data' => [],
                'count' => 0,
                'month' => date('F Y'),
                'total_amount_due' => 0
            ]);
        }

        // Calculate total amount due
        $totalAmountDue = $subscriptionsDue->sum('price');

        return response()->json([
            'success' => true,
            'message' => 'Subscriptions due this month retrieved successfully',
            'data' => $subscriptionsDue,
            'count' => $subscriptionsDue->count(),
            'month' => date('F Y'),
            'total_amount_due' => $totalAmountDue
        ]);
    }

    /**
     * Get subscriptions due in the next 2 weeks.
     */
    public function getSubscriptionsDueInTwoWeeks()
    {
        $today = date('Y-m-d');
        $twoWeeksFromNow = date('Y-m-d', strtotime('+2 weeks'));
        
        $subscriptionsDue = DB::table('bookings')
            ->join('user_tbl', DB::raw('CAST(bookings.userID AS CHAR)'), '=', DB::raw('CAST(user_tbl.userID AS CHAR)'))
            ->join('property_tbl', DB::raw('CAST(bookings.propertyID AS CHAR)'), '=', DB::raw('CAST(property_tbl.propertyID AS CHAR)'))
            ->select(
                'bookings.*',
                'user_tbl.firstName',
                'user_tbl.lastName', 
                'user_tbl.email',
                'user_tbl.phone',
                'property_tbl.propertyTitle',
                'property_tbl.address',
                'property_tbl.propertyType',
                'property_tbl.price'
            )
            ->whereDate('bookings.next_rental', '>=', $today)
            ->whereDate('bookings.next_rental', '<=', $twoWeeksFromNow)
            ->whereNotNull('bookings.next_rental')
            ->where('bookings.rent_status', '!=', 'Terminated')
            ->orderBy('bookings.next_rental', 'desc')
            ->get();

        if ($subscriptionsDue->isEmpty()) {
            return response()->json([
                'success' => true,
                'message' => 'No subscriptions due in the next 2 weeks',
                'data' => [],
                'count' => 0,
                'period' => 'Next 2 weeks (' . date('M d') . ' - ' . date('M d', strtotime('+2 weeks')) . ')',
                'total_amount_due' => 0
            ]);
        }

        // Calculate total amount due and group by urgency
        $totalAmountDue = $subscriptionsDue->sum('rent_amount');
        
        // Group subscriptions by urgency
        $urgentCount = 0; // Due in next 3 days
        $warningCount = 0; // Due in 4-7 days
        $normalCount = 0; // Due in 8-14 days
        
        foreach ($subscriptionsDue as $subscription) {
            $daysUntilDue = $this->getDaysUntilDue($subscription->next_rental);
            if ($daysUntilDue <= 3) {
                $urgentCount++;
            } elseif ($daysUntilDue <= 7) {
                $warningCount++;
            } else {
                $normalCount++;
            }
        }

        return response()->json([
            'success' => true,
            'message' => 'Subscriptions due in the next 2 weeks retrieved successfully',
            'data' => $subscriptionsDue,
            'count' => $subscriptionsDue->count(),
            'period' => 'Next 2 weeks (' . date('M d') . ' - ' . date('M d', strtotime('+2 weeks')) . ')',
            'total_amount_due' => $totalAmountDue,
            'urgency_breakdown' => [
                'urgent' => $urgentCount,      // 0-3 days
                'warning' => $warningCount,    // 4-7 days
                'normal' => $normalCount       // 8-14 days
            ]
        ]);
    }

    /**
     * Get specific subscription due by booking ID.
     */
    public function getSubscriptionDueById($id)
    {
        $currentMonth = date('m');
        $currentYear = date('Y');
        
        $subscription = DB::table('bookings')
            ->join('user_tbl', DB::raw('CAST(bookings.userID AS CHAR)'), '=', DB::raw('CAST(user_tbl.userID AS CHAR)'))
            ->join('property_tbl', DB::raw('CAST(bookings.propertyID AS CHAR)'), '=', DB::raw('CAST(property_tbl.propertyID AS CHAR)'))
            ->select(
                'bookings.*',
                'user_tbl.firstName',
                'user_tbl.lastName', 
                'user_tbl.email',
                'user_tbl.phone',
                'property_tbl.propertyTitle',
                'property_tbl.address',
                'property_tbl.propertyType',
                'property_tbl.price'
            )
            ->where('bookings.id', $id)
            ->first();

        if (!$subscription) {
            return response()->json([
                'success' => false,
                'message' => 'Subscription not found'
            ], 404);
        }

        // Check if this subscription is due this month
        $nextRentalMonth = date('m', strtotime($subscription->next_rental));
        $nextRentalYear = date('Y', strtotime($subscription->next_rental));
        $isDueThisMonth = ($nextRentalMonth == $currentMonth && $nextRentalYear == $currentYear);
        
        $daysUntilDue = $this->getDaysUntilDue($subscription->next_rental);
        $urgencyLevel = $this->getUrgencyLevel($daysUntilDue);

        return response()->json([
            'success' => true,
            'message' => 'Subscription retrieved successfully',
            'data' => $subscription,
            'is_due_this_month' => $isDueThisMonth,
            'days_until_due' => $daysUntilDue,
            'urgency_level' => $urgencyLevel
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
     * Calculate days until due date.
     */
    private function getDaysUntilDue($nextRentalDate)
    {
        if (!$nextRentalDate) return null;
        
        $today = new \DateTime();
        $dueDate = new \DateTime($nextRentalDate);
        $interval = $today->diff($dueDate);
        
        return $interval->invert ? -$interval->days : $interval->days;
    }

    /**
     * Get urgency level based on days until due.
     */
    private function getUrgencyLevel($daysUntilDue)
    {
        if ($daysUntilDue === null) return 'unknown';
        if ($daysUntilDue < 0) return 'overdue';
        if ($daysUntilDue <= 3) return 'urgent';
        if ($daysUntilDue <= 7) return 'warning';
        if ($daysUntilDue <= 14) return 'normal';
        return 'future';
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
