<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->timestamp('kitchen_printed_at')->nullable()->after('cancel_reason');
            $table->timestamp('bar_printed_at')->nullable()->after('kitchen_printed_at');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn(['kitchen_printed_at', 'bar_printed_at']);
        });
    }
};
