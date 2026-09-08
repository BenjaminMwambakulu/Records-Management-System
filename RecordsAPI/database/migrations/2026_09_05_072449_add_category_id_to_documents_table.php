<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('documents', function (Blueprint $table) {
            $table->foreignId('category_id')->nullable()->after('category')->constrained('document_categories')->nullOnDelete();
        });

        $this->backfillCategories();

        Schema::table('documents', function (Blueprint $table) {
            $table->dropColumn('category');
        });
    }

    protected function backfillCategories(): void
    {
        DB::table('documents')->select('category')->distinct()->whereNotNull('category')->get()
            ->each(function (object $row): void {
                DB::table('document_categories')->insertOrIgnore([
                    'name' => $row->category,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            });

        DB::table('documents')->whereNotNull('category')->get()
            ->each(function (object $row): void {
                $category = DB::table('document_categories')->where('name', $row->category)->first();

                DB::table('documents')->where('id', $row->id)->update(['category_id' => $category->id]);
            });
    }

    public function down(): void
    {
        Schema::table('documents', function (Blueprint $table) {
            $table->string('category')->nullable()->after('category_id');
        });

        DB::table('documents')->get()
            ->each(function (object $row): void {
                if (! $row->category_id) {
                    return;
                }

                $category = DB::table('document_categories')->where('id', $row->category_id)->first();
                DB::table('documents')->where('id', $row->id)->update(['category' => $category->name]);
            });

        Schema::table('documents', function (Blueprint $table) {
            $table->dropForeign(['category_id']);
            $table->dropColumn('category_id');
        });
    }
};
