<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('inventories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('outlet_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->decimal('quantity', 15, 3)->default(0);
            $table->decimal('reserved_quantity', 15, 3)->default(0);
            $table->timestamps();
            $table->unique(['outlet_id', 'product_id']);
        });

        Schema::create('inventory_movements', function (Blueprint $table) {
            $table->id();
            $table->string('reference_number')->index();
            $table->foreignId('outlet_id')->constrained();
            $table->foreignId('product_id')->constrained();
            $table->foreignId('unit_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('type');
            $table->decimal('quantity', 15, 3);
            $table->decimal('before_stock', 15, 3)->default(0);
            $table->decimal('after_stock', 15, 3)->default(0);
            $table->string('reason')->nullable();
            $table->nullableMorphs('reference');
            $table->timestamps();
            $table->index(['outlet_id', 'product_id', 'created_at']);
            $table->index('type');
        });

        Schema::create('stock_opnames', function (Blueprint $table) {
            $table->id();
            $table->string('number')->unique();
            $table->foreignId('outlet_id')->constrained();
            $table->foreignId('category_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('status')->default('draft');
            $table->text('notes')->nullable();
            $table->timestamp('finalized_at')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('stock_opname_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('stock_opname_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained();
            $table->decimal('system_qty', 15, 3)->default(0);
            $table->decimal('physical_qty', 15, 3)->default(0);
            $table->decimal('difference', 15, 3)->default(0);
            $table->string('reason')->nullable();
        });

        Schema::create('wastes', function (Blueprint $table) {
            $table->id();
            $table->string('number')->unique();
            $table->foreignId('outlet_id')->constrained();
            $table->foreignId('product_id')->constrained();
            $table->foreignId('unit_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->decimal('quantity', 15, 3);
            $table->string('reason');
            $table->text('notes')->nullable();
            $table->string('status')->default('approved');
            $table->timestamps();
            $table->softDeletes();
            $table->index(['outlet_id', 'created_at']);
        });

        Schema::create('stock_transfers', function (Blueprint $table) {
            $table->id();
            $table->string('number')->unique();
            $table->foreignId('source_outlet_id')->constrained('outlets');
            $table->foreignId('destination_outlet_id')->constrained('outlets');
            $table->foreignId('requested_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('received_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('status')->default('draft');
            $table->date('transfer_date')->nullable();
            $table->text('notes')->nullable();
            $table->timestamp('shipped_at')->nullable();
            $table->timestamp('received_at')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->index(['status', 'transfer_date']);
        });

        Schema::create('stock_transfer_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('stock_transfer_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained();
            $table->decimal('quantity', 15, 3);
            $table->decimal('received_quantity', 15, 3)->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_transfer_items');
        Schema::dropIfExists('stock_transfers');
        Schema::dropIfExists('wastes');
        Schema::dropIfExists('stock_opname_items');
        Schema::dropIfExists('stock_opnames');
        Schema::dropIfExists('inventory_movements');
        Schema::dropIfExists('inventories');
    }
};
