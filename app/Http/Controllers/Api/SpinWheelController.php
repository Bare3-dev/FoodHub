<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\SpinWheel\BuySpinsRequest;
use App\Http\Requests\SpinWheel\RedeemPrizeRequest;
use App\Http\Resources\Api\SpinResultResource;
use App\Services\SpinWheelService;
use App\Traits\ApiErrorResponse;
use App\Traits\ApiSuccessResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

final class SpinWheelController extends Controller
{
    use ApiSuccessResponse, ApiErrorResponse;

    public function __construct(
        private readonly SpinWheelService $spinWheelService
    ) {}

    /**
     * Get the authenticated customer or user.
     */
    private function getAuthenticatedCustomer()
    {
        $user = Auth::user();
        
        // If it's a Customer model, return it directly
        if ($user instanceof \App\Models\Customer) {
            return $user;
        }
        
        // If it's a User model, try to find the associated customer
        if ($user instanceof \App\Models\User) {
            // For now, we'll assume the user is a customer
            // In a real application, you might have a relationship between User and Customer
            return \App\Models\Customer::where('email', $user->email)->first();
        }
        
        return null;
    }

    /**
     * Get customer's spin wheel status.
     */
    public function getStatus(Request $request): JsonResponse
    {
        try {
            $customer = $this->getAuthenticatedCustomer();
            
            if (!$customer) {
                return $this->errorResponse('Unauthorized', 'UNAUTHORIZED', 401);
            }

            $status = $this->spinWheelService->getCustomerSpinStatus($customer);

            return $this->successResponse($status, 'Spin wheel status retrieved successfully');
        } catch (\Exception $e) {
            return $this->errorResponse('Failed to get spin wheel status: ' . $e->getMessage(), 'GET_STATUS_ERROR', 500);
        }
    }

    /**
     * Spin the wheel.
     */
    public function spin(Request $request): JsonResponse
    {
        try {
            $customer = $this->getAuthenticatedCustomer();
            
            if (!$customer) {
                return $this->errorResponse('Unauthorized', 'UNAUTHORIZED', 401);
            }

            $spinResult = $this->spinWheelService->processSpinWheelResult($customer);

            if (!$spinResult) {
                return response()->json([
                    'success' => false,
                    'message' => 'Cannot spin the wheel at this time',
                ], 400);
            }

            return $this->successResponse([
                'spin_result' => new SpinResultResource($spinResult),
                'prize_won' => [
                    'name' => $spinResult->prize_name,
                    'type' => $spinResult->prize_type,
                    'value' => $spinResult->prize_value,
                    'display_value' => $spinResult->display_value,
                    'description' => $spinResult->prize_description,
                ],
            ], 'Spin completed successfully');
        } catch (\Exception $e) {
            return $this->errorResponse('Failed to spin the wheel: ' . $e->getMessage(), 'SPIN_ERROR', 500);
        }
    }

    /**
     * Buy spins with loyalty points.
     */
    public function buySpins(BuySpinsRequest $request): JsonResponse
    {
        try {
            $customer = $this->getAuthenticatedCustomer();
            
            if (!$customer) {
                return $this->errorResponse('Unauthorized', 'UNAUTHORIZED', 401);
            }

            $quantity = $request->validated('quantity');
            
            $success = $this->spinWheelService->buySpins($customer, $quantity);

            if (!$success) {
                return response()->json([
                    'success' => false,
                    'message' => 'Failed to buy spins. Insufficient loyalty points or no active spin wheel.',
                ], 400);
            }

            // Get updated status
            $status = $this->spinWheelService->getCustomerSpinStatus($customer);

            return $this->successResponse([
                'spins_purchased' => $quantity,
                'updated_status' => $status,
            ], 'Spins purchased successfully');
        } catch (\Exception $e) {
            return $this->errorResponse('Failed to buy spins: ' . $e->getMessage(), 'BUY_SPINS_ERROR', 500);
        }
    }

    /**
     * Get customer's redeemable prizes.
     */
    public function getRedeemablePrizes(Request $request): JsonResponse
    {
        try {
            $customer = $this->getAuthenticatedCustomer();
            
            if (!$customer) {
                return $this->errorResponse('Unauthorized', 'UNAUTHORIZED', 401);
            }

            $prizes = $this->spinWheelService->getCustomerRedeemablePrizes($customer);

            return $this->successResponse([
                'prizes' => $prizes,
                'total_count' => count($prizes),
            ], 'Redeemable prizes retrieved successfully');
        } catch (\Exception $e) {
            return $this->errorResponse('Failed to get redeemable prizes: ' . $e->getMessage(), 'GET_PRIZES_ERROR', 500);
        }
    }

    /**
     * Redeem a prize.
     */
    public function redeemPrize(RedeemPrizeRequest $request): JsonResponse
    {
        try {
            $customer = $this->getAuthenticatedCustomer();
            
            if (!$customer) {
                return $this->errorResponse('Unauthorized', 'UNAUTHORIZED', 401);
            }

            $spinResultId = $request->validated('spin_result_id');
            $orderId = $request->validated('order_id');

            $success = $this->spinWheelService->redeemSpinResult($customer, $spinResultId, $orderId);

            if (!$success) {
                return response()->json([
                    'success' => false,
                    'message' => 'Failed to redeem prize. Prize may be expired or already redeemed.',
                ], 400);
            }

            return response()->json([
                'success' => true,
                'message' => 'Prize redeemed successfully',
            ], 200);
        } catch (\Exception $e) {
            return $this->errorResponse('Failed to redeem prize: ' . $e->getMessage(), 'REDEEM_PRIZE_ERROR', 500);
        }
    }

    /**
     * Get spin wheel configuration (for admin/display purposes).
     */
    public function getConfiguration(Request $request): JsonResponse
    {
        try {
            $activeSpinWheel = \App\Models\SpinWheel::currentlyActive()->with('activePrizes')->first();

            if (!$activeSpinWheel) {
                return $this->errorResponse('No active spin wheel available', 'NO_ACTIVE_WHEEL', 404);
            }

            $configuration = [
                'wheel' => [
                    'id' => $activeSpinWheel->id,
                    'name' => $activeSpinWheel->name,
                    'description' => $activeSpinWheel->description,
                    'daily_free_spins_base' => $activeSpinWheel->daily_free_spins_base,
                    'max_daily_spins' => $activeSpinWheel->max_daily_spins,
                    'spin_cost_points' => $activeSpinWheel->spin_cost_points,
                ],
                'prizes' => $activeSpinWheel->activePrizes->map(function ($prize) {
                    return [
                        'id' => $prize->id,
                        'name' => $prize->name,
                        'type' => $prize->type,
                        'display_value' => $prize->display_value,
                        'description' => $prize->description,
                    ];
                }),
            ];

            return $this->successResponse($configuration, 'Spin wheel configuration retrieved successfully');
        } catch (\Exception $e) {
            return $this->errorResponse('Failed to get spin wheel configuration: ' . $e->getMessage(), 'GET_CONFIG_ERROR', 500);
        }
    }
} 