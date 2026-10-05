<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bundles', function (Blueprint $table) {
            $table->string('campaign')->nullable()->after('name');
            $table->boolean('weekdays_only')->default(false)->after('end_time');
            $table->string('requirement')->nullable()->after('weekdays_only');
        });

        Schema::table('bundle_items', function (Blueprint $table) {
            $table->string('choice_group')->nullable()->after('quantity');
        });

        Schema::table('order_items', function (Blueprint $table) {
            $table->json('bundle_picks')->nullable()->after('bundle_id');
        });
    }

    public function down(): void
    {
        Schema::table('order_items', function (Blueprint $table) {
            $table->dropColumn('bundle_picks');
        });

        Schema::table('bundle_items', function (Blueprint $table) {
            $table->dropColumn('choice_group');
        });

        Schema::table('bundles', function (Blueprint $table) {
            $table->dropColumn(['campaign', 'weekdays_only', 'requirement']);
        });
    }
};
