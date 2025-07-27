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
        // Full-text search index for customer search
        DB::statement('CREATE INDEX customers_search_idx ON customers USING gin(to_tsvector(\'english\', first_name || \' \' || last_name || \' \' || COALESCE(email, \'\')))');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Drop the Full-text search index
        DB::statement('DROP INDEX IF EXISTS customers_search_idx');
    }
};
