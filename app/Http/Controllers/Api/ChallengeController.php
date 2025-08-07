<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Challenge;
use App\Models\Customer;
use App\Models\CustomerChallenge;
use App\Services\CustomerChallengeService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;

final class ChallengeController extends Controller
{
    public function __construct(
        private readonly CustomerChallengeService $challengeService
    ) {}

    /**
     * Get all challenges
     */
    public function index(Request $request): JsonResponse
    {
        $challenges = Challenge::query()
            ->when($request->has('challenge_type'), function ($query) use ($request) {
                $query->where('challenge_type', $request->challenge_type);
            })
            ->with(['customerChallenges'])
            ->paginate(15);

        // Calculate completion rate for each challenge
        $challengesWithStats = $challenges->items();
        foreach ($challengesWithStats as $challenge) {
            $totalParticipants = $challenge->customerChallenges->count();
            $completedParticipants = $challenge->customerChallenges->where('status', 'completed')->count();
            
            $challenge->completion_rate = $totalParticipants > 0 
                ? round(($completedParticipants / $totalParticipants) * 100, 2) 
                : 0;
        }

        return response()->json([
            'status' => 'success',
            'data' => [
                'challenges' => $challengesWithStats,
                'total_count' => $challenges->total(),
            ]
        ]);
    }

    /**
     * Create a new challenge
     */
    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'description' => 'required|string',
            'challenge_type' => 'required|string|in:frequency,variety,spending,referral',
            'requirements' => 'required|array',
            'reward_type' => 'required|string|in:points,discount,free_item',
            'reward_value' => 'required|numeric|min:0',
            'start_date' => 'required|date',
            'end_date' => 'required|date|after:start_date',
            'is_active' => 'boolean',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        $challenge = Challenge::create($request->all());

        return response()->json([
            'status' => 'success',
            'message' => 'Challenge created successfully',
            'data' => [
                'challenge' => $challenge
            ]
        ], 201);
    }

    /**
     * Assign challenge to customer
     */
    public function assign(Request $request, Challenge $challenge): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'customer_id' => 'required|exists:customers,id',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        $customer = Customer::findOrFail($request->customer_id);

        if (!$challenge->is_active) {
            return response()->json([
                'status' => 'error',
                'message' => 'Customer is not eligible for this challenge',
            ], 400);
        }

        $customerChallenge = $this->challengeService->assignChallengeToCustomer($challenge, $customer);

        return response()->json([
            'status' => 'success',
            'message' => 'Challenge assigned successfully',
            'data' => [
                'customer_challenge' => $customerChallenge
            ]
        ]);
    }

    /**
     * Get customer challenges
     */
    public function getCustomerChallenges(Customer $customer): JsonResponse
    {
        $customerChallenges = $customer->customerChallenges()
            ->with(['challenge'])
            ->where('status', '!=', 'completed')
            ->get();

        $challenges = $customerChallenges->map(function ($customerChallenge) {
            return [
                'id' => $customerChallenge->id,
                'challenge' => [
                    'id' => $customerChallenge->challenge->id,
                    'name' => $customerChallenge->challenge->name,
                    'description' => $customerChallenge->challenge->description,
                    'challenge_type' => $customerChallenge->challenge->challenge_type,
                    'reward_type' => $customerChallenge->challenge->reward_type,
                    'reward_value' => $customerChallenge->challenge->reward_value,
                ],
                'progress' => [
                    'current' => $customerChallenge->progress_current,
                    'target' => $customerChallenge->progress_target,
                    'percentage' => $customerChallenge->progress_percentage,
                ],
                'timing' => [
                    'assigned_at' => $customerChallenge->assigned_at,
                    'started_at' => $customerChallenge->started_at,
                    'expires_at' => $customerChallenge->expires_at,
                    'days_remaining' => now()->diffInDays($customerChallenge->expires_at, false),
                ],
                'status' => $customerChallenge->status,
            ];
        });

        return response()->json([
            'status' => 'success',
            'data' => [
                'challenges' => $challenges,
                'total_count' => $challenges->count(),
            ]
        ]);
    }

    /**
     * Check customer challenge progress
     */
    public function getCustomerProgress(Customer $customer): JsonResponse
    {
        $activeChallenges = $customer->customerChallenges()
            ->with(['challenge'])
            ->where('status', 'active')
            ->get();

        $progress = $activeChallenges->map(function ($customerChallenge) {
            return [
                'challenge_name' => $customerChallenge->challenge->name,
                'progress_percentage' => $customerChallenge->progress_percentage,
                'days_remaining' => now()->diffInDays($customerChallenge->expires_at, false),
                'is_close_to_completion' => $customerChallenge->progress_percentage >= 80,
            ];
        });

        return response()->json([
            'status' => 'success',
            'data' => [
                'progress' => $progress,
                'total_active_challenges' => $activeChallenges->count(),
            ]
        ]);
    }

    /**
     * Update challenge progress
     */
    public function updateProgress(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'customer_id' => 'required|exists:customers,id',
            'action_type' => 'required|string',
            'action_data' => 'required|array',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        $this->challengeService->updateProgress(
            $request->customer_id,
            $request->action_type,
            $request->action_data
        );

        return response()->json([
            'status' => 'success',
            'message' => 'Challenge progress updated successfully',
        ]);
    }

    /**
     * Get challenge leaderboard
     */
    public function getLeaderboard(Challenge $challenge): JsonResponse
    {
        $participants = $challenge->customerChallenges()
            ->with(['customer'])
            ->orderBy('progress_percentage', 'desc')
            ->get();

        $leaderboard = $participants->map(function ($customerChallenge, $index) {
            return [
                'rank' => $index + 1,
                'customer' => $customerChallenge->customer,
                'progress' => [
                    'percentage' => $customerChallenge->progress_percentage,
                    'current' => $customerChallenge->progress_current,
                    'target' => $customerChallenge->progress_target,
                ],
                'status' => $customerChallenge->status,
            ];
        });

        return response()->json([
            'status' => 'success',
            'data' => [
                'challenge' => $challenge,
                'leaderboard' => $leaderboard,
                'total_participants' => $participants->count(),
            ]
        ]);
    }

    /**
     * Track engagement
     */
    public function trackEngagement(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'customer_id' => 'required|exists:customers,id',
            'challenge_id' => 'required|exists:challenges,id',
            'event_type' => 'required|string',
            'event_data' => 'array',
            'source' => 'string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        $this->challengeService->trackEngagement(
            $request->customer_id,
            $request->challenge_id,
            $request->event_type,
            $request->event_data ?? [],
            $request->source ?? 'api'
        );

        return response()->json([
            'status' => 'success',
            'message' => 'Engagement tracked successfully',
        ]);
    }

    /**
     * Generate weekly challenges
     */
    public function generateWeekly(): JsonResponse
    {
        $challenges = $this->challengeService->generateWeeklyChallenges();

        return response()->json([
            'status' => 'success',
            'message' => 'Weekly challenges generated successfully',
            'data' => [
                'challenges' => $challenges,
                'total_generated' => count($challenges),
            ]
        ]);
    }

    /**
     * Calculate challenge rewards
     */
    public function calculateRewards(Challenge $challenge, Customer $customer): JsonResponse
    {
        $rewardCalculation = $this->challengeService->calculateRewards($challenge, $customer);

        return response()->json([
            'status' => 'success',
            'data' => [
                'challenge' => [
                    'id' => $challenge->id,
                    'name' => $challenge->name,
                    'description' => $challenge->description,
                    'challenge_type' => $challenge->challenge_type,
                    'reward_type' => $challenge->reward_type,
                    'reward_value' => $challenge->reward_value,
                ],
                'customer' => [
                    'id' => $customer->id,
                    'name' => $customer->first_name . ' ' . $customer->last_name,
                    'email' => $customer->email,
                ],
                'reward_calculation' => $rewardCalculation,
            ]
        ]);
    }

    /**
     * Complete challenge
     */
    public function complete(CustomerChallenge $customerChallenge): JsonResponse
    {
        if ($customerChallenge->status !== 'completed') {
            return response()->json([
                'status' => 'error',
                'message' => 'Challenge completion validation failed',
            ], 400);
        }

        $this->challengeService->completeChallenge($customerChallenge);

        return response()->json([
            'status' => 'success',
            'message' => 'Challenge completed and reward awarded successfully',
            'data' => [
                'customer_challenge' => $customerChallenge->fresh(),
            ]
        ]);
    }

    /**
     * Expire old challenges
     */
    public function expireOld(): JsonResponse
    {
        $expiredCount = $this->challengeService->expireOldChallenges();

        return response()->json([
            'status' => 'success',
            'message' => 'Old challenges expired successfully',
            'data' => [
                'expired_count' => $expiredCount,
            ]
        ]);
    }
} 