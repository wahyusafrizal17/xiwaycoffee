<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cleaning_reports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $table->foreignId('outlet_id')->nullable()->constrained()->nullOnDelete();
            $table->date('work_date');
            $table->string('slot', 5);
            $table->string('area', 20);
            $table->string('photo');
            $table->timestamps();

            $table->unique(['employee_id', 'work_date', 'slot', 'area']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cleaning_reports');
    }
};
