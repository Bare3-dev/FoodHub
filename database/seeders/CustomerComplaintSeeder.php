<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Customer;
use App\Models\Order;
use App\Models\User;
use Illuminate\Database\Seeder;

final class CustomerComplaintSeeder extends Seeder
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
            $this->command->warn('Skipping CustomerComplaintSeeder: Required data not found.');
            return;
        }

        $complaintTypes = ['food_safety', 'delivery_issue', 'order_error', 'staff_behavior', 'food_quality'];
        $severities = ['low', 'medium', 'high', 'critical'];
        $resolutions = ['refund', 'replacement', 'apology', 'investigation', 'immediate_refund'];
        $contactPreferences = ['email', 'phone', 'sms'];
        $statuses = ['pending', 'in_progress', 'resolved', 'escalated'];

        for ($i = 0; $i < 15; $i++) {
            $order = $orders->random();
            $customer = $customers->random();
            $user = $users->random();
            $status = $statuses[array_rand($statuses)];
            
            $complaint = \DB::table('customer_complaints')->insertGetId([
                'order_id' => $order->id,
                'customer_id' => $customer->id,
                'user_id' => $user->id,
                'complaint_type' => $complaintTypes[array_rand($complaintTypes)],
                'severity' => $severities[array_rand($severities)],
                'description' => $this->getComplaintDescription(),
                'requested_resolution' => $resolutions[array_rand($resolutions)],
                'contact_preference' => $contactPreferences[array_rand($contactPreferences)],
                'status' => $status,
                'resolution_notes' => $status === 'resolved' ? $this->getResolutionNotes() : null,
                'resolved_at' => $status === 'resolved' ? now() : null,
                'created_at' => now()->subDays(rand(1, 30)),
                'updated_at' => now()->subDays(rand(0, 29)),
            ]);
        }

        $this->command->info('Successfully seeded Customer Complaint data.');
    }

    private function getComplaintDescription(): string
    {
        $descriptions = [
            'Food was delivered cold and soggy',
            'Wrong items were included in the order',
            'Delivery was extremely late',
            'Food quality was poor and tasted bad',
            'Staff was rude during delivery',
            'Order was missing several items',
            'Food was not properly cooked',
            'Delivery driver was unprofessional',
            'Order was completely wrong',
            'Food had foreign objects in it',
            'Delivery address was incorrect',
            'Food was stale and old',
            'Service was very slow',
            'Food was not fresh',
            'Order was cancelled without notification'
        ];

        return $descriptions[array_rand($descriptions)];
    }

    private function getResolutionNotes(): string
    {
        $notes = [
            'Customer was provided with a full refund and apology',
            'Order was replaced with fresh items',
            'Customer received compensation voucher',
            'Issue was investigated and resolved',
            'Customer was offered discount on next order',
            'Staff was retrained on proper procedures',
            'Customer received immediate refund',
            'Quality control measures were improved',
            'Customer was provided with free meal voucher',
            'Delivery process was reviewed and improved'
        ];

        return $notes[array_rand($notes)];
    }
} 