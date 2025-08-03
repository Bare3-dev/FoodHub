<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\Order;
use App\Models\User;
use App\Services\SecurityLoggingService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class CustomerServiceController extends Controller
{
    /**
     * Store a customer complaint.
     */
    public function storeComplaint(Request $request): Response
    {
        $validated = $request->validate([
            'order_id' => ['required', 'exists:orders,id'],
            'customer_id' => ['required', 'exists:customers,id'],
            'complaint_type' => ['required', 'string', 'in:food_safety,delivery_issue,order_error,staff_behavior,food_quality'],
            'severity' => ['required', 'string', 'in:low,medium,high,critical'],
            'description' => ['required', 'string', 'max:1000'],
            'requested_resolution' => ['required', 'string', 'in:refund,replacement,apology,investigation,immediate_refund'],
            'contact_preference' => ['required', 'string', 'in:email,phone,sms'],
        ]);

        // Create complaint record
        $complaint = DB::table('customer_complaints')->insertGetId([
            'order_id' => $validated['order_id'],
            'customer_id' => $validated['customer_id'],
            'user_id' => Auth::id(),
            'complaint_type' => $validated['complaint_type'],
            'severity' => $validated['severity'],
            'description' => $validated['description'],
            'requested_resolution' => $validated['requested_resolution'],
            'contact_preference' => $validated['contact_preference'],
            'status' => $validated['severity'] === 'critical' ? 'escalated' : 'pending',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // If critical, create escalation notification to branch manager
        if ($validated['severity'] === 'critical') {
            // Get the branch manager for this order's restaurant branch
            $order = Order::find($validated['order_id']);
            $branchManager = User::where('restaurant_branch_id', $order->restaurant_branch_id)
                ->where('role', 'BRANCH_MANAGER')
                ->first();
            
            if ($branchManager) {
                DB::table('notifications')->insert([
                    'id' => \Illuminate\Support\Str::uuid(),
                    'type' => 'App\Notifications\ComplaintEscalationNotification',
                    'notifiable_type' => 'App\Models\User',
                    'notifiable_id' => $branchManager->id,
                    'data' => json_encode(['complaint_id' => $complaint]),
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }

        return response(['data' => [
            'id' => $complaint,
            'order_id' => $validated['order_id'],
            'customer_id' => $validated['customer_id'],
            'complaint_type' => $validated['complaint_type'],
            'severity' => $validated['severity'],
            'description' => $validated['description'],
            'requested_resolution' => $validated['requested_resolution'],
            'contact_preference' => $validated['contact_preference'],
            'status' => $validated['severity'] === 'critical' ? 'escalated' : 'pending',
            'assigned_to' => Auth::id(),
            'created_at' => now(),
            'updated_at' => now()
        ]], 201);
    }

    /**
     * Store a customer service interaction.
     */
    public function storeInteraction(Request $request): Response
    {
        $validated = $request->validate([
            'customer_id' => ['required', 'exists:customers,id'],
            'interaction_type' => ['required', 'string', 'in:phone_call,email,chat,in_person'],
            'duration_minutes' => ['required', 'integer', 'min:1', 'max:480'],
            'topic' => ['required', 'string', 'max:255'],
            'resolution' => ['required', 'string', 'max:255'],
            'satisfaction_rating' => ['nullable', 'integer', 'min:1', 'max:5'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);

        $interaction = DB::table('customer_service_interactions')->insertGetId([
            'customer_id' => $validated['customer_id'],
            'user_id' => Auth::id(),
            'interaction_type' => $validated['interaction_type'],
            'duration_minutes' => $validated['duration_minutes'],
            'topic' => $validated['topic'],
            'resolution' => $validated['resolution'],
            'satisfaction_rating' => $validated['satisfaction_rating'],
            'notes' => $validated['notes'],
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return response([
            'data' => [
                'id' => $interaction,
                'customer_id' => $validated['customer_id'],
                'interaction_type' => $validated['interaction_type'],
                'duration_minutes' => $validated['duration_minutes'],
                'topic' => $validated['topic'],
                'resolution' => $validated['resolution'],
                'satisfaction_rating' => $validated['satisfaction_rating'],
                'created_at' => now()
            ]
        ], 201);
    }

    /**
     * Store a refund request.
     */
    public function storeRefund(Request $request): Response
    {
        $validated = $request->validate([
            'order_id' => ['required', 'exists:orders,id'],
            'customer_id' => ['required', 'exists:customers,id'],
            'refund_amount' => ['required', 'numeric', 'min:0.01'],
            'refund_reason' => ['required', 'string', 'max:500'],
            'refund_type' => ['required', 'string', 'in:full,partial'],
            'approval_status' => ['nullable', 'string', 'in:pending,approved,rejected'],
        ]);

        $refund = DB::table('customer_refunds')->insertGetId([
            'order_id' => $validated['order_id'],
            'customer_id' => $validated['customer_id'],
            'user_id' => Auth::id(),
            'refund_amount' => $validated['refund_amount'],
            'refund_reason' => $validated['refund_reason'],
            'refund_type' => $validated['refund_type'],
            'approval_status' => $validated['approval_status'] ?? 'pending',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return response([
            'data' => [
                'id' => $refund,
                'order_id' => $validated['order_id'],
                'refund_amount' => $validated['refund_amount'],
                'refund_reason' => $validated['refund_reason'],
                'refund_type' => $validated['refund_type'],
                'approval_status' => $validated['approval_status'] ?? 'pending',
                'created_at' => now()
            ]
        ], 201);
    }

    /**
     * Store a compensation request.
     */
    public function storeCompensation(Request $request): Response
    {
        $validated = $request->validate([
            'order_id' => ['required', 'exists:orders,id'],
            'customer_id' => ['required', 'exists:customers,id'],
            'compensation_type' => ['required', 'string', 'in:discount,free_item,credit,apology'],
            'compensation_value' => ['required', 'string', 'max:255'],
            'reason' => ['required', 'string', 'max:500'],
            'approval_status' => ['nullable', 'string', 'in:pending,approved,rejected'],
        ]);

        $compensation = DB::table('customer_compensations')->insertGetId([
            'order_id' => $validated['order_id'],
            'customer_id' => $validated['customer_id'],
            'user_id' => Auth::id(),
            'compensation_type' => $validated['compensation_type'],
            'compensation_value' => $validated['compensation_value'],
            'reason' => $validated['reason'],
            'approval_status' => $validated['approval_status'] ?? 'pending',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return response([
            'data' => [
                'id' => $compensation,
                'order_id' => $validated['order_id'],
                'compensation_type' => $validated['compensation_type'],
                'compensation_value' => $validated['compensation_value'],
                'reason' => $validated['reason'],
                'approval_status' => $validated['approval_status'] ?? 'pending',
                'created_at' => now()
            ]
        ], 201);
    }

    /**
     * Store a customer service activity.
     */
    public function storeActivity(Request $request): Response
    {
        $validated = $request->validate([
            'customer_id' => ['required', 'exists:customers,id'],
            'activity_type' => ['required', 'string', 'in:complaint_resolution,refund_processing,compensation_offered,general_support'],
            'description' => ['required', 'string', 'max:1000'],
            'duration_minutes' => ['nullable', 'integer', 'min:1', 'max:480'],
            'outcome' => ['required', 'string', 'max:255'],
        ]);

        $activity = DB::table('customer_service_activities')->insertGetId([
            'customer_id' => $validated['customer_id'],
            'user_id' => Auth::id(),
            'activity_type' => $validated['activity_type'],
            'description' => $validated['description'],
            'duration_minutes' => $validated['duration_minutes'],
            'outcome' => $validated['outcome'],
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Log security event
        $securityService = new SecurityLoggingService();
        $customer = Customer::find($validated['customer_id']);
        $securityService->logUserAction(
            Auth::user(),
            'customer_service_activity',
            'App\Models\Customer',
            $validated['customer_id'],
            [
                'activity_id' => $activity,
                'activity_type' => $validated['activity_type'],
                'outcome' => $validated['outcome'],
                'duration_minutes' => $validated['duration_minutes'],
            ]
        );

        return response(['data' => ['id' => $activity]], 201);
    }

    /**
     * Get satisfaction metrics.
     */
    public function getSatisfactionMetrics(Request $request): Response
    {
        $query = DB::table('customer_feedback');
        
        // Filter by restaurant if provided
        if ($request->has('restaurant_id')) {
            $query->where('restaurant_id', $request->restaurant_id);
        }
        
        // Filter by branch if provided
        if ($request->has('restaurant_branch_id')) {
            $query->where('restaurant_branch_id', $request->restaurant_branch_id);
        }
        
        $metrics = $query->selectRaw('
                COUNT(*) as total_feedback_count,
                AVG(rating) as average_rating,
                COUNT(CASE WHEN rating >= 4 THEN 1 END) as positive_feedback_count,
                COUNT(CASE WHEN rating <= 2 THEN 1 END) as negative_feedback_count
            ')
            ->first();

        // Get rating distribution
        $ratingDistributionQuery = DB::table('customer_feedback');
        if ($request->has('restaurant_id')) {
            $ratingDistributionQuery->where('restaurant_id', $request->restaurant_id);
        }
        if ($request->has('restaurant_branch_id')) {
            $ratingDistributionQuery->where('restaurant_branch_id', $request->restaurant_branch_id);
        }
        $ratingDistribution = $ratingDistributionQuery
            ->selectRaw('rating, COUNT(*) as count')
            ->groupBy('rating')
            ->orderBy('rating')
            ->get()
            ->pluck('count', 'rating')
            ->toArray();

        // Get feedback by type
        $feedbackByTypeQuery = DB::table('customer_feedback');
        if ($request->has('restaurant_id')) {
            $feedbackByTypeQuery->where('restaurant_id', $request->restaurant_id);
        }
        if ($request->has('restaurant_branch_id')) {
            $feedbackByTypeQuery->where('restaurant_branch_id', $request->restaurant_branch_id);
        }
        $feedbackByType = $feedbackByTypeQuery
            ->selectRaw('feedback_type, COUNT(*) as count, AVG(rating) as avg_rating')
            ->groupBy('feedback_type')
            ->get()
            ->keyBy('feedback_type')
            ->toArray();

        // Get trend over time (last 7 days)
        $trendOverTimeQuery = DB::table('customer_feedback');
        if ($request->has('restaurant_id')) {
            $trendOverTimeQuery->where('restaurant_id', $request->restaurant_id);
        }
        if ($request->has('restaurant_branch_id')) {
            $trendOverTimeQuery->where('restaurant_branch_id', $request->restaurant_branch_id);
        }
        $trendOverTime = $trendOverTimeQuery
            ->selectRaw('DATE(created_at) as date, AVG(rating) as avg_rating, COUNT(*) as count')
            ->where('created_at', '>=', now()->subDays(7))
            ->groupBy('date')
            ->orderBy('date')
            ->get()
            ->toArray();

        return response([
            'data' => [
                'average_rating' => round($metrics->average_rating ?? 0, 2),
                'total_feedback_count' => $metrics->total_feedback_count ?? 0,
                'rating_distribution' => $ratingDistribution,
                'feedback_by_type' => $feedbackByType,
                'trend_over_time' => $trendOverTime,
                'positive_feedback_percentage' => $metrics->total_feedback_count > 0 
                    ? round(($metrics->positive_feedback_count / $metrics->total_feedback_count) * 100, 2)
                    : 0,
                'negative_feedback_percentage' => $metrics->total_feedback_count > 0 
                    ? round(($metrics->negative_feedback_count / $metrics->total_feedback_count) * 100, 2)
                    : 0,
            ]
        ]);
    }
} 