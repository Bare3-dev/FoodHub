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
        // Full-text search index for menu items
        DB::statement('CREATE INDEX menu_items_search_idx ON menu_items USING gin(to_tsvector(\'english\', name || \' \' || COALESCE(description, \'\') || \' \' || COALESCE(ingredients, \'\')))');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Drop the Full-text search index
        DB::statement('DROP INDEX IF EXISTS menu_items_search_idx');
    }
};
