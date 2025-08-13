<?php

namespace Tests\Feature\Api;

use Tests\TestCase;
use App\Models\ApiVersion;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;

class ApiVersioningTest extends TestCase
{
    use RefreshDatabase, WithFaker;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Seed the API versions
        $this->seed(\Database\Seeders\ApiVersionSeeder::class);
    }

    /** @test */
    public function it_returns_version_info_for_unversioned_endpoint()
    {
        $response = $this->getJson('/api/version');

        $response->assertStatus(200)
            ->assertJson([
                'current_version' => 'v1',
                'status' => 'active'
            ])
            ->assertJsonStructure([
                'current_version',
                'status',
                'deprecated_versions',
                'migration_guide',
                'support_contact'
            ]);
    }

    /** @test */
    public function it_accepts_v1_versioned_endpoints()
    {
        $response = $this->getJson('/api/v1/restaurants');

        $response->assertStatus(200)
            ->assertHeader('X-API-Version', 'v1')
            ->assertHeader('X-API-Version-Status', 'active');
    }

    /** @test */
    public function it_accepts_v2_versioned_endpoints()
    {
        $response = $this->getJson('/api/v2/migration/check');

        $response->assertStatus(200)
            ->assertJson([
                'current_version' => 'v2',
                'status' => 'beta',
                'migration_required' => true
            ]);
    }

    /** @test */
    public function it_returns_error_for_invalid_version()
    {
        $response = $this->getJson('/api/v999/restaurants');

        $response->assertStatus(400)
            ->assertJson([
                'error' => 'API version not found',
                'message' => "API version 'v999' is not supported"
            ])
            ->assertJsonStructure([
                'error',
                'message',
                'available_versions',
                'current_version',
                'migration_guide'
            ]);
    }

    /** @test */
    public function it_accepts_legacy_unversioned_endpoints()
    {
        $response = $this->getJson('/api/restaurants');

        $response->assertStatus(200);
    }

    /** @test */
    public function it_returns_migration_info_for_v1()
    {
        $response = $this->getJson('/api/v1/migration/check');

        $response->assertStatus(200)
            ->assertJson([
                'current_version' => 'v1',
                'status' => 'stable',
                'migration_required' => false
            ]);
    }

    /** @test */
    public function it_returns_migration_info_for_v2()
    {
        $response = $this->getJson('/api/v2/migration/check');

        $response->assertStatus(200)
            ->assertJson([
                'current_version' => 'v2',
                'status' => 'beta',
                'migration_required' => true
            ]);
    }

    /** @test */
    public function it_includes_version_headers_in_responses()
    {
        $response = $this->getJson('/api/v1/restaurants');

        $response->assertHeader('X-API-Version', 'v1')
            ->assertHeader('X-API-Version-Status', 'active')
            ->assertHeader('X-API-Migration-Guide');
    }

    /** @test */
    public function it_handles_deprecated_version_warnings()
    {
        // Create a deprecated version for testing
        $deprecatedVersion = ApiVersion::create([
            'version' => 'v0',
            'status' => ApiVersion::STATUS_DEPRECATED,
            'release_date' => now()->subYear(),
            'sunset_date' => now()->addMonths(6),
            'migration_guide_url' => 'https://example.com/migration',
            'breaking_changes' => [],
            'is_default' => false,
            'notes' => 'Deprecated version'
        ]);

        // This test would require the deprecated version to have routes
        // For now, we'll just test the model behavior
        $this->assertTrue($deprecatedVersion->isDeprecated());
        $this->assertFalse($deprecatedVersion->isSunset());
        $this->assertNotNull($deprecatedVersion->getDeprecationWarning());
    }

    /** @test */
    public function it_handles_sunset_version_responses()
    {
        // Create a sunset version for testing
        $sunsetVersion = ApiVersion::create([
            'version' => 'v0',
            'status' => ApiVersion::STATUS_SUNSET,
            'release_date' => now()->subYear(),
            'sunset_date' => now()->subDay(), // Already sunset
            'migration_guide_url' => 'https://example.com/migration',
            'breaking_changes' => [],
            'is_default' => false,
            'notes' => 'Sunset version'
        ]);

        $this->assertTrue($sunsetVersion->isSunset());
        $this->assertNotNull($sunsetVersion->getDeprecationWarning());
    }

    /** @test */
    public function it_provides_successor_version_information()
    {
        $v1 = ApiVersion::where('version', 'v1')->first();
        $v2 = ApiVersion::where('version', 'v2')->first();

        $successor = $v1->getSuccessorVersion();
        $this->assertNotNull($successor);
        $this->assertEquals('v2', $successor->version);
    }

    /** @test */
    public function it_handles_version_lifecycle_correctly()
    {
        $v1 = ApiVersion::where('version', 'v1')->first();
        $v2 = ApiVersion::where('version', 'v2')->first();

        // Test v1 lifecycle
        $this->assertTrue($v1->isActive());
        $this->assertFalse($v1->isDeprecated());
        $this->assertFalse($v1->isSunset());
        $this->assertTrue($v1->is_default);

        // Test v2 lifecycle
        $this->assertFalse($v2->isActive());
        $this->assertFalse($v2->isDeprecated());
        $this->assertFalse($v2->isSunset());
        $this->assertFalse($v2->is_default);
    }
}
