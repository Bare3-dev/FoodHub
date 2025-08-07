<?php

namespace App\Services;

use App\Models\Order;
use App\Models\StampCard;
use App\Models\StampHistory;
use App\Models\Customer;
use App\Models\LoyaltyProgram;
use App\Models\MenuCategory;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class StampCardService
{
    /**
     * Check if a stamp card is ready for reward redemption
     */
    public function checkStampCardCompletion(StampCard $card): bool
    {
        return $card->stamps_earned >= $card->stamps_required;
    }

    /**
     * Add stamp(s) to eligible cards when order is completed
     */
    public function addStampToCard(Order $order): void
    {
        DB::transaction(function () use ($order) {
            // Get customer's active stamp cards
            $activeCards = StampCard::where('customer_id', $order->customer_id)
                ->where('is_active', true)
                ->where('is_completed', false)
                ->get();

            if ($activeCards->isEmpty()) {
                return;
            }

            foreach ($activeCards as $card) {
                $this->processStampCardForOrder($card, $order);
            }
        });
    }

    /**
     * Process a specific stamp card for an order
     */
    private function processStampCardForOrder(StampCard $card, Order $order): void
    {
        // Check if order qualifies for this card type
        if (!$this->orderQualifiesForCardType($order, $card->card_type)) {
            return;
        }

        // Calculate stamps to add based on order
        $stampsToAdd = $this->calculateStampsForOrder($order, $card->card_type);

        if ($stampsToAdd <= 0) {
            return;
        }

        // Record stamps before update
        $stampsBefore = $card->stamps_earned;

        // Update stamp card
        $card->update([
            'stamps_earned' => $card->stamps_earned + $stampsToAdd
        ]);

        $stampsAfter = $card->stamps_earned;

        // Create stamp history record
        StampHistory::create([
            'stamp_card_id' => $card->id,
            'order_id' => $order->id,
            'customer_id' => $order->customer_id,
            'stamps_added' => $stampsToAdd,
            'stamps_before' => $stampsBefore,
            'stamps_after' => $stampsAfter,
            'action_type' => StampHistory::ACTION_STAMP_EARNED,
            'description' => "Earned {$stampsToAdd} stamp(s) from order #{$order->order_number}",
            'metadata' => [
                'order_number' => $order->order_number,
                'order_total' => $order->total_amount,
                'card_type' => $card->card_type,
                'stamps_required' => $card->stamps_required,
                'progress_percentage' => $card->getProgressPercentage(),
            ]
        ]);

        // Check if card is now completed
        if ($this->checkStampCardCompletion($card)) {
            $this->completeStampCard($card, $order);
        }
    }

    /**
     * Check if order qualifies for a specific card type
     */
    private function orderQualifiesForCardType(Order $order, string $cardType): bool
    {
        switch ($cardType) {
            case StampCard::TYPE_GENERAL:
                return true; // All orders qualify

            case StampCard::TYPE_BEVERAGES:
                return $this->orderContainsBeverages($order);

            case StampCard::TYPE_DESSERTS:
                return $this->orderContainsDesserts($order);

            case StampCard::TYPE_MAINS:
                return $this->orderContainsMains($order);

            case StampCard::TYPE_HEALTHY:
                return $this->orderContainsHealthyItems($order);

            default:
                return false;
        }
    }

    /**
     * Calculate stamps to add based on order and card type
     */
    private function calculateStampsForOrder(Order $order, string $cardType): int
    {
        // Base calculation: 1 stamp per $10 spent on qualifying items
        $qualifyingAmount = $this->getQualifyingOrderAmount($order, $cardType);
        
        if ($qualifyingAmount <= 0) {
            return 0;
        }

        // Calculate stamps based on amount (1 stamp per $10)
        $stamps = (int) floor($qualifyingAmount / 10);

        // Minimum 1 stamp if any qualifying items
        return max(1, $stamps);
    }

    /**
     * Get the qualifying amount for a specific card type
     */
    private function getQualifyingOrderAmount(Order $order, string $cardType): float
    {
        $qualifyingAmount = 0;

        foreach ($order->items as $item) {
            if ($this->itemQualifiesForCardType($item, $cardType)) {
                $qualifyingAmount += $item->total_price;
            }
        }

        return $qualifyingAmount;
    }

    /**
     * Check if an order item qualifies for a specific card type
     */
    private function itemQualifiesForCardType($orderItem, string $cardType): bool
    {
        $menuItem = $orderItem->menuItem;
        if (!$menuItem) {
            return false;
        }

        $category = $menuItem->category;
        if (!$category) {
            return false;
        }

        switch ($cardType) {
            case StampCard::TYPE_BEVERAGES:
                return $this->isBeverageCategory($category);

            case StampCard::TYPE_DESSERTS:
                return $this->isDessertCategory($category);

            case StampCard::TYPE_MAINS:
                return $this->isMainCourseCategory($category);

            case StampCard::TYPE_HEALTHY:
                return $this->isHealthyItem($menuItem);

            default:
                return true;
        }
    }

    /**
     * Check if order contains beverages
     */
    private function orderContainsBeverages(Order $order): bool
    {
        return $order->items->some(function ($item) {
            $category = $item->menuItem?->category;
            return $category && $this->isBeverageCategory($category);
        });
    }

    /**
     * Check if order contains desserts
     */
    private function orderContainsDesserts(Order $order): bool
    {
        return $order->items->some(function ($item) {
            $category = $item->menuItem?->category;
            return $category && $this->isDessertCategory($category);
        });
    }

    /**
     * Check if order contains main courses
     */
    private function orderContainsMains(Order $order): bool
    {
        return $order->items->some(function ($item) {
            $category = $item->menuItem?->category;
            return $category && $this->isMainCourseCategory($category);
        });
    }

    /**
     * Check if order contains healthy items
     */
    private function orderContainsHealthyItems(Order $order): bool
    {
        return $order->items->some(function ($item) {
            $menuItem = $item->menuItem;
            return $menuItem && $this->isHealthyItem($menuItem);
        });
    }

    /**
     * Check if category is beverages
     */
    private function isBeverageCategory($category): bool
    {
        $beverageKeywords = ['beverage', 'drink', 'coffee', 'tea', 'juice', 'soda', 'water'];
        return $this->categoryMatchesKeywords($category, $beverageKeywords);
    }

    /**
     * Check if category is desserts
     */
    private function isDessertCategory($category): bool
    {
        $dessertKeywords = ['dessert', 'sweet', 'cake', 'ice cream', 'pastry'];
        return $this->categoryMatchesKeywords($category, $dessertKeywords);
    }

    /**
     * Check if category is main courses
     */
    private function isMainCourseCategory($category): bool
    {
        $mainKeywords = ['main', 'entree', 'dish', 'meal', 'course'];
        return $this->categoryMatchesKeywords($category, $mainKeywords);
    }

    /**
     * Check if menu item is healthy
     */
    private function isHealthyItem($menuItem): bool
    {
        $dietaryTags = $menuItem->dietary_tags ?? [];
        $healthyKeywords = ['healthy', 'organic', 'low-calorie', 'low-fat', 'vegetarian', 'vegan'];
        
        // Check dietary tags
        foreach ($dietaryTags as $tag) {
            if (in_array(strtolower($tag), $healthyKeywords)) {
                return true;
            }
        }

        // Check if name contains healthy keywords
        $name = strtolower($menuItem->name);
        foreach ($healthyKeywords as $keyword) {
            if (str_contains($name, $keyword)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Check if category matches keywords
     */
    private function categoryMatchesKeywords($category, array $keywords): bool
    {
        $categoryName = strtolower($category->name);
        $categorySlug = strtolower($category->slug);

        foreach ($keywords as $keyword) {
            if (str_contains($categoryName, $keyword) || str_contains($categorySlug, $keyword)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Complete a stamp card and trigger rewards
     */
    private function completeStampCard(StampCard $card, Order $order): void
    {
        DB::transaction(function () use ($card, $order) {
            // Mark card as completed
            $card->update([
                'is_completed' => true,
                'completed_at' => now()
            ]);

            // Create completion history record
            StampHistory::create([
                'stamp_card_id' => $card->id,
                'order_id' => $order->id,
                'customer_id' => $order->customer_id,
                'stamps_added' => 0,
                'stamps_before' => $card->stamps_earned,
                'stamps_after' => $card->stamps_earned,
                'action_type' => StampHistory::ACTION_CARD_COMPLETED,
                'description' => "Stamp card completed! Reward: {$card->reward_description}",
                'metadata' => [
                    'order_number' => $order->order_number,
                    'reward_description' => $card->reward_description,
                    'reward_value' => $card->reward_value,
                    'card_type' => $card->card_type,
                    'completed_at' => now(),
                ]
            ]);

            // Log completion
            Log::info("Stamp card completed", [
                'card_id' => $card->id,
                'customer_id' => $card->customer_id,
                'card_type' => $card->card_type,
                'reward' => $card->reward_description,
                'order_id' => $order->id
            ]);
        });
    }

    /**
     * Create a new stamp card for a customer
     */
    public function createStampCard(Customer $customer, LoyaltyProgram $loyaltyProgram, string $cardType, int $stampsRequired = 10): StampCard
    {
        return StampCard::create([
            'customer_id' => $customer->id,
            'loyalty_program_id' => $loyaltyProgram->id,
            'card_type' => $cardType,
            'stamps_required' => $stampsRequired,
            'stamps_earned' => 0,
            'is_completed' => false,
            'is_active' => true,
            'reward_description' => $this->getDefaultRewardDescription($cardType),
            'reward_value' => $this->getDefaultRewardValue($cardType),
        ]);
    }

    /**
     * Get default reward description for card type
     */
    private function getDefaultRewardDescription(string $cardType): string
    {
        $rewards = [
            StampCard::TYPE_GENERAL => 'Free dessert or beverage',
            StampCard::TYPE_BEVERAGES => 'Free beverage of your choice',
            StampCard::TYPE_DESSERTS => 'Free dessert of your choice',
            StampCard::TYPE_MAINS => 'Free main course up to $15',
            StampCard::TYPE_HEALTHY => 'Free healthy meal option',
        ];

        return $rewards[$cardType] ?? 'Free item of your choice';
    }

    /**
     * Get default reward value for card type
     */
    private function getDefaultRewardValue(string $cardType): float
    {
        $values = [
            StampCard::TYPE_GENERAL => 8.00,
            StampCard::TYPE_BEVERAGES => 5.00,
            StampCard::TYPE_DESSERTS => 7.00,
            StampCard::TYPE_MAINS => 15.00,
            StampCard::TYPE_HEALTHY => 12.00,
        ];

        return $values[$cardType] ?? 10.00;
    }
} 