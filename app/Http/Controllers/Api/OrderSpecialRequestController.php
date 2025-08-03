<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class OrderSpecialRequestController extends Controller
{
    /**
     * Store a newly created special request.
     */
    public function store(Request $request): Response
    {
        $validated = $request->validate([
            'order_id' => ['required', 'exists:orders,id'],
            'customer_id' => ['required', 'exists:customers,id'],
            'request_type' => ['required', 'string', 'in:dietary_restriction,allergy,preparation_instruction,customization'],
            'description' => ['required', 'string', 'max:1000'],
            'priority' => ['required', 'string', 'in:low,medium,high,urgent'],
            'requires_kitchen_attention' => ['boolean'],
        ]);

        $specialRequest = DB::table('order_special_requests')->insertGetId([
            'order_id' => $validated['order_id'],
            'customer_id' => $validated['customer_id'],
            'request_type' => $validated['request_type'],
            'description' => $validated['description'],
            'priority' => $validated['priority'],
            'requires_kitchen_attention' => $validated['requires_kitchen_attention'] ?? false,
            'status' => 'pending',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return response(['data' => [
            'id' => $specialRequest,
            'order_id' => $validated['order_id'],
            'request_type' => $validated['request_type'],
            'description' => $validated['description'],
            'priority' => $validated['priority'],
            'status' => 'pending',
            'created_at' => now(),
            'updated_at' => now()
        ]], 201);
    }
} 