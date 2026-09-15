<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->decimal('consignment_commission', 15, 2)->default(0)->after('cost');
            $table->boolean('is_recommended')->default(false)->after('is_active');
        });

        Schema::table('order_items', function (Blueprint $table) {
            $table->decimal('consignment_commission', 15, 2)->default(0)->after('total');
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn(['consignment_commission', 'is_recommended']);
        });

        Schema::table('order_items', function (Blueprint $table) {
            $table->dropColumn('consignment_commission');
        });
    }
};
