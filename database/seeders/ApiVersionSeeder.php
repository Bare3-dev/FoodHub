<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\ApiVersion;
use Carbon\Carbon;

class ApiVersionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Clear existing versions
        ApiVersion::truncate();
        
        // Create v1 (current stable version)
        ApiVersion::create([
            'version' => 'v1',
            'status' => ApiVersion::STATUS_ACTIVE,
            'release_date' => Carbon::parse('2024-01-01'),
            'sunset_date' => null, // No sunset date for current stable version
            'migration_guide_url' => null, // No migration required for v1
            'breaking_changes' => [], // No breaking changes for v1
            'is_default' => true, // v1 is the default version
            'min_client_version' => null, // No minimum client version requirement
            'max_client_version' => null, // No maximum client version limit
            'notes' => 'Current stable API version with all existing endpoints. This version will be maintained for at least 12 months after v2 release.'
        ]);
        
        // Create v2 (future version for breaking changes)
        ApiVersion::create([
            'version' => 'v2',
            'status' => ApiVersion::STATUS_BETA,
            'release_date' => Carbon::parse('2024-06-01'), // Make it a successor to v1
            'sunset_date' => null, // No sunset date for beta version
            'migration_guide_url' => config('app.url') . '/api/docs/migration/v1-to-v2',
            'breaking_changes' => [
                'planned_changes' => [
                    'Enhanced error handling with standardized error codes',
                    'Improved pagination with cursor-based navigation',
                    'Enhanced filtering and sorting capabilities',
                    'Webhook payload improvements',
                    'Rate limiting enhancements'
                ],
                'migration_notes' => 'Most endpoints will remain compatible with minor parameter changes. Major breaking changes will be clearly documented.'
            ],
            'is_default' => false, // v2 is not the default version yet
            'min_client_version' => null, // No minimum client version requirement yet
            'max_client_version' => null, // No maximum client version limit
            'notes' => 'Future API version for introducing breaking changes. Currently in beta planning phase. Will include comprehensive migration guides.'
        ]);
        
        // Create a deprecated version example (for testing purposes)
        // This would be used when v1 eventually becomes deprecated
        /*
        ApiVersion::create([
            'version' => 'v0',
            'status' => ApiVersion::STATUS_DEPRECATED,
            'release_date' => Carbon::parse('2023-01-01'),
            'sunset_date' => Carbon::parse('2025-06-30'), // Example sunset date
            'migration_guide_url' => config('app.url') . '/api/docs/migration/v0-to-v1',
            'breaking_changes' => [
                'deprecated_features' => [
                    'Old authentication method',
                    'Legacy webhook format',
                    'Deprecated response structure'
                ]
            ],
            'is_default' => false,
            'min_client_version' => null,
            'max_client_version' => null,
            'notes' => 'Deprecated version - clients should migrate to v1 or v2'
        ]);
        */
        
        $this->command->info('API versions seeded successfully!');
        $this->command->info('Current stable version: v1');
        $this->command->info('Future version: v2 (beta)');
    }
}
