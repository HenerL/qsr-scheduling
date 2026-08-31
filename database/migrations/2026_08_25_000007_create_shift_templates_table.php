<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('shift_templates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('store_id')->constrained('stores')->cascadeOnDelete();
            $table->string('template_name');
            $table->string('template_code', 10)->nullable();
            $table->time('start_time');
            $table->time('end_time');
            $table->unsignedSmallInteger('break_minutes')->default(60);
            $table->enum('applies_to', ['manager', 'crew', 'both'])->default('both');
            $table->char('color_hex', 7)->default('#2563EB');
            $table->integer('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['store_id', 'template_name']);
            $table->index(['store_id', 'applies_to']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shift_templates');
    }
};
