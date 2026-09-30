<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            $table->decimal('base_salary', 15, 2)->default(0)->after('salary');
            $table->decimal('job_allowance', 15, 2)->default(0)->after('base_salary');
            $table->decimal('transport_allowance', 15, 2)->default(0)->after('job_allowance');
            $table->decimal('cleanliness_allowance', 15, 2)->default(0)->after('transport_allowance');
            $table->decimal('sales_bonus', 15, 2)->default(0)->after('cleanliness_allowance');
            $table->decimal('deduction', 15, 2)->default(0)->after('sales_bonus');
        });
    }

    public function down(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            $table->dropColumn([
                'base_salary',
                'job_allowance',
                'transport_allowance',
                'cleanliness_allowance',
                'sales_bonus',
                'deduction',
            ]);
        });
    }
};
