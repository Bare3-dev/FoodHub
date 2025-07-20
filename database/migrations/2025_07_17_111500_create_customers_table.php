<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB; // Added missing import for DB facade

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('customers', function (Blueprint $table) {
            $table->id();
            $table->string('first_name');
            $table->string('last_name');
            $table->string('email')->unique();
            $table->timestamp('email_verified_at')->nullable();
            $table->string('phone')->unique()->nullable();
            $table->timestamp('phone_verified_at')->nullable();
            $table->string('password');
            $table->date('date_of_birth')->nullable();
            $table->enum('gender', ['male', 'female', 'other'])->nullable();
            $table->string('profile_image_url')->nullable();
            $table->jsonb('preferences')->default('{}'); // Dietary preferences, favorite cuisines, etc.
            $table->enum('status', ['active', 'inactive', 'suspended'])->default('active');
            $table->timestamp('last_login_at')->nullable();
            $table->integer('total_orders')->default(0);
            $table->decimal('total_spent', 10, 2)->default(0.00);
            $table->boolean('marketing_emails_enabled')->default(true);
            $table->boolean('sms_notifications_enabled')->default(true);
            $table->boolean('push_notifications_enabled')->default(true);
            $table->rememberToken();
            $table->timestamps();
            
            // Indexes for frequently queried columns
            $table->index(['status', 'created_at']);
            $table->index('email');
            $table->index('phone');
            $table->index('last_login_at');
            $table->index('total_orders');
            
            // Removed: Full-text search index for customer search (will be in a separate migration)
            // DB::statement('CREATE INDEX customers_search_idx ON customers USING gin(to_tsvector(\'english\', first_name || \' \' || last_name || \' \' || COALESCE(email, \'\')))');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('customers');
    }
};
