<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\CustomerChallengeService;
use App\Models\Challenge;
use App\Models\Customer;
use App\Models\CustomerChallenge;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;

final class CustomerChallengeController extends Controller
{
    public function __construct(
        private readonly CustomerChallengeService $challengeService
    ) {}

    /**
     * Create a new challenge
     */
    public function createChallenge(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'name' => 'required|string|max:255',
                'description' => 'required|string',
                'challenge_type' => ['required', Rule::in(['frequency', 'variety', 'value', 'social', 'seasonal', 'referral'])],
                'requirements' => 'required|array',
                'reward_type' => ['required', Rule::in(['points', 'discount', 'free_item', 'coupon'])],
                'reward_value' => 'required|numeric|min:0',
                'reward_metadata' => 'nullable|array',
                'start_date' => 'required|date|after:now',
                'end_date' => 'required|date|after:start_date',
                'duration_days' => 'nullable|integer|min:1',
                'target_segments' => 'nullable|array',
                'is_active' => 'boolean',
                'is_repeatable' => 'boolean',
                'max_participants' => 'nullable|integer|min:1',
                'priority' => 'integer|min:1|max:10',
                'metadata' => 'nullable|array',
                'auto_assign' => 'boolean',
            ]);

            $challenge = $this->challengeService->createCustomerChallenge($validated);

            return response()->json([
                'status' => 'success',
                'message' => 'Challenge created successfully',
                'data' => [
                    'challenge' => [
                        'id' => $challenge->id,
                        'name' => $challenge->name,
                        'description' => $challenge->description,
                        'challenge_type' => $challenge->challenge_type,
                        'reward_type' => $challenge->reward_type,
                        'reward_value' => $challenge->reward_value,
                        'start_date' => $challenge->start_date,
                        'end_date' => $challenge->end_date,
                        'is_active' => $challenge->is_active,
                    ]
                ]
            ], 201);

        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Validation failed',
                'errors' => $e->errors()
            ], 422);

        } catch (\Exception $e) {
            Log::error('Failed to create challenge', [
                'error' => $e->getMessage(),
                'request_data' => $request->all()
            ]);

            return response()->json([
                'status' => 'error',
                'message' => 'Failed to create challenge'
            ], 500);
        }
    }

    /**
     * Assign challenge to customer
     */
    public function assignChallenge(Request $request, int $challengeId): JsonResponse
    {
        try {
            $validated = $request->validate([
                'customer_id' => 'required|exists:customers,id',
            ]);

            $challenge = Challenge::findOrFail($challengeId);
            $customer = Customer::findOrFail($validated['customer_id']);

            $customerChallenge = $this->challengeService->assignChallengeToCustomer($challenge, $customer);

            if (!$customerChallenge) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Customer is not eligible for this challenge'
                ], 400);
            }

            return response()->json([
                'status' => 'success',
                'message' => 'Challenge assigned successfully',
                'data' => [
                    'customer_challenge' => [
                        'id' => $customerChallenge->id,
                        'challenge_id' => $customerChallenge->challenge_id,
                        'customer_id' => $customerChallenge->customer_id,
                        'status' => $customerChallenge->status,
                        'assigned_at' => $customerChallenge->assigned_at,
                        'expires_at' => $customerChallenge->expires_at,
                        'progress_percentage' => $customerChallenge->progress_percentage,
                    ]
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to assign challenge', [
                'challenge_id' => $challengeId,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'status' => 'error',
                'message' => 'Failed to assign challenge'
            ], 500);
        }
    }

    /**
     * Get customer's active challenges
     */
    public function getCustomerChallenges(int $customerId): JsonResponse
    {
        try {
            $customer = Customer::findOrFail($customerId);
            $challenges = $this->challengeService->getActiveCustomerChallenges($customer);

            return response()->json([
                'status' => 'success',
                'data' => [
                    'challenges' => $challenges,
                    'total_count' => $challenges->count()
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to get customer challenges', [
                'customer_id' => $customerId,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'status' => 'error',
                'message' => 'Failed to retrieve customer challenges'
            ], 500);
        }
    }

    /**
     * Check customer's challenge progress
     */
    public function checkProgress(int $customerId): JsonResponse
    {
        try {
            $customer = Customer::findOrFail($customerId);
            $progress = $this->challengeService->checkChallengeProgress($customer);

            return response()->json([
                'status' => 'success',
                'data' => [
                    'progress' => $progress,
                    'total_active_challenges' => $progress->count()
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to check challenge progress', [
                'customer_id' => $customerId,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'status' => 'error',
                'message' => 'Failed to check challenge progress'
            ], 500);
        }
    }

    /**
     * Update challenge progress (called by system events)
     */
    public function updateProgress(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'customer_id' => 'required|exists:customers,id',
                'action_type' => 'required|string',
                'action_data' => 'required|array',
            ]);

            $customer = Customer::findOrFail($validated['customer_id']);

            $this->challengeService->updateChallengeProgress(
                $customer,
                $validated['action_type'],
                $validated['action_data']
            );

            return response()->json([
                'status' => 'success',
                'message' => 'Challenge progress updated successfully'
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to update challenge progress', [
                'error' => $e->getMessage(),
                'request_data' => $request->all()
            ]);

            return response()->json([
                'status' => 'error',
                'message' => 'Failed to update challenge progress'
            ], 500);
        }
    }

    /**
     * Get challenge leaderboard
     */
    public function getLeaderboard(int $challengeId, Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'scope' => 'nullable|in:restaurant,branch',
                'scope_id' => 'nullable|integer',
            ]);

            $challenge = Challenge::findOrFail($challengeId);
            
            $leaderboard = $this->challengeService->getChallengeLeaderboard(
                $challenge,
                $validated['scope'] ?? null,
                $validated['scope_id'] ?? null
            );

            return response()->json([
                'status' => 'success',
                'data' => [
                    'challenge' => [
                        'id' => $challenge->id,
                        'name' => $challenge->name,
                        'challenge_type' => $challenge->challenge_type,
                    ],
                    'leaderboard' => $leaderboard,
                    'total_participants' => $leaderboard->count()
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to get challenge leaderboard', [
                'challenge_id' => $challengeId,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'status' => 'error',
                'message' => 'Failed to retrieve leaderboard'
            ], 500);
        }
    }

    /**
     * Track customer engagement with challenges
     */
    public function trackEngagement(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'customer_id' => 'required|exists:customers,id',
                'challenge_id' => 'nullable|exists:challenges,id',
                'event_type' => 'required|in:challenge_viewed,progress_checked,leaderboard_viewed,challenge_shared,notification_clicked,reward_claimed',
                'event_data' => 'nullable|array',
                'source' => 'nullable|string',
                'session_id' => 'nullable|string',
            ]);

            $customer = Customer::findOrFail($validated['customer_id']);
            $challenge = $validated['challenge_id'] ? Challenge::find($validated['challenge_id']) : null;

            $this->challengeService->trackChallengeEngagement(
                $customer,
                $challenge,
                $validated['event_type'],
                [
                    'source' => $validated['source'] ?? 'web',
                    'session_id' => $validated['session_id'] ?? null,
                    'user_agent' => $request->userAgent(),
                    'event_data' => $validated['event_data'] ?? [],
                ]
            );

            return response()->json([
                'status' => 'success',
                'message' => 'Engagement tracked successfully'
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to track challenge engagement', [
                'error' => $e->getMessage(),
                'request_data' => $request->all()
            ]);

            return response()->json([
                'status' => 'error',
                'message' => 'Failed to track engagement'
            ], 500);
        }
    }

    /**
     * Generate weekly challenges (admin function)
     */
    public function generateWeeklyChallenges(): JsonResponse
    {
        try {
            $challenges = $this->challengeService->generateWeeklyChallenges();

            return response()->json([
                'status' => 'success',
                'message' => 'Weekly challenges generated successfully',
                'data' => [
                    'challenges' => $challenges->map(function ($challenge) {
                        return [
                            'id' => $challenge->id,
                            'name' => $challenge->name,
                            'challenge_type' => $challenge->challenge_type,
                            'start_date' => $challenge->start_date,
                            'end_date' => $challenge->end_date,
                        ];
                    }),
                    'total_generated' => $challenges->count()
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to generate weekly challenges', [
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'status' => 'error',
                'message' => 'Failed to generate weekly challenges'
            ], 500);
        }
    }

    /**
     * Calculate challenge rewards for customer
     */
    public function calculateRewards(int $challengeId, int $customerId): JsonResponse
    {
        try {
            $challenge = Challenge::findOrFail($challengeId);
            $customer = Customer::findOrFail($customerId);

            $rewardCalculation = $this->challengeService->calculateChallengeRewards($challenge, $customer);

            return response()->json([
                'status' => 'success',
                'data' => [
                    'challenge' => [
                        'id' => $challenge->id,
                        'name' => $challenge->name,
                        'reward_type' => $challenge->reward_type,
                    ],
                    'customer' => [
                        'id' => $customer->id,
                        'name' => $customer->name,
                    ],
                    'reward_calculation' => $rewardCalculation
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to calculate challenge rewards', [
                'challenge_id' => $challengeId,
                'customer_id' => $customerId,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'status' => 'error',
                'message' => 'Failed to calculate rewards'
            ], 500);
        }
    }

    /**
     * Complete challenge and award reward
     */
    public function completeChallenge(int $customerChallengeId): JsonResponse
    {
        try {
            $customerChallenge = CustomerChallenge::with(['challenge', 'customer'])->findOrFail($customerChallengeId);

            $success = $this->challengeService->completeChallengeReward($customerChallenge);

            if (!$success) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Challenge completion validation failed'
                ], 400);
            }

            return response()->json([
                'status' => 'success',
                'message' => 'Challenge completed and reward awarded successfully',
                'data' => [
                    'customer_challenge' => [
                        'id' => $customerChallenge->id,
                        'status' => $customerChallenge->status,
                        'completed_at' => $customerChallenge->completed_at,
                        'reward_claimed' => $customerChallenge->reward_claimed,
                        'reward_details' => $customerChallenge->reward_details,
                    ]
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to complete challenge', [
                'customer_challenge_id' => $customerChallengeId,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'status' => 'error',
                'message' => 'Failed to complete challenge'
            ], 500);
        }
    }

    /**
     * Expire old challenges (admin function)
     */
    public function expireOldChallenges(): JsonResponse
    {
        try {
            $expiredCount = $this->challengeService->expireOldChallenges();

            return response()->json([
                'status' => 'success',
                'message' => 'Old challenges expired successfully',
                'data' => [
                    'expired_count' => $expiredCount
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to expire old challenges', [
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'status' => 'error',
                'message' => 'Failed to expire old challenges'
            ], 500);
        }
    }

    /**
     * Get all active challenges
     */
    public function getAllChallenges(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'challenge_type' => 'nullable|in:frequency,variety,value,social,seasonal,referral',
                'is_active' => 'nullable|boolean',
                'limit' => 'nullable|integer|min:1|max:100',
            ]);

            $query = Challenge::query();

            if (isset($validated['challenge_type'])) {
                $query->where('challenge_type', $validated['challenge_type']);
            }

            if (isset($validated['is_active'])) {
                if ($validated['is_active']) {
                    $query->active();
                } else {
                    $query->where('is_active', false);
                }
            }

            $challenges = $query->orderBy('priority', 'desc')
                ->orderBy('created_at', 'desc')
                ->limit($validated['limit'] ?? 20)
                ->get();

            return response()->json([
                'status' => 'success',
                'data' => [
                    'challenges' => $challenges->map(function ($challenge) {
                        return [
                            'id' => $challenge->id,
                            'name' => $challenge->name,
                            'description' => $challenge->description,
                            'challenge_type' => $challenge->challenge_type,
                            'reward_type' => $challenge->reward_type,
                            'reward_value' => $challenge->reward_value,
                            'start_date' => $challenge->start_date,
                            'end_date' => $challenge->end_date,
                            'is_active' => $challenge->is_active,
                            'completion_rate' => $challenge->getCompletionRate(),
                            'average_completion_time' => $challenge->getAverageCompletionTime(),
                        ];
                    }),
                    'total_count' => $challenges->count()
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to get all challenges', [
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'status' => 'error',
                'message' => 'Failed to retrieve challenges'
            ], 500);
        }
    }
}