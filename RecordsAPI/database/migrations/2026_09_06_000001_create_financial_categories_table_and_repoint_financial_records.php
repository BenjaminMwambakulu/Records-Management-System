<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('financial_categories', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->text('description')->nullable();
            $table->timestamps();
        });

        Schema::table('financial_records', function (Blueprint $table) {
            $table->dropColumn('category');
            $table->foreignId('category_id')
                ->nullable()
                ->after('type')
                ->constrained('financial_categories')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('financial_records', function (Blueprint $table) {
            $table->dropConstrainedForeignId('category_id');
            $table->string('category')->nullable();
        });

        Schema::dropIfExists('financial_categories');
    }
};
