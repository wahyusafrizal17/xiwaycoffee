<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('food_settlements', function (Blueprint $table) {
            $table->date('period_from')->nullable()->after('settled_on');
            $table->date('period_to')->nullable()->after('period_from');
        });
    }

    public function down(): void
    {
        Schema::table('food_settlements', function (Blueprint $table) {
            $table->dropColumn(['period_from', 'period_to']);
        });
    }
};
