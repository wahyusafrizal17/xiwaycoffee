<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('units', function (Blueprint $table) {
            $table->string('family')->nullable()->after('name');
            $table->decimal('conversion_factor', 15, 6)->default(1)->after('family');
        });

        Schema::table('order_items', function (Blueprint $table) {
            $table->foreignId('batch_id')->nullable()->after('bundle_id')->constrained('production_batches')->nullOnDelete();
            $table->foreignId('confirmed_by')->nullable()->after('status')->constrained('users')->nullOnDelete();
            $table->timestamp('confirmed_at')->nullable()->after('confirmed_by');
        });

        Schema::table('production_batches', function (Blueprint $table) {
            $table->decimal('remaining_quantity', 15, 3)->default(0)->after('yield_quantity');
        });

        DB::table('production_batches')->update([
            'remaining_quantity' => DB::raw('COALESCE(yield_quantity, quantity)'),
        ]);

        Schema::table('inventory_movements', function (Blueprint $table) {
            $table->foreignId('batch_id')->nullable()->after('unit_id')->constrained('production_batches')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('inventory_movements', function (Blueprint $table) {
            $table->dropConstrainedForeignId('batch_id');
        });

        Schema::table('production_batches', function (Blueprint $table) {
            $table->dropColumn('remaining_quantity');
        });

        Schema::table('order_items', function (Blueprint $table) {
            $table->dropConstrainedForeignId('batch_id');
            $table->dropConstrainedForeignId('confirmed_by');
            $table->dropColumn('confirmed_at');
        });

        Schema::table('units', function (Blueprint $table) {
            $table->dropColumn(['family', 'conversion_factor']);
        });
    }
};
