<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Challenge;
use App\Models\Customer;
use App\Models\CustomerChallenge;
use App\Models\ChallengeEngagementLog;
use App\Services\NotificationService;
use App\Services\LoyaltyService;
use App\Services\SecurityLoggingService;
use Illuminate\Support\Facades\DB;

class CustomerChallengeService
{
    public function __construct(
        private NotificationService $notificationService,
        private LoyaltyService $loyaltyService,
        private SecurityLoggingService $securityLoggingService
    ) {}

    /**
     * Assign a challenge to a customer
     */
    public function assignChallengeToCustomer(Challenge $challenge, Customer $customer): ?CustomerChallenge
    {
        // Check if challenge is active
        if (!$challenge->is_active) {
            return null;
        }

        $customerChallenge = CustomerChallenge::create([
            'customer_id' => $customer->id,
            'challenge_id' => $challenge->id,
            'assigned_at' => now(),
            'status' => 'active', // Start as active so it can be processed immediately
            'progress_current' => 0,
            'progress_target' => $this->calculateTarget($challenge),
            'progress_percentage' => 0,
            'expires_at' => $challenge->end_date,
        ]);

        // Send notification
        $this->notificationService->createChallengeNotification(
            $customer,
            'challenge_assigned',
            'New Challenge Available!',
            "A new challenge '{$challenge->name}' has been assigned to you.",
            ['challenge_id' => $challenge->id, 'challenge_name' => $challenge->name],
            'challenge'
        );

        return $customerChallenge;
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
     * Create a new customer challenge
     */
    public function createCustomerChallenge(array $challengeData): Challenge
    {
        // Validate required fields
        $requiredFields = ['name', 'description', 'challenge_type', 'requirements', 'reward_type', 'reward_value', 'start_date', 'end_date'];
        foreach ($requiredFields as $field) {
            if (!isset($challengeData[$field])) {
                throw new \InvalidArgumentException("Missing required field: {$field}");
            }
        }

        // Validate date range
        if ($challengeData['start_date'] >= $challengeData['end_date']) {
            throw new \InvalidArgumentException('Start date must be before end date');
        }

        $challenge = Challenge::create($challengeData);

        // Auto-assign to eligible customers if requested
        if (isset($challengeData['auto_assign']) && $challengeData['auto_assign']) {
            $this->autoAssignChallengeToCustomers($challenge);
        }

        return $challenge;
    }

    /**
     * Auto-assign challenge to eligible customers
     */
    private function autoAssignChallengeToCustomers(Challenge $challenge): void
    {
        $eligibleCustomers = Customer::where('status', 'active')->get();
        
        foreach ($eligibleCustomers as $customer) {
            $this->assignChallengeToCustomer($challenge, $customer);
            
            // Send notification
            $this->notificationService->createChallengeNotification(
                $customer,
                'challenge_assigned',
                'New Challenge Available!',
                "A new challenge '{$challenge->name}' has been assigned to you.",
                ['challenge_id' => $challenge->id, 'challenge_name' => $challenge->name],
                'challenge'
            );
        }
    }

    /**
     * Update challenge progress for a specific customer
     */
    public function updateChallengeProgress(Customer $customer, string $actionType, array $actionData): void
    {
        $this->updateProgress($customer->id, $actionType, $actionData);
    }

    /**
     * Validate challenge completion
     */
    public function validateChallengeCompletion(CustomerChallenge $customerChallenge): bool
    {
        return $customerChallenge->progress_percentage >= 100 && 
               $customerChallenge->status === 'completed' && 
               !$customerChallenge->reward_claimed;
    }

    /**
     * Complete challenge reward
     */
    public function completeChallengeReward(CustomerChallenge $customerChallenge): bool
    {
        if (!$this->validateChallengeCompletion($customerChallenge)) {
            return false;
        }

        // Ensure relationships are loaded
        $customerChallenge->load(['challenge', 'customer']);

        $customerChallenge->update([
            'reward_claimed' => true,
            'reward_claimed_at' => now(),
            'status' => 'rewarded'
        ]);

        $this->awardReward($customerChallenge);
        
        // Send notification
        $this->notificationService->createChallengeNotification(
            $customerChallenge->customer,
            'challenge_completed',
            'Challenge Completed!',
            'Congratulations! You have completed a challenge and earned your reward.',
            ['challenge_id' => $customerChallenge->challenge_id, 'reward_value' => $customerChallenge->challenge->reward_value],
            'challenge'
        );
        
        return true;
    }

    /**
     * Track challenge engagement
     */
    public function trackChallengeEngagement(Customer $customer, Challenge $challenge, string $eventType, array $eventData = [], string $source = 'api'): void
    {
        $this->trackEngagement($customer->id, $challenge->id, $eventType, $eventData, $source);
    }

    /**
     * Calculate challenge rewards
     */
    public function calculateChallengeRewards(Challenge $challenge, Customer $customer): array
    {
        $baseValue = $challenge->reward_value;
        $tierMultiplier = $this->getCustomerTierMultiplier($customer);
        $difficultyMultiplier = $this->getDifficultyMultiplier($challenge);
        
        // Calculate adjusted value with a cap of 150% of base value
        $adjustedValue = min(
            $baseValue * $tierMultiplier * $difficultyMultiplier,
            $baseValue * 1.5
        );
        
        return [
            'base_value' => $baseValue,
            'adjusted_value' => $adjustedValue,
            'tier_multiplier' => $tierMultiplier,
            'difficulty_multiplier' => $difficultyMultiplier,
        ];
    }

    /**
     * Get challenge leaderboard
     */
    public function getChallengeLeaderboard(Challenge $challenge, int $limit = 10): array
    {
        $customerChallenges = CustomerChallenge::where('challenge_id', $challenge->id)
            ->where('status', 'completed')
            ->orderBy('completed_at', 'asc')
            ->limit($limit)
            ->with(['customer'])
            ->get();

        $rank = 1;
        return $customerChallenges->map(function ($customerChallenge) use (&$rank) {
            return [
                'rank' => $rank++,
                'customer' => $customerChallenge->customer,
                'progress' => [
                    'current' => $customerChallenge->progress_current,
                    'target' => $customerChallenge->progress_target,
                    'percentage' => $customerChallenge->progress_percentage,
                ],
                'completed_at' => $customerChallenge->completed_at,
            ];
        })->toArray();
    }

    /**
     * Calculate rewards for a challenge
     */
    public function calculateRewards(Challenge $challenge, Customer $customer): array
    {
        $baseValue = $challenge->reward_value;
        $tierMultiplier = $this->getCustomerTierMultiplier($customer);
        $difficultyMultiplier = $this->getDifficultyMultiplier($challenge);

        // Calculate adjusted value with a cap of 150% of base value
        $adjustedValue = min(
            $baseValue * $tierMultiplier * $difficultyMultiplier,
            $baseValue * 1.5
        );

        return [
            'base_value' => $baseValue,
            'adjusted_value' => $adjustedValue,
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
     * Check challenge progress for a customer
     */
    public function checkChallengeProgress(Customer $customer): array
    {
        return CustomerChallenge::where('customer_id', $customer->id)
            ->where('status', 'active')
            ->with(['challenge'])
            ->get()
            ->map(function ($customerChallenge) {
                return [
                    'challenge_name' => $customerChallenge->challenge->name,
                    'progress_percentage' => $customerChallenge->progress_percentage,
                    'days_remaining' => $customerChallenge->expires_at ? now()->diffInDays($customerChallenge->expires_at, false) : null,
                    'status' => $customerChallenge->status,
                ];
            })
            ->toArray();
    }

    /**
     * Get active customer challenges
     */
    public function getActiveCustomerChallenges(Customer $customer): array
    {
        return CustomerChallenge::where('customer_id', $customer->id)
            ->where('status', 'active')
            ->with(['challenge'])
            ->get()
            ->map(function ($customerChallenge) {
                return [
                    'challenge' => $customerChallenge->challenge,
                    'progress' => [
                        'current' => $customerChallenge->progress_current,
                        'target' => $customerChallenge->progress_target,
                        'percentage' => $customerChallenge->progress_percentage,
                    ],
                    'timing' => [
                        'started_at' => $customerChallenge->started_at,
                        'expires_at' => $customerChallenge->expires_at,
                        'days_remaining' => $customerChallenge->expires_at ? now()->diffInDays($customerChallenge->expires_at, false) : null,
                    ],
                ];
            })
            ->toArray();
    }

    /**
     * Expire old challenges
     */
    public function expireOldChallenges(): int
    {
        $expiredChallenges = CustomerChallenge::where('status', 'active')
            ->where('expires_at', '<', now())
            ->get();

        foreach ($expiredChallenges as $customerChallenge) {
            $customerChallenge->update(['status' => 'expired']);
            
            // Send notification about expired challenge
            $this->notificationService->createChallengeNotification(
                $customerChallenge->customer,
                'challenge_expired',
                'Challenge Expired',
                "The challenge '{$customerChallenge->challenge->name}' has expired.",
                ['challenge_id' => $customerChallenge->challenge_id, 'challenge_name' => $customerChallenge->challenge->name],
                'challenge'
            );
        }

        return $expiredChallenges->count();
    }

    /**
     * Calculate target value for a challenge
     */
    private function calculateTarget(Challenge $challenge): int
    {
        return match ($challenge->challenge_type) {
            'frequency' => (int) ($challenge->requirements['order_count'] ?? 1),
            'variety' => (int) ($challenge->requirements['unique_items'] ?? 1),
            'spending' => (int) ($challenge->requirements['total_spent'] ?? 100),
            'value' => (int) ($challenge->requirements['total_amount'] ?? 100),
            'referral' => (int) ($challenge->requirements['referral_count'] ?? 1),
            default => 1,
        };
    }

    /**
     * Process an action for a specific challenge
     */
    private function processActionForChallenge(CustomerChallenge $customerChallenge, string $actionType, array $actionData): void
    {
        // Ensure the challenge relationship is loaded
        $customerChallenge->load('challenge');
        $challenge = $customerChallenge->challenge;
        $progress = (float) $customerChallenge->progress_current;
        $previousProgress = $progress;

        switch ($actionType) {
            case 'order_placed':
                if ($challenge->challenge_type === 'frequency') {
                    $progress++;
                } elseif ($challenge->challenge_type === 'spending' || $challenge->challenge_type === 'value') {
                    $progress += (float) ($actionData['order_total'] ?? 0);
                } elseif ($challenge->challenge_type === 'variety') {
                    // For variety challenges, accumulate unique items across all orders
                    $currentUniqueItems = [];
                    if (isset($actionData['menu_items'])) {
                        foreach ($actionData['menu_items'] as $item) {
                            $currentUniqueItems[$item['id']] = $item['name'];
                        }
                    }
                    // Add to existing progress (this is a simplified approach - in production you'd track all unique items)
                    $progress += (float) count($currentUniqueItems);
                }
                break;

            case 'item_ordered':
                if ($challenge->challenge_type === 'variety') {
                    $uniqueItems = $this->getUniqueItemsForCustomer($customerChallenge->customer_id);
                    $progress = (float) count($uniqueItems);
                }
                break;
        }

        $percentage = min(100, ($progress / (float) $customerChallenge->progress_target) * 100);
        $status = $percentage >= 100 ? 'completed' : 'active';

        $customerChallenge->update([
            'progress_current' => $progress,
            'progress_percentage' => $percentage,
            'status' => $status,
            'started_at' => $status === 'active' && $customerChallenge->started_at === null ? now() : $customerChallenge->started_at,
            'completed_at' => $status === 'completed' ? now() : null,
        ]);

        // Refresh the model to get updated values
        $customerChallenge->refresh();

        // Create progress log
        $this->createProgressLog($customerChallenge, $actionType, $actionData, $previousProgress, $progress);

        // Check for milestone notifications
        $this->checkMilestoneNotifications($customerChallenge, $previousProgress, $progress);

        // Auto-claim reward if challenge is completed and reward not already claimed
        if ($status === 'completed' && !$customerChallenge->reward_claimed) {
            // Ensure the challenge relationship is still loaded after refresh
            $customerChallenge->load('challenge');
            $this->completeChallengeReward($customerChallenge);
        }
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
        // Ensure relationships are loaded
        $customerChallenge->load(['challenge', 'customer']);
        
        $challenge = $customerChallenge->challenge;
        $customer = $customerChallenge->customer;

        switch ($challenge->reward_type) {
            case 'points':
                // Award loyalty points through the loyalty service
                $this->loyaltyService->awardPoints(
                    $customer, 
                    (float) $challenge->reward_value, 
                    'challenge_completion',
                    ['challenge_id' => $challenge->id]
                );
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

    /**
     * Check for milestone notifications
     */
    private function checkMilestoneNotifications(CustomerChallenge $customerChallenge, float $previousProgress, float $currentProgress): void
    {
        $target = (float) $customerChallenge->progress_target;
        $milestones = [25, 50, 75];
        
        foreach ($milestones as $milestone) {
            $milestoneValue = ($target * $milestone) / 100;
            
            if ($previousProgress < $milestoneValue && $currentProgress >= $milestoneValue) {
                $this->notificationService->createChallengeNotification(
                    $customerChallenge->customer,
                    'challenge_milestone',
                    'Challenge Progress!',
                    "Great progress! You've reached the {$milestone}% milestone.",
                    [
                        'challenge_id' => $customerChallenge->challenge_id,
                        'milestone' => $milestone,
                        'progress' => $currentProgress,
                        'target' => $target
                    ],
                    'challenge'
                );
                break; // Only send one notification per update
            }
        }
    }

    /**
     * Create progress log entry
     */
    private function createProgressLog(CustomerChallenge $customerChallenge, string $actionType, array $actionData, float $previousProgress, float $currentProgress): void
    {
        $target = (float) $customerChallenge->progress_target;
        $milestones = [25, 50, 75];
        $milestoneReached = false;
        
        foreach ($milestones as $milestone) {
            $milestoneValue = ($target * $milestone) / 100;
            
            if ($previousProgress < $milestoneValue && $currentProgress >= $milestoneValue) {
                $milestoneReached = true;
                break;
            }
        }

        \App\Models\ChallengeProgressLog::create([
            'customer_challenge_id' => $customerChallenge->id,
            'customer_id' => $customerChallenge->customer_id,
            'challenge_id' => $customerChallenge->challenge_id,
            'action_type' => $actionType,
            'action_data' => $actionData,
            'progress_before' => $previousProgress,
            'progress_after' => $currentProgress,
            'progress_increment' => $currentProgress - $previousProgress,
            'description' => "Progress updated from {$previousProgress} to {$currentProgress} via {$actionType}",
            'milestone_reached' => $milestoneReached,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}