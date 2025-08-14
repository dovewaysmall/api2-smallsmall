<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Mail;

class VerificationController extends Controller
{
    /**
     * Display a listing of all verifications (Improved version of your original).
     */
    public function index()
    {
        try {
            $verifications = DB::table('verifications')
                ->join('user_tbl', 'verifications.user_id', '=', 'user_tbl.userID')
                ->select(
                    'user_tbl.firstName',
                    'user_tbl.lastName', 
                    'user_tbl.userID',
                    'user_tbl.email',
                    'user_tbl.phone',
                    'user_tbl.verified',
                    'verifications.*'
                )
                ->orderBy('verifications.id', 'desc')
                ->get();

            return response()->json([
                'success' => true,
                'message' => 'Verifications retrieved successfully',
                'data' => $verifications,
                'count' => $verifications->count()
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'An error occurred while retrieving verifications',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Display the specified verification (Improved version of your original).
     */
    public function show(string $id)
    {
        try {
            $verification = DB::table('verifications')
                ->join('user_tbl', 'verifications.user_id', '=', 'user_tbl.userID')
                ->select(
                    'user_tbl.*',
                    'verifications.*'
                )
                ->where('verifications.id', $id)
                ->first();

            if (!$verification) {
                return response()->json([
                    'success' => false,
                    'message' => 'Verification not found'
                ], 404);
            }

            return response()->json([
                'success' => true,
                'message' => 'Verification retrieved successfully',
                'data' => $verification
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'An error occurred while retrieving verification',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Store a newly created verification.
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'user_id' => 'required|string|exists:user_tbl,userID',
            'employment_status' => 'required|in:employed,self_employed,student,unemployed,retired',
            'gross_annual_income' => 'required|numeric|min:0',
            'marital_status' => 'required|in:single,married,divorced,widowed',
            'present_address' => 'required|string|max:500',
            'duration_present_address' => 'required|string|max:100',
            'dob' => 'required|date|before:today',
            'occupation' => 'required|string|max:255',
            'company_name' => 'nullable|string|max:255',
            'company_address' => 'nullable|string|max:500',
            'hr_manager_name' => 'nullable|string|max:255',
            'hr_manager_email' => 'nullable|email|max:255',
            'office_phone' => 'nullable|string|max:20',
            'current_renting_status' => 'required|in:owner,tenant,living_with_family,other',
            'reason_for_living' => 'nullable|string|max:1000',
            'disability' => 'required|in:yes,no',
            'pets' => 'required|in:yes,no',
            'present_landlord' => 'nullable|string|max:255',
            'landlord_email' => 'nullable|email|max:255',
            'landlord_phone' => 'nullable|string|max:20',
            'landlord_address' => 'nullable|string|max:500',
            'guarantor_name' => 'required|string|max:255',
            'guarantor_email' => 'required|email|max:255',
            'guarantor_phone' => 'required|string|max:20',
            'guarantor_address' => 'required|string|max:500',
            'guarantor_occupation' => 'required|string|max:255',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation error',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            // Generate unique verification ID
            $verificationId = 'VER' . date('Ymd') . mt_rand(1000, 9999);

            $data = array_merge($request->all(), [
                'verification_id' => $verificationId,
                'verification_status' => 'received',
                'created_at' => now(),
            ]);

            $insertedId = DB::table('verifications')->insertGetId($data);

            if ($insertedId) {
                // Update user verification status to 'received'
                DB::table('user_tbl')->where('userID', $request->user_id)->update([
                    'verified' => 'received',
                    'updated_at' => now()
                ]);

                // Send email notification
                $this->sendVerificationEmail($request->user_id, 'received');

                $createdVerification = DB::table('verifications')
                    ->join('user_tbl', 'verifications.user_id', '=', 'user_tbl.userID')
                    ->select('verifications.*', 'user_tbl.firstName', 'user_tbl.lastName', 'user_tbl.email')
                    ->where('verifications.id', $insertedId)
                    ->first();

                return response()->json([
                    'success' => true,
                    'message' => 'Verification submitted successfully',
                    'data' => $createdVerification,
                    'id' => $insertedId
                ], 201);
            } else {
                return response()->json([
                    'success' => false,
                    'message' => 'Failed to submit verification'
                ], 500);
            }

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'An error occurred while submitting verification',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get verification count (Improved version of your original).
     */
    public function count()
    {
        try {
            $totalCount = DB::table('verifications')->count();
            $todayCount = DB::table('verifications')->whereDate('created_at', today())->count();
            $thisWeekCount = DB::table('verifications')->whereDate('created_at', '>=', now()->startOfWeek())->count();
            $thisMonthCount = DB::table('verifications')->whereDate('created_at', '>=', now()->startOfMonth())->count();

            return response()->json([
                'success' => true,
                'message' => 'Verification count retrieved successfully',
                'total_verifications' => $totalCount,
                'today_verifications' => $todayCount,
                'this_week_verifications' => $thisWeekCount,
                'this_month_verifications' => $thisMonthCount
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'An error occurred while retrieving verification count',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update verification status (Improved version of your original with email notifications).
     */
    public function updateStatus(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'userID' => 'required|string|exists:user_tbl,userID',
            'verified' => 'required|in:yes,no,processing,received',
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
                'verified' => $request->verified
            ];

            // Update user verification status
            $updated = DB::table('user_tbl')->where('userID', $request->userID)->update($data);

            if ($updated) {
                // Send email notification based on status
                $this->sendVerificationEmail($request->userID, $request->verified);

                // Get updated user info
                $updatedUser = DB::table('user_tbl')
                    ->select(
                        'user_tbl.userID',
                        'user_tbl.firstName',
                        'user_tbl.lastName',
                        'user_tbl.email',
                        'user_tbl.verified'
                    )
                    ->where('user_tbl.userID', $request->userID)
                    ->first();

                return response()->json([
                    'success' => true,
                    'message' => 'Verification status updated successfully',
                    'data' => $updatedUser
                ]);
            } else {
                return response()->json([
                    'success' => false,
                    'message' => 'Failed to update verification status'
                ], 500);
            }

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'An error occurred while updating verification status',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get verifications by status.
     */
    public function getByStatus($status)
    {
        $validator = Validator::make(['status' => $status], [
            'status' => 'required|in:received,processing,approved,rejected,pending'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid status. Must be: received, processing, approved, rejected, or pending',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $verifications = DB::table('verifications')
                ->join('user_tbl', 'verifications.user_id', '=', 'user_tbl.userID')
                ->select(
                    'user_tbl.firstName',
                    'user_tbl.lastName',
                    'user_tbl.email',
                    'user_tbl.phone',
                    'user_tbl.verified',
                    'verifications.*'
                )
                ->orderBy('verifications.created_at', 'desc')
                ->get();

            return response()->json([
                'success' => true,
                'message' => 'Verifications retrieved successfully',
                'data' => $verifications,
                'count' => $verifications->count(),
                'status' => $status
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'An error occurred while retrieving verifications',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get verification statistics.
     */
    public function getStats()
    {
        try {
            $totalVerifications = DB::table('verifications')->count();

            $employmentStats = DB::table('verifications')
                ->select('employment_status', DB::raw('count(*) as count'))
                ->groupBy('employment_status')
                ->get();

            $monthlyStats = DB::table('verifications')
                ->select(
                    DB::raw('YEAR(created_at) as year'),
                    DB::raw('MONTH(created_at) as month'),
                    DB::raw('count(*) as count')
                )
                ->where('created_at', '>=', now()->subMonths(12))
                ->groupBy('year', 'month')
                ->orderBy('year', 'desc')
                ->orderBy('month', 'desc')
                ->get();

            $averageIncome = DB::table('verifications')
                ->avg('gross_annual_income');

            $incomeRange = DB::table('verifications')
                ->selectRaw('MIN(gross_annual_income) as min_income, MAX(gross_annual_income) as max_income')
                ->first();

            return response()->json([
                'success' => true,
                'message' => 'Verification statistics retrieved successfully',
                'total_verifications' => $totalVerifications,
                'average_income' => round($averageIncome ?? 0, 2),
                'income_range' => $incomeRange,
                'employment_breakdown' => $employmentStats,
                'monthly_trends' => $monthlyStats
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'An error occurred while retrieving verification statistics',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Search verifications.
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
            $verifications = DB::table('verifications')
                ->join('user_tbl', 'verifications.user_id', '=', 'user_tbl.userID')
                ->select('verifications.*', 'user_tbl.firstName', 'user_tbl.lastName', 'user_tbl.email')
                ->where(function($q) use ($query) {
                    $q->where('user_tbl.firstName', 'LIKE', "%{$query}%")
                      ->orWhere('user_tbl.lastName', 'LIKE', "%{$query}%")
                      ->orWhere('user_tbl.email', 'LIKE', "%{$query}%")
                      ->orWhere('verifications.verification_id', 'LIKE', "%{$query}%")
                      ->orWhere('verifications.company_name', 'LIKE', "%{$query}%");
                })
                ->orderBy('verifications.created_at', 'desc')
                ->get();

            return response()->json([
                'success' => true,
                'message' => 'Verification search results',
                'data' => $verifications,
                'count' => $verifications->count(),
                'search_query' => $query
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'An error occurred while searching verifications',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Send verification email notification (Based on your original email system).
     */
    private function sendVerificationEmail($userID, $status)
    {
        try {
            $user = DB::table('user_tbl')->where('userID', $userID)->first();
            
            if (!$user) {
                return false;
            }

            $to = $user->email;
            $firstName = $user->firstName;
            $lastName = $user->lastName;

            // Email content based on status
            switch ($status) {
                case 'yes':
                    $subject = "Verification Successful";
                    $statusText = "Verification Successful";
                    $bodyMessage = "Thank you for showing interest in subscribing one of our properties. This is to inform you that your verification has been completed and you are eligible to subscribing this property. You can now proceed to pay for your already booked apartment/Furniture. Proceed to your dashboard to continue.";
                    $additionalInfo = "Please note: If payment is not made within 12 hours, the property will be available for the next person in the queue. Also, if payment is made after the stipulated time, the process of initiating a refund takes 7 days or a sum of two thousand naira (N2000) will be charged for an immediate refund. If you choose to cancel your booking, a 5% deduction would be applied.";
                    break;
                    
                case 'processing':
                    $subject = "Verification in process";
                    $statusText = "Verification in process";
                    $bodyMessage = "This is to notify you that verification processing has begun, and you will be notified as soon as a decision has been made.";
                    $additionalInfo = "Please note your information is highly confidential.";
                    break;
                    
                case 'received':
                    $subject = "Verification Received";
                    $statusText = "Verification Received";
                    $bodyMessage = "Your verification details have been received, you will get another email in 48 hours notifying you of actions been taken towards verifying your details.";
                    $additionalInfo = "Please note your information is highly confidential.";
                    break;
                    
                case 'no':
                    $subject = "Verification Failed";
                    $statusText = "Verification Failed";
                    $bodyMessage = "Thank you for showing interest in renting with us. We are sorry to inform that you did not pass our verification process and are therefore not eligible to rent with us at the moment, therefore you can't make payment.";
                    $additionalInfo = "";
                    break;
                    
                default:
                    return false;
            }

            $message = $this->generateEmailTemplate($firstName, $statusText, $bodyMessage, $additionalInfo);

            // Email headers
            $headers = "MIME-Version: 1.0" . "\r\n";
            $headers .= "Content-type:text/html;charset=UTF-8" . "\r\n";
            $headers .= 'From: <noreply@smallsmall.com>' . "\r\n";
            $headers .= 'Cc: customerexperience@smallsmall.com' . "\r\n";

            // Send email
            return mail($to, $subject, $message, $headers);

        } catch (\Exception $e) {
            // Log error but don't fail the main operation
            error_log('Email sending failed: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Get verifications created this week.
     */
    public function getThisWeek()
    {
        try {
            $startOfWeek = now()->startOfWeek();
            $endOfWeek = now()->endOfWeek();

            $verifications = DB::table('verifications')
                ->join('user_tbl', 'verifications.user_id', '=', 'user_tbl.userID')
                ->select(
                    'user_tbl.firstName',
                    'user_tbl.lastName', 
                    'user_tbl.email',
                    'user_tbl.phone',
                    'user_tbl.verified',
                    'verifications.*'
                )
                ->whereDate('verifications.created_at', '>=', $startOfWeek)
                ->whereDate('verifications.created_at', '<=', $endOfWeek)
                ->orderBy('verifications.created_at', 'desc')
                ->get();

            $weekStats = [
                'total_verifications' => $verifications->count(),
                'average_income' => $verifications->count() > 0 ? round($verifications->avg('gross_annual_income'), 2) : 0,
                'employment_breakdown' => $verifications->groupBy('employment_status')->map->count()
            ];

            return response()->json([
                'success' => true,
                'message' => 'Verifications for this week retrieved successfully',
                'data' => $verifications,
                'count' => $verifications->count(),
                'period' => [
                    'start' => $startOfWeek->format('Y-m-d H:i:s'),
                    'end' => $endOfWeek->format('Y-m-d H:i:s')
                ],
                'week_statistics' => $weekStats
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'An error occurred while retrieving verifications for this week',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get verifications created this month.
     */
    public function getThisMonth()
    {
        try {
            $startOfMonth = now()->startOfMonth();
            $endOfMonth = now()->endOfMonth();

            $verifications = DB::table('verifications')
                ->join('user_tbl', 'verifications.user_id', '=', 'user_tbl.userID')
                ->select(
                    'user_tbl.firstName',
                    'user_tbl.lastName', 
                    'user_tbl.email',
                    'user_tbl.phone',
                    'user_tbl.verified',
                    'verifications.*'
                )
                ->whereDate('verifications.created_at', '>=', $startOfMonth)
                ->whereDate('verifications.created_at', '<=', $endOfMonth)
                ->orderBy('verifications.created_at', 'desc')
                ->get();

            $monthStats = [
                'total_verifications' => $verifications->count(),
                'average_income' => $verifications->count() > 0 ? round($verifications->avg('gross_annual_income'), 2) : 0,
                'employment_breakdown' => $verifications->groupBy('employment_status')->map->count(),
                'marital_status_breakdown' => $verifications->groupBy('marital_status')->map->count()
            ];

            return response()->json([
                'success' => true,
                'message' => 'Verifications for this month retrieved successfully',
                'data' => $verifications,
                'count' => $verifications->count(),
                'period' => [
                    'start' => $startOfMonth->format('Y-m-d H:i:s'),
                    'end' => $endOfMonth->format('Y-m-d H:i:s')
                ],
                'month_statistics' => $monthStats
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'An error occurred while retrieving verifications for this month',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get verifications created this year.
     */
    public function getThisYear()
    {
        try {
            $startOfYear = now()->startOfYear();
            $endOfYear = now()->endOfYear();

            $verifications = DB::table('verifications')
                ->join('user_tbl', 'verifications.user_id', '=', 'user_tbl.userID')
                ->select(
                    'user_tbl.firstName',
                    'user_tbl.lastName', 
                    'user_tbl.email',
                    'user_tbl.phone',
                    'user_tbl.verified',
                    'verifications.*'
                )
                ->whereDate('verifications.created_at', '>=', $startOfYear)
                ->whereDate('verifications.created_at', '<=', $endOfYear)
                ->orderBy('verifications.created_at', 'desc')
                ->get();

            $yearStats = [
                'total_verifications' => $verifications->count(),
                'average_income' => $verifications->count() > 0 ? round($verifications->avg('gross_annual_income'), 2) : 0,
                'highest_income' => $verifications->max('gross_annual_income') ?? 0,
                'lowest_income' => $verifications->min('gross_annual_income') ?? 0,
                'employment_breakdown' => $verifications->groupBy('employment_status')->map->count(),
                'marital_status_breakdown' => $verifications->groupBy('marital_status')->map->count(),
                'monthly_breakdown' => $verifications->groupBy(function($item) {
                    return date('Y-m', strtotime($item->created_at));
                })->map->count(),
                'company_diversity' => $verifications->where('company_name', '!=', '')->groupBy('company_name')->map->count()
            ];

            return response()->json([
                'success' => true,
                'message' => 'Verifications for this year retrieved successfully',
                'data' => $verifications,
                'count' => $verifications->count(),
                'period' => [
                    'start' => $startOfYear->format('Y-m-d H:i:s'),
                    'end' => $endOfYear->format('Y-m-d H:i:s')
                ],
                'year_statistics' => $yearStats
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'An error occurred while retrieving verifications for this year',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Generate email template (Based on your original template).
     */
    private function generateEmailTemplate($firstName, $statusText, $bodyMessage, $additionalInfo = '')
    {
        $paymentInfo = $additionalInfo ? "<div style='width:100%;min-height:30px;overflow:auto;text-align:center;font-family:calibri;font-size:16px;margin-bottom:20px;' class='email-body'>$additionalInfo</div>" : "";
        
        $subscriptionInfo = ($statusText === "Verification Successful") ? 
            "<div style='width:100%;min-height:30px;overflow:auto;text-align:center;font-family:calibri;font-size:16px;margin-bottom:20px;' class='email-body'>Subscription payment is easy on our platform, we use Paystack to collect payments on a modern secure payment gateway, this gateway offers users different modes of payment. Smallsmall does not store bank card or personal account data. If you encounter any problem using the payment gateway please contact Smallsmall Customer experience at customerexperience@smallsmall.com or Call 070-877 89 815/ 0903-722-2669/ 0903-633-9800 for assistance. Thanks</div>" : "";

        return "
        <!doctype html>
        <html>
        <head>
        <meta charset='utf-8'>
        <meta name='viewport' content='width=device-width'>
        <title></title>
        </head>

        <body style='width:100%;padding:0;margin:0;box-sizing:border-box;'>
            <div class='container' style='width:95%;min-height:100px;overflow:auto;margin:auto;box-sizing:border-box;'>
                <table width='100%'>
                    <tr>
                        <td width='33.3%'>&nbsp;</td>
                        <td style='text-align:center' class='logo-container' width='33.3%'><img width='130px' src='https://www.rentsmallsmall.com/assets/img/logo-rss.png' /></td>
                        <td width='33.3%'>&nbsp;</td>
                    </tr>
                </table>
                
                <table width='100%' style='margin-top:30px'>
                    <tr>
                        <td width='100%'>
                            <div class='message-container' style='width:100%;border-radius:10px;text-align:center;background:#F2FCFB;padding:40px;'>
                                <div style='width:100%;min-height:10px;overflow:auto;text-align:center;font-family:calibri;font-size:30px;margin-bottom:20px;' class='name'>Hello, $firstName</div>
                                <div style='width:100%;min-height:10px;overflow:auto;text-align:center;font-family:calibri;font-size:20px;margin-bottom:20px;' class='intro'>$statusText</div>
                                <div style='width:100%;min-height:30px;overflow:auto;text-align:center;font-family:calibri;font-size:16px;margin-bottom:20px;' class='email-body'>$bodyMessage</div>
                                $paymentInfo
                                $subscriptionInfo
                            </div>
                        </td>
                    </tr>
                </table> 

                <div class='footer' style='width:100%;min-height:100px;overflow:auto;margin-top:40px;padding-top:40px;border-top:1px solid #00CDA6;padding:20px;'>
                    <div style='width:100%;min-height:10px;overflow:auto;margin-bottom:20px;font-family:avenir-regular;font-size:14px;text-align:center;' class='stay-connected-txt'>Stay connected to us</div>
                    <div style='width:100%;min-height:10px;overflow:auto;margin-bottom:30px;text-align:center;' class='social-spc'>
                        <ul class='social-container' style='display:inline-block;min-width:100px;min-height:10px;overflow:auto;margin:auto;list-style:none;padding:0;'>
                            <li style='width:70px;min-height:10px;overflow:auto;float:left;text-align:center;' class='social-item'><a href='https://www.twitter.com/rentsmallsmall'><img width='50px' height='auto' src='https://www.rentsmallsmall.com/assets/img/twitter.png' /></a></li>
                            <li style='width:70px;min-height:10px;overflow:auto;float:left;text-align:center;' class='social-item'><a href='https://www.facebook.com/rentsmallsmall'><img width='50px' height='auto' src='https://www.rentsmallsmall.com/assets/img/facebook.png' /></a></li>
                            <li style='width:70px;min-height:10px;overflow:auto;float:left;text-align:center;' class='social-item'><a href='https://www.instagram.com/rentsmallsmall'><img width='50px' height='auto' src='https://www.rentsmallsmall.com/assets/img/instagram.png' /></a></li>
                            <li style='width:70px;min-height:10px;overflow:auto;float:left;text-align:center;' class='social-item'><a href='https://www.linkedin.com/company/rentsmallsmall'><img width='50px' height='auto' src='https://www.rentsmallsmall.com/assets/img/linkedin.png' /></a></li>
                        </ul>
                    </div>
                    <div style='width:100%;min-height:30px;overflow:auto;text-align:center;line-height:30px;font-size:14px;font-family:avenir-regular;color:#00CDA6;' class='disclaimer'>
                        For help contact Customer experience<br />
                        at 090 722 2669, 0903 633 9800<br /> 
                        or email to customerexperience@smallsmall.com
                    </div>
                </div>
            </div>
        </body>
        </html>";
    }
}