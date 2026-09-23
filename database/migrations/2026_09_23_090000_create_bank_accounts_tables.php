<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bank_accounts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('outlet_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('name')->default('Rekening');
            $table->timestamps();
        });

        Schema::create('bank_movements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('bank_account_id')->constrained()->cascadeOnDelete();
            $table->foreignId('outlet_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('type');
            $table->decimal('amount', 15, 2);
            $table->decimal('balance_after', 15, 2);
            $table->date('occurred_on');
            $table->nullableMorphs('reference');
            $table->string('notes')->nullable();
            $table->timestamps();
            $table->index(['outlet_id', 'occurred_on']);
            $table->unique(['type', 'reference_type', 'reference_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bank_movements');
        Schema::dropIfExists('bank_accounts');
    }
};
