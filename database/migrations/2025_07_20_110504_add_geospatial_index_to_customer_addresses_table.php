<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Add Geospatial index for location-based queries
        DB::statement('CREATE INDEX customer_addresses_location_idx ON customer_addresses USING gist(point(longitude, latitude))');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Drop the Geospatial index
        DB::statement('DROP INDEX IF EXISTS customer_addresses_location_idx');
    }
};
