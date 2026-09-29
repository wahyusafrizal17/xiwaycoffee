<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('operating_expenses', function (Blueprint $table) {
            $table->string('evidence')->nullable()->after('notes');
        });
    }

    public function down(): void
    {
        Schema::table('operating_expenses', function (Blueprint $table) {
            $table->dropColumn('evidence');
        });
    }
};
