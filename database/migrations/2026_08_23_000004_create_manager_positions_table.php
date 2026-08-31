<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('manager_positions', function (Blueprint $table) {
            $table->id('position_id');
            $table->foreignId('store_id')->constrained('stores')->cascadeOnDelete();
            $table->string('position_name');
            $table->text('position_description')->nullable();
            $table->integer('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['store_id', 'position_name']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('manager_positions');
    }
};
