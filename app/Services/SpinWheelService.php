<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Customer;
use App\Models\SpinWheel;
use App\Models\SpinWheelPrize;
use App\Models\CustomerSpin;
use App\Models\SpinResult;
use App\Models\CustomerLoyaltyPoint;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class SpinWheelService
{
    /**
     * Validate if a customer can spin the wheel.
     */
    public function validateSpinWheel(Customer $customer): bool
    {
        // Check if there's an active spin wheel
        $activeSpinWheel = SpinWheel::currentlyActive()->first();
        
        if (!$activeSpinWheel) {
            return false;
        }

        // Get or create customer spin record
        $customerSpin = $this->getOrCreateCustomerSpin($customer, $activeSpinWheel);
        
        // Check if customer can spin today
        if (!$customerSpin->can_spin_today) {
            return false;
        }

        // Check if customer has available spins
        if (!$customerSpin->has_available_spins) {
            return false;
        }

        return true;
    }

    /**
     * Process spin wheel result for a customer.
     */
    public function processSpinWheelResult(Customer $customer): ?SpinResult
    {
        // Validate customer can spin
        if (!$this->validateSpinWheel($customer)) {
            return null;
        }

        $activeSpinWheel = SpinWheel::currentlyActive()->first();
        $customerSpin = $this->getOrCreateCustomerSpin($customer, $activeSpinWheel);

        return DB::transaction(function () use ($customer, $activeSpinWheel, $customerSpin) {
            // Determine spin type (free or paid)
            $spinType = $this->determineSpinType($customerSpin);
            
            if (!$spinType) {
                return null;
            }

            // Use the appropriate spin
            if ($spinType === 'free') {
                $customerSpin->useFreeSpin();
            } else {
                $customerSpin->usePaidSpin();
            }

            // Get customer's tier for probability calculation
            $tierLevel = $this->getCustomerTierLevel($customer);
            
            // Select prize based on weighted probabilities
            $selectedPrize = $this->selectPrize($activeSpinWheel, $tierLevel);
            
            if (!$selectedPrize) {
                return null;
            }

            // Create spin result
            $spinResult = $this->createSpinResult($customer, $activeSpinWheel, $selectedPrize, $spinType);
            
            // Apply prize to customer
            $spinResult->applyPrize();
            
            // Increment prize redemption count
            $selectedPrize->incrementRedemption();

            return $spinResult;
        });
    }

    /**
     * Buy spins with loyalty points.
     */
    public function buySpins(Customer $customer, int $quantity): bool
    {
        $activeSpinWheel = SpinWheel::currentlyActive()->first();
        
        if (!$activeSpinWheel) {
            return false;
        }

        $customerSpin = $this->getOrCreateCustomerSpin($customer, $activeSpinWheel);
        
        return $customerSpin->buySpins($quantity);
    }

    /**
     * Get customer's spin status and available spins.
     */
    public function getCustomerSpinStatus(Customer $customer): array
    {
        $activeSpinWheel = SpinWheel::currentlyActive()->first();
        
        if (!$activeSpinWheel) {
            return [
                'can_spin' => false,
                'reason' => 'No active spin wheel',
                'available_spins' => 0,
                'daily_spins_used' => 0,
                'max_daily_spins' => 0,
            ];
        }

        $customerSpin = $this->getOrCreateCustomerSpin($customer, $activeSpinWheel);
        
        return [
            'can_spin' => $this->validateSpinWheel($customer),
            'available_spins' => $customerSpin->total_available_spins,
            'free_spins_remaining' => $customerSpin->free_spins_remaining,
            'paid_spins_remaining' => $customerSpin->paid_spins_remaining,
            'daily_spins_used' => $customerSpin->daily_spins_used,
            'max_daily_spins' => $activeSpinWheel->max_daily_spins,
            'spin_cost_points' => $activeSpinWheel->spin_cost_points,
            'total_spins_used' => $customerSpin->total_spins_used,
        ];
    }

    /**
     * Get or create customer spin record.
     */
    private function getOrCreateCustomerSpin(Customer $customer, SpinWheel $spinWheel): CustomerSpin
    {
        $customerSpin = CustomerSpin::where('customer_id', $customer->id)
            ->where('spin_wheel_id', $spinWheel->id)
            ->first();

        if (!$customerSpin) {
            $customerSpin = CustomerSpin::create([
                'customer_id' => $customer->id,
                'spin_wheel_id' => $spinWheel->id,
                'free_spins_remaining' => 0,
                'paid_spins_remaining' => 0,
                'total_spins_used' => 0,
                'daily_spins_used' => 0,
                'is_active' => true,
            ]);
        }

        // Add daily free spins if needed
        $this->addDailyFreeSpinsIfNeeded($customerSpin);

        return $customerSpin;
    }

    /**
     * Add daily free spins if customer hasn't received them today.
     */
    private function addDailyFreeSpinsIfNeeded(CustomerSpin $customerSpin): void
    {
        $today = Carbon::today();
        
        if (!$customerSpin->last_spin_date || $customerSpin->last_spin_date->lt($today)) {
            $customerSpin->addDailyFreeSpins();
        }
    }

    /**
     * Determine spin type (free or paid).
     */
    private function determineSpinType(CustomerSpin $customerSpin): ?string
    {
        if ($customerSpin->free_spins_remaining > 0) {
            return 'free';
        }
        
        if ($customerSpin->paid_spins_remaining > 0) {
            return 'paid';
        }
        
        return null;
    }

    /**
     * Get customer's tier level.
     */
    private function getCustomerTierLevel(Customer $customer): int
    {
        $loyaltyPoints = $customer->loyaltyPoints()->first();
        
        if (!$loyaltyPoints || !$loyaltyPoints->loyaltyTier) {
            return 1; // Default tier
        }
        
        return $loyaltyPoints->loyaltyTier->tier_level;
    }

    /**
     * Select prize based on weighted probabilities.
     */
    private function selectPrize(SpinWheel $spinWheel, int $tierLevel): ?SpinWheelPrize
    {
        $availablePrizes = $spinWheel->activePrizes()
            ->available()
            ->where(function ($query) use ($tierLevel) {
                $query->whereNull('tier_restrictions')
                      ->orWhereJsonContains('tier_restrictions', $tierLevel);
            })
            ->get();

        if ($availablePrizes->isEmpty()) {
            return null;
        }

        // Calculate adjusted probabilities for the tier
        $prizesWithProbabilities = [];
        $totalProbability = 0;

        foreach ($availablePrizes as $prize) {
            $adjustedProbability = $prize->getAdjustedProbabilityForTier($tierLevel);
            $prizesWithProbabilities[] = [
                'prize' => $prize,
                'probability' => $adjustedProbability,
            ];
            $totalProbability += $adjustedProbability;
        }

        // Normalize probabilities if total > 1
        if ($totalProbability > 1) {
            foreach ($prizesWithProbabilities as &$item) {
                $item['probability'] = $item['probability'] / $totalProbability;
            }
        }

        // Generate random number and select prize
        $random = mt_rand() / mt_getrandmax();
        $cumulativeProbability = 0;

        foreach ($prizesWithProbabilities as $item) {
            $cumulativeProbability += $item['probability'];
            if ($random <= $cumulativeProbability) {
                return $item['prize'];
            }
        }

        // Fallback to first prize if no selection made
        return $availablePrizes->first();
    }

    /**
     * Create spin result record.
     */
    private function createSpinResult(Customer $customer, SpinWheel $spinWheel, SpinWheelPrize $prize, string $spinType): SpinResult
    {
        // Calculate expiration date (default 30 days)
        $expiresAt = Carbon::now()->addDays(30);
        
        // Override with prize-specific expiration if set
        if (isset($prize->conditions['expiration_days'])) {
            $expiresAt = Carbon::now()->addDays($prize->conditions['expiration_days']);
        }

        return SpinResult::create([
            'customer_id' => $customer->id,
            'spin_wheel_id' => $spinWheel->id,
            'spin_wheel_prize_id' => $prize->id,
            'spin_type' => $spinType,
            'prize_value' => $prize->value,
            'prize_type' => $prize->type,
            'prize_name' => $prize->name,
            'prize_description' => $prize->description,
            'prize_details' => $prize->conditions ?? [],
            'is_redeemed' => false,
            'expires_at' => $expiresAt,
        ]);
    }

    /**
     * Get customer's redeemable prizes.
     */
    public function getCustomerRedeemablePrizes(Customer $customer): array
    {
        $redeemablePrizes = $customer->spinResults()
            ->redeemable()
            ->with(['prize', 'spinWheel'])
            ->orderBy('created_at', 'desc')
            ->get();

        return $redeemablePrizes->map(function ($result) {
            return [
                'id' => $result->id,
                'prize_name' => $result->prize_name,
                'prize_type' => $result->prize_type,
                'prize_value' => $result->prize_value,
                'display_value' => $result->display_value,
                'expires_at' => $result->expires_at?->toISOString(),
                'created_at' => $result->created_at->toISOString(),
            ];
        })->toArray();
    }

    /**
     * Redeem a spin result.
     */
    public function redeemSpinResult(Customer $customer, int $spinResultId, ?int $orderId = null): bool
    {
        $spinResult = $customer->spinResults()
            ->where('id', $spinResultId)
            ->redeemable()
            ->first();

        if (!$spinResult) {
            return false;
        }

        return $spinResult->redeem($orderId);
    }
} 