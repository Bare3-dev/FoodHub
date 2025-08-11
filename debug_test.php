<?php

require_once 'vendor/autoload.php';

// Bootstrap Laravel
$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use App\Models\Challenge;
use App\Models\Customer;
use App\Models\CustomerChallenge;
use App\Services\CustomerChallengeService;

echo "=== Debug Test ===\n";

try {
    // Create challenge
    $challenge = Challenge::factory()->frequency()->create([
        'reward_type' => 'points',
        'reward_value' => 100,
    ]);
    echo "Challenge created: ID={$challenge->id}, reward_type={$challenge->reward_type}\n";
    
    // Create customer
    $customer = Customer::factory()->create();
    echo "Customer created: ID={$customer->id}\n";
    
    // Create customer challenge
    $customerChallenge = CustomerChallenge::create([
        'customer_id' => $customer->id,
        'challenge_id' => $challenge->id,
        'progress_target' => 5,
        'progress_current' => 4,
        'status' => 'active',
        'started_at' => now(),
        'expires_at' => now()->addDays(7),
        'reward_claimed' => false,
        'assigned_at' => now(),
    ]);
    echo "CustomerChallenge created: ID={$customerChallenge->id}\n";
    
    // Check if challenge relationship is loaded
    echo "Challenge relationship loaded: " . ($customerChallenge->challenge ? 'yes' : 'no') . "\n";
    if ($customerChallenge->challenge) {
        echo "Challenge reward_type: " . $customerChallenge->challenge->reward_type . "\n";
    }
    
    // Test the service method directly
    $service = app(CustomerChallengeService::class);
    echo "Service created successfully\n";
    
    // Check current progress
    echo "Current progress: {$customerChallenge->progress_current}/{$customerChallenge->progress_target}\n";
    echo "Progress percentage: {$customerChallenge->progress_percentage}%\n";
    echo "Status: {$customerChallenge->status}\n";
    
    // Test the update method
    echo "\nCalling updateChallengeProgress...\n";
    $service->updateChallengeProgress($customer, 'order_placed', ['order_number' => 'ORD-123']);
    
    // Refresh and check results
    $customerChallenge->refresh();
    echo "After update:\n";
    echo "Progress: {$customerChallenge->progress_current}/{$customerChallenge->progress_target}\n";
    echo "Progress percentage: {$customerChallenge->progress_percentage}%\n";
    echo "Status: {$customerChallenge->status}\n";
    echo "Reward claimed: " . ($customerChallenge->reward_claimed ? 'yes' : 'no') . "\n";
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
    echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
}
