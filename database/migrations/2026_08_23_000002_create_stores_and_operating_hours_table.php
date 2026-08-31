<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stores', function (Blueprint $table) {
            $table->id();
            $table->foreignId('owner_user_id')->constrained('users')->cascadeOnDelete();
            $table->string('store_name');
            $table->string('branch_name')->nullable();
            $table->string('store_code')->unique();
            $table->string('address')->nullable();
            $table->string('contact_number')->nullable();
            $table->string('timezone')->default('Asia/Manila');
            $table->tinyInteger('week_starts_on')->default(0);
            $table->tinyInteger('max_consecutive_duty_days')->default(6);
            $table->tinyInteger('onboarding_step')->default(1);
            $table->timestamp('onboarding_completed_at')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('store_operating_hours', function (Blueprint $table) {
            $table->id();
            $table->foreignId('store_id')->constrained('stores')->cascadeOnDelete();
            $table->tinyInteger('day_of_week');
            $table->boolean('is_open')->default(true);
            $table->boolean('is_24_hours')->default(false);
            $table->time('open_time')->nullable();
            $table->time('close_time')->nullable();
            $table->timestamps();

            $table->unique(['store_id', 'day_of_week']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('store_operating_hours');
        Schema::dropIfExists('stores');
    }
};
