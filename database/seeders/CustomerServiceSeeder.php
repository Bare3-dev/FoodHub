<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Customer;
use App\Models\Order;
use App\Models\User;
use Illuminate\Database\Seeder;

final class CustomerServiceSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $customers = Customer::all();
        $orders = Order::all();
        $users = User::all();

        if ($customers->isEmpty() || $orders->isEmpty() || $users->isEmpty()) {
            $this->command->warn('Skipping CustomerServiceSeeder: Required data not found.');
            return;
        }

        // Seed customer service interactions
        $this->seedCustomerServiceInteractions($customers, $users);
        
        // Seed customer refunds
        $this->seedCustomerRefunds($customers, $orders, $users);
        
        // Seed customer compensations
        $this->seedCustomerCompensations($customers, $orders, $users);
        
        // Seed customer service activities
        $this->seedCustomerServiceActivities($customers, $users);
        
        // Seed order special requests
        $this->seedOrderSpecialRequests($customers, $orders);

        $this->command->info('Successfully seeded Customer Service data.');
    }

    private function seedCustomerServiceInteractions($customers, $users): void
    {
        $interactionTypes = ['phone_call', 'email', 'chat', 'in_person', 'social_media'];
        $topics = ['order_inquiry', 'complaint', 'refund_request', 'technical_support', 'general_inquiry'];
        $resolutions = ['resolved', 'escalated', 'pending_followup', 'closed'];

        for ($i = 0; $i < 25; $i++) {
            \DB::table('customer_service_interactions')->insert([
                'customer_id' => $customers->random()->id,
                'user_id' => $users->random()->id,
                'interaction_type' => $interactionTypes[array_rand($interactionTypes)],
                'duration_minutes' => rand(5, 45),
                'topic' => $topics[array_rand($topics)],
                'resolution' => $resolutions[array_rand($resolutions)],
                'satisfaction_rating' => rand(1, 5),
                'notes' => $this->getInteractionNotes(),
                'created_at' => now()->subDays(rand(1, 60)),
                'updated_at' => now()->subDays(rand(0, 59)),
            ]);
        }
    }

    private function seedCustomerRefunds($customers, $orders, $users): void
    {
        $refundTypes = ['full', 'partial'];
        $approvalStatuses = ['pending', 'approved', 'rejected'];
        $refundReasons = [
            'Food was cold when delivered',
            'Wrong items in order',
            'Order was extremely late',
            'Food quality was poor',
            'Order was missing items',
            'Delivery driver was rude',
            'Food was not properly cooked',
            'Order was completely wrong'
        ];

        for ($i = 0; $i < 10; $i++) {
            $order = $orders->random();
            $status = $approvalStatuses[array_rand($approvalStatuses)];
            
            \DB::table('customer_refunds')->insert([
                'order_id' => $order->id,
                'customer_id' => $customers->random()->id,
                'user_id' => $users->random()->id,
                'refund_amount' => rand(10, 200),
                'refund_reason' => $refundReasons[array_rand($refundReasons)],
                'refund_type' => $refundTypes[array_rand($refundTypes)],
                'approval_status' => $status,
                'approval_notes' => $status === 'approved' ? 'Refund approved and processed' : null,
                'processed_at' => $status === 'approved' ? now() : null,
                'created_at' => now()->subDays(rand(1, 30)),
                'updated_at' => now()->subDays(rand(0, 29)),
            ]);
        }
    }

    private function seedCustomerCompensations($customers, $orders, $users): void
    {
        $compensationTypes = ['free_meal', 'discount_voucher', 'loyalty_points', 'cash_compensation'];
        $approvalStatuses = ['pending', 'approved', 'rejected'];
        $reasons = [
            'Long delivery time',
            'Food quality issue',
            'Service problem',
            'Order error',
            'Customer inconvenience',
            'Staff behavior issue',
            'Technical problem',
            'System error'
        ];

        for ($i = 0; $i < 8; $i++) {
            $order = $orders->random();
            $status = $approvalStatuses[array_rand($approvalStatuses)];
            
            \DB::table('customer_compensations')->insert([
                'order_id' => $order->id,
                'customer_id' => $customers->random()->id,
                'user_id' => $users->random()->id,
                'compensation_type' => $compensationTypes[array_rand($compensationTypes)],
                'compensation_value' => $this->getCompensationValue(),
                'reason' => $reasons[array_rand($reasons)],
                'approval_status' => $status,
                'approval_notes' => $status === 'approved' ? 'Compensation approved' : null,
                'processed_at' => $status === 'approved' ? now() : null,
                'created_at' => now()->subDays(rand(1, 25)),
                'updated_at' => now()->subDays(rand(0, 24)),
            ]);
        }
    }

    private function seedCustomerServiceActivities($customers, $users): void
    {
        $activityTypes = ['customer_call', 'email_response', 'complaint_resolution', 'refund_processing', 'compensation_handling'];
        $outcomes = ['resolved', 'escalated', 'pending', 'closed'];

        for ($i = 0; $i < 30; $i++) {
            \DB::table('customer_service_activities')->insert([
                'customer_id' => $customers->random()->id,
                'user_id' => $users->random()->id,
                'activity_type' => $activityTypes[array_rand($activityTypes)],
                'description' => $this->getActivityDescription(),
                'duration_minutes' => rand(3, 60),
                'outcome' => $outcomes[array_rand($outcomes)],
                'created_at' => now()->subDays(rand(1, 90)),
                'updated_at' => now()->subDays(rand(0, 89)),
            ]);
        }
    }

    private function seedOrderSpecialRequests($customers, $orders): void
    {
        $requestTypes = ['dietary_restriction', 'allergy_alert', 'cooking_preference', 'packaging_request', 'delivery_instruction'];
        $priorities = ['low', 'medium', 'high', 'urgent'];
        $statuses = ['pending', 'approved', 'rejected', 'completed'];

        for ($i = 0; $i < 12; $i++) {
            $order = $orders->random();
            $status = $statuses[array_rand($statuses)];
            
            \DB::table('order_special_requests')->insert([
                'order_id' => $order->id,
                'customer_id' => $customers->random()->id,
                'request_type' => $requestTypes[array_rand($requestTypes)],
                'description' => $this->getSpecialRequestDescription(),
                'priority' => $priorities[array_rand($priorities)],
                'requires_kitchen_attention' => rand(0, 1),
                'status' => $status,
                'notes' => $status === 'approved' ? 'Request approved and noted' : null,
                'created_at' => now()->subDays(rand(1, 20)),
                'updated_at' => now()->subDays(rand(0, 19)),
            ]);
        }
    }

    private function getInteractionNotes(): string
    {
        $notes = [
            'Customer was satisfied with the resolution provided.',
            'Issue was escalated to management for further review.',
            'Customer requested follow-up call within 24 hours.',
            'Problem was resolved immediately during the call.',
            'Customer was provided with compensation for the inconvenience.',
            'Technical issue was resolved by IT team.',
            'Customer was very understanding about the situation.',
            'Follow-up email was sent with detailed explanation.',
            'Customer was offered discount on next order.',
            'Issue was documented for quality improvement.'
        ];

        return $notes[array_rand($notes)];
    }

    private function getCompensationValue(): string
    {
        $values = [
            'Free meal voucher worth 50 SAR',
            '20% discount on next order',
            '500 loyalty points',
            'Cash refund of 30 SAR',
            'Free delivery on next 3 orders',
            'Complimentary dessert',
            '25% discount voucher',
            '1000 loyalty points bonus'
        ];

        return $values[array_rand($values)];
    }

    private function getActivityDescription(): string
    {
        $descriptions = [
            'Handled customer complaint about cold food delivery',
            'Processed refund request for wrong order items',
            'Resolved technical issue with mobile app',
            'Provided compensation for delivery delay',
            'Assisted with account verification process',
            'Handled loyalty points inquiry',
            'Resolved payment method issue',
            'Processed order modification request',
            'Handled delivery address change request',
            'Resolved push notification problem',
            'Assisted with promo code application',
            'Handled order tracking inquiry',
            'Processed customer feedback submission',
            'Resolved account login issue',
            'Handled billing statement inquiry'
        ];

        return $descriptions[array_rand($descriptions)];
    }

    private function getSpecialRequestDescription(): string
    {
        $descriptions = [
            'Please ensure no nuts in the preparation due to severe allergy',
            'Food should be prepared without any dairy products',
            'Please cook the meat well-done as requested',
            'Need extra packaging to prevent spillage during delivery',
            'Please deliver to the back entrance of the building',
            'Food should be prepared without any spicy ingredients',
            'Please include extra napkins and utensils',
            'Need the food to be prepared without any onions',
            'Please ensure halal preparation methods are followed',
            'Food should be delivered in eco-friendly packaging',
            'Please prepare without any artificial preservatives',
            'Need the order to be delivered in a thermal bag'
        ];

        return $descriptions[array_rand($descriptions)];
    }
} 