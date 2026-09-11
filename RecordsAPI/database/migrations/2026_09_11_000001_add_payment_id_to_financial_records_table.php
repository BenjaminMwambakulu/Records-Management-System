<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('financial_records', function (Blueprint $table) {
            $table->foreignId('payment_id')
                ->nullable()
                ->after('id')
                ->constrained('payments')
                ->nullOnDelete();

            $table->unique('payment_id');
        });
    }

    public function down(): void
    {
        Schema::table('financial_records', function (Blueprint $table) {
            $table->dropUnique(['payment_id']);
            $table->dropConstrainedForeignId('payment_id');
        });
    }
};