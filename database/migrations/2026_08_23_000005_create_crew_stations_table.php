<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('crew_stations', function (Blueprint $table) {
            $table->id('station_id');
            $table->foreignId('store_id')->constrained('stores')->cascadeOnDelete();
            $table->string('station_name');
            $table->text('station_description')->nullable();
            $table->unsignedTinyInteger('min_crew_required')->nullable();
            $table->integer('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['store_id', 'station_name']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('crew_stations');
    }
};
