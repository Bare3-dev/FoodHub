<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreCustomerLoyaltyPointsRequest;
use App\Http\Requests\UpdateCustomerLoyaltyPointsRequest;
use App\Http\Resources\Api\CustomerLoyaltyPointsResource;
use App\Models\CustomerLoyaltyPoint;
use App\Models\Customer;
use App\Models\LoyaltyProgram;
use App\Models\LoyaltyTier;
use App\Models\LoyaltyPointsHistory;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

final class CustomerLoyaltyPointsController extends Controller
{
    /**
     * Display a listing of customer loyalty points.
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $query = CustomerLoyaltyPoint::with(['customer', 'loyaltyProgram', 'loyaltyTier'])
            ->active();

        // Filter by customer
        if ($request->has('customer_id')) {
            $query->where('customer_id', $request->customer_id);
        }

        // Filter by loyalty program
        if ($request->has('loyalty_program_id')) {
            $query->where('loyalty_program_id', $request->loyalty_program_id);
        }

        // Filter by tier
        if ($request->has('tier_id')) {
            $query->where('loyalty_tier_id', $request->tier_id);
        }

        // Filter by points range
        if ($request->has('min_points')) {
            $query->where('current_points', '>=', $request->min_points);
        }

        if ($request->has('max_points')) {
            $query->where('current_points', '<=', $request->max_points);
        }

        // Filter by expiry status
        if ($request->has('expiring_soon')) {
            $query->expiringSoon($request->expiring_soon ?? 30);
        }

        $perPage = min($request->get('per_page', 15), 100);
        $loyaltyPoints = $query->paginate($perPage);

        return CustomerLoyaltyPointsResource::collection($loyaltyPoints);
    }

    /**
     * Store a newly created customer loyalty points record.
     */
    public function store(StoreCustomerLoyaltyPointsRequest $request): JsonResponse
    {
        $data = $request->validated();

        // Check if customer already has loyalty points for this program
        $existingPoints = CustomerLoyaltyPoint::where('customer_id', $data['customer_id'])
            ->where('loyalty_program_id', $data['loyalty_program_id'])
            ->first();

        if ($existingPoints) {
            return response()->json([
                'message' => 'Customer already has loyalty points for this program',
                'data' => new CustomerLoyaltyPointsResource($existingPoints)
            ], 409);
        }

        // Get the appropriate tier based on points
        $tier = LoyaltyTier::where('loyalty_program_id', $data['loyalty_program_id'])
            ->where('min_points_required', '<=', $data['current_points'] ?? 0)
            ->orderByDesc('min_points_required')
            ->first();

        $data['loyalty_tier_id'] = $tier?->id;
        $data['points_expiry_date'] = Carbon::now()->addYear();

        $loyaltyPoints = CustomerLoyaltyPoint::create($data);

        return response()->json([
            'message' => 'Customer loyalty points created successfully',
            'data' => new CustomerLoyaltyPointsResource($loyaltyPoints)
        ], 201);
    }

    /**
     * Display the specified customer loyalty points.
     */
    public function show(CustomerLoyaltyPoint $customerLoyaltyPoint): JsonResponse
    {
        $customerLoyaltyPoint->load(['customer', 'loyaltyProgram', 'loyaltyTier', 'pointsHistory']);

        return response()->json([
            'data' => new CustomerLoyaltyPointsResource($customerLoyaltyPoint)
        ]);
    }

    /**
     * Update the specified customer loyalty points.
     */
    public function update(UpdateCustomerLoyaltyPointsRequest $request, CustomerLoyaltyPoint $customerLoyaltyPoint): JsonResponse
    {
        $data = $request->validated();

        // Update tier if points changed
        if (isset($data['current_points'])) {
            $newTier = LoyaltyTier::where('loyalty_program_id', $customerLoyaltyPoint->loyalty_program_id)
                ->where('min_points_required', '<=', $data['current_points'])
                ->orderByDesc('min_points_required')
                ->first();

            $data['loyalty_tier_id'] = $newTier?->id;
        }

        $customerLoyaltyPoint->update($data);

        return response()->json([
            'message' => 'Customer loyalty points updated successfully',
            'data' => new CustomerLoyaltyPointsResource($customerLoyaltyPoint)
        ]);
    }

    /**
     * Remove the specified customer loyalty points.
     */
    public function destroy(CustomerLoyaltyPoint $customerLoyaltyPoint): JsonResponse
    {
        $customerLoyaltyPoint->delete();

        return response()->json([
            'message' => 'Customer loyalty points deleted successfully'
        ]);
    }

    /**
     * Earn points for a customer.
     */
    public function earnPoints(Request $request): JsonResponse
    {
        $request->validate([
            'customer_id' => 'required|integer',
            'loyalty_program_id' => 'required|integer',
            'points_amount' => 'required|numeric|min:0.01',
            'source' => 'required|string|in:order,bonus,referral,birthday,happy_hour,first_order,tier_upgrade,promotion',
            'order_id' => 'nullable|exists:orders,id',
            'base_amount' => 'nullable|numeric|min:0',
            'multiplier_applied' => 'nullable|numeric|min:1',
            'bonus_multipliers_applied' => 'nullable|array',
            'description' => 'required|string|max:255',
        ]);

        return DB::transaction(function () use ($request) {
            $customerLoyaltyPoint = CustomerLoyaltyPoint::where('customer_id', $request->customer_id)
                ->where('loyalty_program_id', $request->loyalty_program_id)
                ->first();

            if (!$customerLoyaltyPoint) {
                return response()->json([
                    'message' => 'Customer loyalty points not found for this program'
                ], 404);
            }

            $pointsToEarn = $request->points_amount;
            $newBalance = $customerLoyaltyPoint->current_points + $pointsToEarn;

            // Update customer loyalty points
            $customerLoyaltyPoint->update([
                'current_points' => $newBalance,
                'total_points_earned' => $customerLoyaltyPoint->total_points_earned + $pointsToEarn,
                'last_points_earned_date' => now(),
            ]);

            // Check for tier upgrade
            $currentTierId = $customerLoyaltyPoint->loyalty_tier_id;
            $newTier = LoyaltyTier::where('loyalty_program_id', $customerLoyaltyPoint->loyalty_program_id)
                ->where('min_points_required', '<=', $newBalance)
                ->orderByDesc('min_points_required')
                ->first();

            $tierUpgraded = false;
            if ($newTier && $newTier->id !== $currentTierId) {
                $customerLoyaltyPoint->update(['loyalty_tier_id' => $newTier->id]);
                $tierUpgraded = true;
            }

            // Create points history record
            LoyaltyPointsHistory::create([
                'customer_loyalty_points_id' => $customerLoyaltyPoint->id,
                'order_id' => $request->order_id,
                'transaction_type' => 'earned',
                'points_amount' => $pointsToEarn,
                'points_balance_after' => $newBalance,
                'description' => $request->description,
                'transaction_details' => [
                    'order_id' => $request->order_id,
                    'base_amount' => $request->base_amount,
                    'multiplier_applied' => $request->multiplier_applied,
                ],
                'source' => $request->source,
                'bonus_multipliers_applied' => $request->bonus_multipliers_applied,
                'base_amount' => $request->base_amount,
                'multiplier_applied' => $request->multiplier_applied ?? 1.0,
                'reference_id' => $request->order_id,
                'reference_type' => 'order',
                'is_reversible' => true,
            ]);

            return response()->json([
                'message' => 'Points earned successfully',
                'data' => [
                    'points_earned' => $pointsToEarn,
                    'new_balance' => $newBalance,
                    'tier_upgraded' => $tierUpgraded,
                    'new_tier' => $newTier ? $newTier->name : null,
                ]
            ]);
        });
    }

    /**
     * Redeem points for a customer.
     */
    public function redeemPoints(Request $request): JsonResponse
    {
        $request->validate([
            'customer_id' => 'required|exists:customers,id',
            'loyalty_program_id' => 'required|exists:loyalty_programs,id',
            'points_amount' => 'required|numeric|min:0.01',
            'redemption_type' => 'required|string|in:discount,free_item,free_delivery',
            'order_id' => 'nullable|exists:orders,id',
            'description' => 'required|string|max:255',
        ]);

        return DB::transaction(function () use ($request) {
            $customerLoyaltyPoint = CustomerLoyaltyPoint::where('customer_id', $request->customer_id)
                ->where('loyalty_program_id', $request->loyalty_program_id)
                ->first();

            if (!$customerLoyaltyPoint) {
                return response()->json([
                    'message' => 'Customer loyalty points not found for this program'
                ], 404);
            }

            if ($customerLoyaltyPoint->current_points < $request->points_amount) {
                return response()->json([
                    'message' => 'Insufficient points for redemption'
                ], 400);
            }

            $pointsToRedeem = $request->points_amount;
            $newBalance = $customerLoyaltyPoint->current_points - $pointsToRedeem;

            // Update customer loyalty points
            $customerLoyaltyPoint->update([
                'current_points' => $newBalance,
                'total_points_redeemed' => $customerLoyaltyPoint->total_points_redeemed + $pointsToRedeem,
                'last_points_redeemed_date' => now(),
            ]);

            // Create points history record
            LoyaltyPointsHistory::create([
                'customer_loyalty_points_id' => $customerLoyaltyPoint->id,
                'order_id' => $request->order_id,
                'transaction_type' => 'redeemed',
                'points_amount' => $pointsToRedeem,
                'points_balance_after' => $newBalance,
                'description' => $request->description,
                'transaction_details' => [
                    'redemption_type' => $request->redemption_type,
                    'order_id' => $request->order_id,
                ],
                'source' => $request->redemption_type,
                'reference_id' => $request->order_id,
                'reference_type' => 'order',
                'is_reversible' => true,
            ]);

            return response()->json([
                'message' => 'Points redeemed successfully',
                'data' => [
                    'points_redeemed' => $pointsToRedeem,
                    'new_balance' => $newBalance,
                    'redemption_type' => $request->redemption_type,
                ]
            ]);
        });
    }

    /**
     * Get customer loyalty points summary.
     */
    public function summary(Request $request): JsonResponse
    {
        $request->validate([
            'customer_id' => 'required|exists:customers,id',
            'loyalty_program_id' => 'required|exists:loyalty_programs,id',
        ]);

        $customerLoyaltyPoint = CustomerLoyaltyPoint::with(['loyaltyTier', 'loyaltyProgram'])
            ->where('customer_id', $request->customer_id)
            ->where('loyalty_program_id', $request->loyalty_program_id)
            ->first();

        if (!$customerLoyaltyPoint) {
            return response()->json([
                'message' => 'Customer loyalty points not found'
            ], 404);
        }

        // Get recent transactions
        $recentTransactions = $customerLoyaltyPoint->pointsHistory()
            ->with('order')
            ->orderByDesc('created_at')
            ->limit(10)
            ->get();

        // Get tier progress
        $nextTierProgress = $customerLoyaltyPoint->next_tier_progress;

        return response()->json([
            'data' => [
                'current_points' => $customerLoyaltyPoint->current_points,
                'total_points_earned' => $customerLoyaltyPoint->total_points_earned,
                'total_points_redeemed' => $customerLoyaltyPoint->total_points_redeemed,
                'total_points_expired' => $customerLoyaltyPoint->total_points_expired,
                'available_points' => $customerLoyaltyPoint->available_points,
                'points_to_expire' => $customerLoyaltyPoint->points_to_expire,
                'current_tier' => $customerLoyaltyPoint->loyaltyTier,
                'next_tier_progress' => $nextTierProgress,
                'recent_transactions' => $recentTransactions,
                'loyalty_program' => $customerLoyaltyPoint->loyaltyProgram,
            ]
        ]);
    }

    /**
     * Process points expiration.
     */
    public function processExpiration(): JsonResponse
    {
        $expiredPoints = CustomerLoyaltyPoint::where('points_expiry_date', '<=', now())
            ->where('current_points', '>', 0)
            ->get();

        $totalExpired = 0;

        foreach ($expiredPoints as $loyaltyPoint) {
            $expiredAmount = $loyaltyPoint->current_points;
            
            $loyaltyPoint->update([
                'current_points' => 0,
                'total_points_expired' => $loyaltyPoint->total_points_expired + $expiredAmount,
            ]);

            // Create expiration history record
            LoyaltyPointsHistory::create([
                'customer_loyalty_points_id' => $loyaltyPoint->id,
                'transaction_type' => 'expired',
                'points_amount' => $expiredAmount,
                'points_balance_after' => 0,
                'description' => 'Points expired due to inactivity',
                'source' => 'expiration',
                'is_reversible' => false,
            ]);

            $totalExpired += $expiredAmount;
        }

        return response()->json([
            'message' => 'Points expiration processed successfully',
            'data' => [
                'customers_affected' => $expiredPoints->count(),
                'total_points_expired' => $totalExpired,
            ]
        ]);
    }
} 