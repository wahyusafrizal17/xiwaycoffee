<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('content_links', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $table->foreignId('outlet_id')->constrained()->cascadeOnDelete();
            $table->date('work_date');
            $table->string('url');
            $table->timestamps();

            $table->unique(['outlet_id', 'work_date', 'url']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('content_links');
    }
};
