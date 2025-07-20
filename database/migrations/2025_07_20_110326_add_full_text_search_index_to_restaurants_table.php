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
        // Add Full-text search index for PostgreSQL to the restaurants table
        DB::statement('CREATE INDEX restaurants_search_idx ON restaurants USING gin(to_tsvector(\'english\', name || \' \' || COALESCE(description, \'\')))');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Drop the Full-text search index from the restaurants table
        DB::statement('DROP INDEX IF EXISTS restaurants_search_idx');
    }
};
