<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('order_items', function (Blueprint $table) {
            $table->timestamp('sent_at')->nullable()->after('status');
            $table->timestamp('printed_at')->nullable()->after('sent_at');
        });

        // Order lama sudah pernah masuk dapur, jangan sampai terkirim atau tercetak ulang.
        DB::table('order_items')
            ->whereIn('order_id', DB::table('orders')->whereNotIn('status', ['draft', 'held'])->select('id'))
            ->update(['sent_at' => now(), 'printed_at' => now()]);
    }

    public function down(): void
    {
        Schema::table('order_items', function (Blueprint $table) {
            $table->dropColumn(['sent_at', 'printed_at']);
        });
    }
};
