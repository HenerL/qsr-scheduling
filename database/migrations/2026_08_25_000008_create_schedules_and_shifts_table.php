<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('schedules', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('store_id')->constrained()->cascadeOnDelete();
            $table->date('week_start_date');
            $table->enum('status', ['draft', 'published'])->default('draft');
            $table->timestamp('published_at')->nullable();
            $table->foreignId('published_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(['store_id', 'week_start_date']);
        });

        Schema::create('schedule_shifts', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('schedule_id')->constrained()->cascadeOnDelete();
            $table->foreignId('store_id')->constrained()->cascadeOnDelete();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $table->date('shift_date');
            $table->foreignId('shift_template_id')->nullable()->constrained('shift_templates')->nullOnDelete();
            // Nullable because a rest day carries no times.
            $table->time('start_time')->nullable();
            $table->time('end_time')->nullable();
            $table->unsignedSmallInteger('break_minutes')->default(0);
            $table->foreignId('crew_station_id')->nullable()->references('station_id')->on('crew_stations')->nullOnDelete();
            $table->foreignId('manager_position_id')->nullable()->references('position_id')->on('manager_positions')->nullOnDelete();
            $table->boolean('is_rest_day')->default(false);
            $table->enum('status', ['scheduled', 'cancelled'])->default('scheduled');
            $table->boolean('is_revised')->default(false);
            $table->string('remarks')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['store_id', 'shift_date']);
            $table->index(['employee_id', 'shift_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('schedule_shifts');
        Schema::dropIfExists('schedules');
    }
};
