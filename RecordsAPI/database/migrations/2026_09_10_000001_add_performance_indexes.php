<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('financial_records', function (Blueprint $table) {
            $table->index('type');
            $table->index('transaction_date');
        });

        Schema::table('assets', function (Blueprint $table) {
            $table->index('category');
            $table->index('status');
        });

        Schema::table('asset_loans', function (Blueprint $table) {
            $table->index('returned_date');
            $table->index('due_date');
        });

        Schema::table('documents', function (Blueprint $table) {
            $table->index('is_public');
            $table->index('status');
        });

        Schema::table('events', function (Blueprint $table) {
            $table->index('is_published');
        });

        Schema::table('activity_log', function (Blueprint $table) {
            $table->index('event');
        });
    }

    public function down(): void
    {
        Schema::table('financial_records', function (Blueprint $table) {
            $table->dropIndex(['type']);
            $table->dropIndex(['transaction_date']);
        });

        Schema::table('assets', function (Blueprint $table) {
            $table->dropIndex(['category']);
            $table->dropIndex(['status']);
        });

        Schema::table('asset_loans', function (Blueprint $table) {
            $table->dropIndex(['returned_date']);
            $table->dropIndex(['due_date']);
        });

        Schema::table('documents', function (Blueprint $table) {
            $table->dropIndex(['is_public']);
            $table->dropIndex(['status']);
        });

        Schema::table('events', function (Blueprint $table) {
            $table->dropIndex(['is_published']);
        });

        Schema::table('activity_log', function (Blueprint $table) {
            $table->dropIndex(['event']);
        });
    }
};
