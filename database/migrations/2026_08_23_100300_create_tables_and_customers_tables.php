<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tables', function (Blueprint $table) {
            $table->id();
            $table->foreignId('outlet_id')->constrained()->cascadeOnDelete();
            $table->string('code');
            $table->string('name');
            $table->unsignedInteger('capacity')->default(2);
            $table->string('status')->default('available');
            $table->unsignedInteger('pos_x')->default(40);
            $table->unsignedInteger('pos_y')->default(40);
            $table->string('shape')->default('square');
            $table->string('zone')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();
            $table->unique(['outlet_id', 'code']);
        });

        Schema::create('table_sessions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('table_id')->constrained('tables')->cascadeOnDelete();
            $table->foreignId('order_id')->nullable();
            $table->foreignId('opened_by')->nullable()->constrained('users')->nullOnDelete();
            $table->unsignedInteger('guest_count')->default(1);
            $table->timestamp('opened_at');
            $table->timestamp('closed_at')->nullable();
            $table->timestamps();
            $table->index(['table_id', 'closed_at']);
        });

        Schema::create('table_reservations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('outlet_id')->constrained()->cascadeOnDelete();
            $table->foreignId('table_id')->constrained('tables')->cascadeOnDelete();
            $table->foreignId('customer_id')->nullable();
            $table->string('guest_name');
            $table->string('guest_phone')->nullable();
            $table->unsignedInteger('guest_count')->default(2);
            $table->dateTime('reserved_at');
            $table->string('status')->default('reserved');
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('customers', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique();
            $table->string('name');
            $table->string('phone')->nullable()->index();
            $table->string('email')->nullable();
            $table->date('birthday')->nullable();
            $table->string('gender')->nullable();
            $table->text('address')->nullable();
            $table->string('membership_level')->default('regular');
            $table->unsignedInteger('points')->default(0);
            $table->decimal('total_transaction', 15, 2)->default(0);
            $table->timestamp('last_transaction_at')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('customer_points', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->constrained()->cascadeOnDelete();
            $table->foreignId('order_id')->nullable();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('type');
            $table->integer('points');
            $table->integer('balance_after');
            $table->string('reason')->nullable();
            $table->timestamps();
            $table->index(['customer_id', 'created_at']);
        });

        Schema::create('rewards', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->text('description')->nullable();
            $table->unsignedInteger('points_required');
            $table->decimal('value', 15, 2)->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rewards');
        Schema::dropIfExists('customer_points');
        Schema::dropIfExists('customers');
        Schema::dropIfExists('table_reservations');
        Schema::dropIfExists('table_sessions');
        Schema::dropIfExists('tables');
    }
};
