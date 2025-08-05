<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\StampCard;
use App\Models\Customer;
use App\Models\LoyaltyProgram;
use App\Services\StampCardService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

class StampCardController extends Controller
{
    private StampCardService $stampCardService;

    public function __construct(StampCardService $stampCardService)
    {
        $this->stampCardService = $stampCardService;
    }

    /**
     * Get customer's stamp cards
     */
    public function index(Request $request): JsonResponse
    {
        $customer = Auth::user()->customer;
        
        if (!$customer) {
            return response()->json(['message' => 'Customer not found'], 404);
        }

        $cards = StampCard::where('customer_id', $customer->id)
            ->with(['loyaltyProgram', 'stampHistory' => function ($query) {
                $query->latest()->limit(5);
            }])
            ->orderBy('is_completed', 'asc')
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $cards->map(function ($card) {
                return [
                    'id' => $card->id,
                    'card_type' => $card->card_type,
                    'card_type_name' => StampCard::getCardTypes()[$card->card_type] ?? $card->card_type,
                    'stamps_earned' => $card->stamps_earned,
                    'stamps_required' => $card->stamps_required,
                    'progress_percentage' => $card->getProgressPercentage(),
                    'remaining_stamps' => $card->getRemainingStamps(),
                    'is_completed' => $card->is_completed,
                    'is_active' => $card->is_active,
                    'completed_at' => $card->completed_at,
                    'reward_description' => $card->reward_description,
                    'reward_value' => $card->reward_value,
                    'loyalty_program' => [
                        'id' => $card->loyaltyProgram->id,
                        'name' => $card->loyaltyProgram->name,
                    ],
                    'recent_activity' => $card->stampHistory->map(function ($history) {
                        return [
                            'id' => $history->id,
                            'action_type' => $history->action_type,
                            'action_type_name' => StampHistory::getActionTypes()[$history->action_type] ?? $history->action_type,
                            'stamps_added' => $history->stamps_added,
                            'description' => $history->description,
                            'created_at' => $history->created_at,
                        ];
                    }),
                    'created_at' => $card->created_at,
                    'updated_at' => $card->updated_at,
                ];
            }),
        ]);
    }

    /**
     * Get a specific stamp card
     */
    public function show(Request $request, StampCard $stampCard): JsonResponse
    {
        $customer = Auth::user()->customer;
        
        if (!$customer || $stampCard->customer_id !== $customer->id) {
            return response()->json(['message' => 'Stamp card not found'], 404);
        }

        $stampCard->load(['loyaltyProgram', 'stampHistory' => function ($query) {
            $query->latest();
        }]);

        return response()->json([
            'success' => true,
            'data' => [
                'id' => $stampCard->id,
                'card_type' => $stampCard->card_type,
                'card_type_name' => StampCard::getCardTypes()[$stampCard->card_type] ?? $stampCard->card_type,
                'stamps_earned' => $stampCard->stamps_earned,
                'stamps_required' => $stampCard->stamps_required,
                'progress_percentage' => $stampCard->getProgressPercentage(),
                'remaining_stamps' => $stampCard->getRemainingStamps(),
                'is_completed' => $stampCard->is_completed,
                'is_active' => $stampCard->is_active,
                'completed_at' => $stampCard->completed_at,
                'reward_description' => $stampCard->reward_description,
                'reward_value' => $stampCard->reward_value,
                'loyalty_program' => [
                    'id' => $stampCard->loyaltyProgram->id,
                    'name' => $stampCard->loyaltyProgram->name,
                ],
                'history' => $stampCard->stampHistory->map(function ($history) {
                    return [
                        'id' => $history->id,
                        'action_type' => $history->action_type,
                        'action_type_name' => StampHistory::getActionTypes()[$history->action_type] ?? $history->action_type,
                        'stamps_added' => $history->stamps_added,
                        'stamps_before' => $history->stamps_before,
                        'stamps_after' => $history->stamps_after,
                        'description' => $history->description,
                        'metadata' => $history->metadata,
                        'created_at' => $history->created_at,
                    ];
                }),
                'created_at' => $stampCard->created_at,
                'updated_at' => $stampCard->updated_at,
            ],
        ]);
    }

    /**
     * Create a new stamp card
     */
    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'loyalty_program_id' => 'required|exists:loyalty_programs,id',
            'card_type' => ['required', Rule::in(array_keys(StampCard::getCardTypes()))],
            'stamps_required' => 'integer|min:1|max:50',
            'reward_description' => 'string|max:255',
            'reward_value' => 'numeric|min:0|max:1000',
        ]);

        $customer = Auth::user()->customer;
        
        if (!$customer) {
            return response()->json(['message' => 'Customer not found'], 404);
        }

        $loyaltyProgram = LoyaltyProgram::findOrFail($request->loyalty_program_id);

        // Check if customer already has an active card of this type
        $existingCard = StampCard::where('customer_id', $customer->id)
            ->where('loyalty_program_id', $loyaltyProgram->id)
            ->where('card_type', $request->card_type)
            ->where('is_active', true)
            ->where('is_completed', false)
            ->first();

        if ($existingCard) {
            return response()->json([
                'message' => 'Customer already has an active stamp card of this type',
                'existing_card' => $existingCard->id
            ], 409);
        }

        $stampCard = $this->stampCardService->createStampCard(
            $customer,
            $loyaltyProgram,
            $request->card_type,
            $request->stamps_required ?? 10
        );

        return response()->json([
            'success' => true,
            'message' => 'Stamp card created successfully',
            'data' => [
                'id' => $stampCard->id,
                'card_type' => $stampCard->card_type,
                'card_type_name' => StampCard::getCardTypes()[$stampCard->card_type] ?? $stampCard->card_type,
                'stamps_earned' => $stampCard->stamps_earned,
                'stamps_required' => $stampCard->stamps_required,
                'reward_description' => $stampCard->reward_description,
                'reward_value' => $stampCard->reward_value,
            ],
        ], 201);
    }

    /**
     * Get available card types
     */
    public function getCardTypes(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => StampCard::getCardTypes(),
        ]);
    }

    /**
     * Get stamp card statistics for customer
     */
    public function statistics(Request $request): JsonResponse
    {
        $customer = Auth::user()->customer;
        
        if (!$customer) {
            return response()->json(['message' => 'Customer not found'], 404);
        }

        $cards = StampCard::where('customer_id', $customer->id);

        $statistics = [
            'total_cards' => $cards->count(),
            'active_cards' => $cards->where('is_active', true)->where('is_completed', false)->count(),
            'completed_cards' => $cards->where('is_completed', true)->count(),
            'total_stamps_earned' => $cards->sum('stamps_earned'),
            'total_rewards_earned' => $cards->where('is_completed', true)->sum('reward_value'),
            'cards_by_type' => $cards->get()->groupBy('card_type')->map(function ($cards) {
                return [
                    'count' => $cards->count(),
                    'completed' => $cards->where('is_completed', true)->count(),
                    'active' => $cards->where('is_active', true)->where('is_completed', false)->count(),
                ];
            }),
        ];

        return response()->json([
            'success' => true,
            'data' => $statistics,
        ]);
    }
} 