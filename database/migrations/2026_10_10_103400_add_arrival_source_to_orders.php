<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->string('arrival_source', 40)->nullable()->after('notes');
            $table->string('arrival_source_note', 80)->nullable()->after('arrival_source');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn(['arrival_source', 'arrival_source_note']);
        });
    }
};
