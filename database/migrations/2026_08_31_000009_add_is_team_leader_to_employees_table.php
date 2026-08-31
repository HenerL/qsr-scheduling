<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            $table->boolean('is_team_leader')->default(false)->after('is_active');
            $table->index(['store_id', 'is_team_leader']);
        });
    }

    public function down(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            $table->dropIndex(['store_id', 'is_team_leader']);
            $table->dropColumn('is_team_leader');
        });
    }
};
