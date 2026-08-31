<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('employees', function (Blueprint $table) {
            $table->id();
            $table->foreignId('store_id')->constrained('stores')->cascadeOnDelete();
            $table->string('employee_no');
            $table->string('first_name');
            $table->string('last_name');
            $table->string('middle_name')->nullable();
            $table->enum('employee_type', ['manager', 'crew']);
            $table->unsignedBigInteger('manager_position_id')->nullable();
            $table->unsignedBigInteger('primary_station_id')->nullable();
            $table->enum('employment_status', ['full_time', 'part_time', 'trainee'])->default('full_time');
            $table->date('date_hired');
            $table->string('contact_number')->nullable();
            $table->unsignedTinyInteger('max_hours_per_week')->nullable();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['store_id', 'employee_no']);
            $table->index(['store_id', 'employee_type']);
            $table->index('manager_position_id');
            $table->index('primary_station_id');

            $table->foreign('manager_position_id')
                ->references('position_id')
                ->on('manager_positions')
                ->nullOnDelete();

            $table->foreign('primary_station_id')
                ->references('station_id')
                ->on('crew_stations')
                ->nullOnDelete();
        });

        Schema::create('employee_stations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained('employees')->cascadeOnDelete();
            $table->unsignedBigInteger('station_id');
            $table->enum('proficiency', ['trainee', 'certified', 'trainer'])->default('trainee');
            $table->timestamps();

            $table->unique(['employee_id', 'station_id']);
            $table->index('station_id');

            $table->foreign('station_id')
                ->references('station_id')
                ->on('crew_stations')
                ->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('employee_stations');
        Schema::dropIfExists('employees');
    }
};
