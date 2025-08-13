<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('device_tokens', function (Blueprint $table) {
            $table->id();
            $table->enum('user_type', ['customer', 'driver', 'user'])->index();
            $table->unsignedBigInteger('user_id')->index();
            $table->text('token');
            $table->enum('platform', ['ios', 'android'])->index();
            $table->boolean('is_active')->default(true)->index();
            $table->timestamp('last_used_at')->nullable();
            $table->timestamps();
            
            $table->unique(['user_type', 'user_id', 'token']);
            $table->index(['user_type', 'user_id']);
        });
    }

    public function down()
    {
        Schema::dropIfExists('device_tokens');
    }
};
