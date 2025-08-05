<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Customer;
use App\Models\User;
use Illuminate\Database\Seeder;

final class CustomerSupportTicketSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $customers = Customer::all();
        $users = User::all();

        if ($customers->isEmpty() || $users->isEmpty()) {
            $this->command->warn('Skipping CustomerSupportTicketSeeder: Required data not found.');
            return;
        }

        $ticketTypes = ['technical_issue', 'billing_inquiry', 'order_support', 'account_help', 'general_inquiry'];
        $priorities = ['low', 'medium', 'high', 'urgent'];
        $contactPreferences = ['email', 'phone', 'sms'];
        $statuses = ['open', 'in_progress', 'resolved', 'closed'];

        for ($i = 0; $i < 20; $i++) {
            $customer = $customers->random();
            $user = $users->random();
            $status = $statuses[array_rand($statuses)];
            
            \DB::table('customer_support_tickets')->insert([
                'customer_id' => $customer->id,
                'user_id' => $user->id,
                'ticket_number' => 'TKT-' . str_pad((string)($i + 1), 6, '0', STR_PAD_LEFT),
                'ticket_type' => $ticketTypes[array_rand($ticketTypes)],
                'subject' => $this->getTicketSubject(),
                'description' => $this->getTicketDescription(),
                'priority' => $priorities[array_rand($priorities)],
                'contact_preference' => $contactPreferences[array_rand($contactPreferences)],
                'status' => $status,
                'resolution_notes' => $status === 'resolved' ? $this->getResolutionNotes() : null,
                'resolved_at' => $status === 'resolved' ? now() : null,
                'created_at' => now()->subDays(rand(1, 45)),
                'updated_at' => now()->subDays(rand(0, 44)),
            ]);
        }

        $this->command->info('Successfully seeded Customer Support Ticket data.');
    }

    private function getTicketSubject(): string
    {
        $subjects = [
            'Cannot place order on mobile app',
            'Payment method not working',
            'Order tracking not updating',
            'Account login issues',
            'Delivery address change request',
            'Loyalty points not credited',
            'App crashes when browsing menu',
            'Promo code not applying',
            'Order history not showing',
            'Push notifications not working',
            'Profile information update needed',
            'Refund request for cancelled order',
            'Delivery time estimation issue',
            'Menu items not loading properly',
            'Payment confirmation not received',
            'Account verification problems',
            'Order modification request',
            'Delivery driver contact issue',
            'App performance problems',
            'Billing statement inquiry'
        ];

        return $subjects[array_rand($subjects)];
    }

    private function getTicketDescription(): string
    {
        $descriptions = [
            'I am unable to place an order through the mobile app. The app keeps crashing when I try to proceed to checkout.',
            'My payment method is not being accepted. I have tried multiple cards but none work.',
            'The order tracking feature is not updating properly. My order shows as "preparing" for hours.',
            'I cannot log into my account. The system says my credentials are invalid.',
            'I need to change my delivery address for future orders.',
            'My loyalty points from recent orders have not been credited to my account.',
            'The app crashes every time I try to browse the menu items.',
            'I have a valid promo code but it is not applying to my order.',
            'My order history is not displaying any of my recent orders.',
            'I am not receiving push notifications for order updates.',
            'I need to update my profile information including phone number.',
            'I would like to request a refund for an order that was cancelled.',
            'The delivery time estimation seems inaccurate for my area.',
            'Menu items are not loading properly in the app.',
            'I did not receive a payment confirmation email for my recent order.',
            'I am having trouble verifying my account with the verification code.',
            'I need to modify an order that was just placed.',
            'I cannot contact the delivery driver for my current order.',
            'The app is running very slowly and freezing frequently.',
            'I need clarification on my billing statement from last month.'
        ];

        return $descriptions[array_rand($descriptions)];
    }

    private function getResolutionNotes(): string
    {
        $notes = [
            'Issue resolved by clearing app cache and reinstalling the application.',
            'Payment method was updated and verified successfully.',
            'Order tracking system was refreshed and now showing correct status.',
            'Account credentials were reset and new password sent to email.',
            'Delivery address was updated in the customer profile.',
            'Loyalty points were manually credited to the account.',
            'App was updated to the latest version which resolved the crash issue.',
            'Promo code was manually applied to the order.',
            'Order history cache was cleared and now displaying correctly.',
            'Push notification settings were reconfigured and tested.',
            'Profile information was updated successfully.',
            'Refund was processed and credited back to the original payment method.',
            'Delivery time algorithm was adjusted for the customer\'s area.',
            'Menu cache was refreshed and items are now loading properly.',
            'Payment confirmation email was resent to the customer.',
            'Account verification was completed using alternative method.',
            'Order modification was processed and confirmed with the restaurant.',
            'Driver contact information was provided to the customer.',
            'App performance issues were resolved with a fresh installation.',
            'Billing statement was reviewed and explained to the customer.'
        ];

        return $notes[array_rand($notes)];
    }
} 