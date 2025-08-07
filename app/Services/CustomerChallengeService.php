<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Challenge;
use App\Models\Customer;
use App\Models\CustomerChallenge;
use App\Models\ChallengeEngagementLog;
use Illuminate\Support\Facades\DB;

class CustomerChallengeService
{
    /**
     * Assign a challenge to a customer
     */
    public function assignChallengeToCustomer(Challenge $challenge, Customer $customer): CustomerChallenge
    {
        return CustomerChallenge::create([
                'customer_id' => $customer->id,
                'challenge_id' => $challenge->id,
                'assigned_at' => now(),
                'status' => 'assigned',
                'progress_current' => 0,
            'progress_target' => $this->calculateTarget($challenge),
                'progress_percentage' => 0,
            'expires_at' => $challenge->end_date,
        ]);
    }

    /**
     * Update challenge progress based on customer actions
     */
    public function updateProgress(int $customerId, string $actionType, array $actionData): void
    {
        $activeChallenges = CustomerChallenge::where('customer_id', $customerId)
            ->where('status', 'active')
                ->with(['challenge'])
                ->get();

            foreach ($activeChallenges as $customerChallenge) {
            $this->processActionForChallenge($customerChallenge, $actionType, $actionData);
        }
    }

    /**
     * Track customer engagement with challenges
     */
    public function trackEngagement(int $customerId, int $challengeId, string $eventType, array $eventData = [], string $source = 'api'): void
    {
            ChallengeEngagementLog::create([
            'customer_id' => $customerId,
            'challenge_id' => $challengeId,
                'event_type' => $eventType,
                'event_data' => $eventData,
            'source' => $source,
                'event_timestamp' => now(),
        ]);
    }

    /**
     * Generate weekly challenges
     */
    public function generateWeeklyChallenges(): array
    {
        $challenges = [];

        // Frequency challenge
        $challenges[] = Challenge::create([
            'name' => 'Weekly Order Challenge',
            'description' => 'Place 3 orders this week to earn bonus points',
            'challenge_type' => 'frequency',
            'requirements' => ['order_count' => 3],
            'reward_type' => 'points',
            'reward_value' => 50,
            'start_date' => now(),
            'end_date' => now()->addWeek(),
                    'is_active' => true,
        ]);

        // Variety challenge
        $challenges[] = Challenge::create([
            'name' => 'Menu Explorer',
            'description' => 'Try 5 different menu items this week',
            'challenge_type' => 'variety',
            'requirements' => ['unique_items' => 5],
            'reward_type' => 'discount',
            'reward_value' => 10,
            'start_date' => now(),
            'end_date' => now()->addWeek(),
            'is_active' => true,
            ]);

            return $challenges;
    }

    /**
     * Calculate rewards for a challenge
     */
    public function calculateRewards(Challenge $challenge, Customer $customer): array
    {
        $baseValue = $challenge->reward_value;
        $tierMultiplier = $this->getCustomerTierMultiplier($customer);
        $difficultyMultiplier = $this->getDifficultyMultiplier($challenge);

            return [
            'base_value' => $baseValue,
            'adjusted_value' => $baseValue * $tierMultiplier * $difficultyMultiplier,
            'tier_multiplier' => $tierMultiplier,
                'difficulty_multiplier' => $difficultyMultiplier,
        ];
    }

    /**
     * Complete a challenge
     */
    public function completeChallenge(CustomerChallenge $customerChallenge): void
    {
        $customerChallenge->update([
            'status' => 'completed',
            'completed_at' => now(),
            'reward_claimed' => true,
            'reward_claimed_at' => now(),
        ]);

        // Award the reward
        $this->awardReward($customerChallenge);
    }

    /**
     * Expire old challenges
     */
    public function expireOldChallenges(): int
    {
        return CustomerChallenge::where('status', 'active')
            ->where('expires_at', '<', now())
            ->update(['status' => 'expired']);
    }

    /**
     * Calculate target value for a challenge
     */
    private function calculateTarget(Challenge $challenge): int
    {
        return match ($challenge->challenge_type) {
            'frequency' => $challenge->requirements['order_count'] ?? 1,
            'variety' => $challenge->requirements['unique_items'] ?? 1,
            'spending' => $challenge->requirements['total_spent'] ?? 100,
            'referral' => $challenge->requirements['referral_count'] ?? 1,
            default => 1,
        };
    }

    /**
     * Process an action for a specific challenge
     */
    private function processActionForChallenge(CustomerChallenge $customerChallenge, string $actionType, array $actionData): void
    {
        $challenge = $customerChallenge->challenge;
        $progress = $customerChallenge->progress_current;

        switch ($actionType) {
            case 'order_placed':
                if ($challenge->challenge_type === 'frequency') {
                    $progress++;
                } elseif ($challenge->challenge_type === 'spending') {
                    $progress += $actionData['order_total'] ?? 0;
                }
                break;

            case 'item_ordered':
                if ($challenge->challenge_type === 'variety') {
                    $uniqueItems = $this->getUniqueItemsForCustomer($customerChallenge->customer_id);
                    $progress = count($uniqueItems);
                }
                break;
        }

        $percentage = min(100, ($progress / $customerChallenge->progress_target) * 100);
        $status = $percentage >= 100 ? 'completed' : 'active';

        $customerChallenge->update([
            'progress_current' => $progress,
            'progress_percentage' => $percentage,
            'status' => $status,
            'completed_at' => $status === 'completed' ? now() : null,
        ]);
    }

    /**
     * Get customer tier multiplier
     */
    private function getCustomerTierMultiplier(Customer $customer): float
    {
        // Simple tier system based on total spent
        return match (true) {
            $customer->total_spent >= 1000 => 1.5,
            $customer->total_spent >= 500 => 1.2,
            default => 1.0,
        };
    }

    /**
     * Get difficulty multiplier for challenge
     */
    private function getDifficultyMultiplier(Challenge $challenge): float
    {
        $target = $this->calculateTarget($challenge);
        
        return match (true) {
            $target >= 10 => 2.0,
            $target >= 5 => 1.5,
            $target >= 3 => 1.2,
            default => 1.0,
        };
    }

    /**
     * Award reward to customer
     */
    private function awardReward(CustomerChallenge $customerChallenge): void
    {
        $challenge = $customerChallenge->challenge;
        $customer = $customerChallenge->customer;

        switch ($challenge->reward_type) {
            case 'points':
                // Add loyalty points
                $customer->increment('total_spent', $challenge->reward_value);
                break;

            case 'discount':
                // Create discount coupon
                // Implementation would depend on discount system
                break;

            case 'free_item':
                // Add free item to customer account
                // Implementation would depend on inventory system
                break;
        }
    }

    /**
     * Get unique items ordered by customer
     */
    private function getUniqueItemsForCustomer(int $customerId): array
    {
        // This would need to be implemented based on your order/order_items structure
        // For now, returning empty array
        return [];
    }
}