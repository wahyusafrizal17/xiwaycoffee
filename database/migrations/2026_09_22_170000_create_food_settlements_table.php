<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('food_settlements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('outlet_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->date('settled_on');
            $table->decimal('amount', 15, 2);
            $table->string('notes')->nullable();
            $table->timestamps();
            $table->index(['outlet_id', 'settled_on']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('food_settlements');
    }
};
