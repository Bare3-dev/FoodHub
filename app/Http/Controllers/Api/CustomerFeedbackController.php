<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\CustomerFeedback;
use App\Models\Order;
use App\Models\Customer;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;

class CustomerFeedbackController extends Controller
{
    /**
     * Store a newly created feedback.
     */
    public function store(Request $request): Response
    {
        $validated = $request->validate([
            'order_id' => ['required', 'exists:orders,id'],
            'rating' => ['required', 'integer', 'min:1', 'max:5'],
            'feedback_type' => ['required', 'string', 'in:food_quality,service,delivery,overall,cleanliness,value_for_money,menu_variety,special_requests'],
            'comments' => ['nullable', 'string', 'max:1000'],
            'would_recommend' => ['nullable', 'boolean'],
        ]);

        // Get the order and customer
        $order = Order::findOrFail($validated['order_id']);
        
        // Create feedback
        $feedback = CustomerFeedback::create([
            'order_id' => $validated['order_id'],
            'customer_id' => $order->customer_id,
            'user_id' => null, // Customer feedback doesn't need to reference a specific user
            'restaurant_id' => $order->restaurant_id,
            'restaurant_branch_id' => $order->restaurant_branch_id,
            'rating' => $validated['rating'],
            'feedback_type' => $validated['feedback_type'],
            'feedback_text' => $validated['comments'] ?? null,
            'feedback_details' => [
                'would_recommend' => $validated['would_recommend'] ?? null,
            ],
            'status' => 'pending',
        ]);

        return response([
            'data' => [
                'id' => $feedback->id,
                'order_id' => $feedback->order_id,
                'rating' => $feedback->rating,
                'feedback_type' => $feedback->feedback_type,
                'comments' => $feedback->feedback_text,
                'created_at' => $feedback->created_at
            ]
        ], 201);
    }
} 