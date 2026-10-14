<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cleaning_reports', function (Blueprint $table) {
            if (! Schema::hasColumn('cleaning_reports', 'item')) {
                $table->unsignedSmallInteger('item')->nullable()->after('area');
            }
            $table->index('employee_id');
        });

        Schema::table('cleaning_reports', function (Blueprint $table) {
            $table->dropUnique(['employee_id', 'work_date', 'slot', 'area']);
            $table->unique(['employee_id', 'work_date', 'slot', 'area', 'item']);
        });
    }

    public function down(): void
    {
        Schema::table('cleaning_reports', function (Blueprint $table) {
            $table->dropUnique(['employee_id', 'work_date', 'slot', 'area', 'item']);
            $table->unique(['employee_id', 'work_date', 'slot', 'area']);
            $table->dropColumn('item');
            $table->dropIndex(['employee_id']);
        });
    }
};
