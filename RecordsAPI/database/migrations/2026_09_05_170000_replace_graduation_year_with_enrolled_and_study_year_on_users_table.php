<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropColumn('graduation_year');
            $table->unsignedInteger('enrolled_year')->nullable()->after('academic_track');
            $table->unsignedTinyInteger('study_year')->nullable()->after('enrolled_year');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropColumn(['enrolled_year', 'study_year']);
            $table->integer('graduation_year')->nullable()->after('academic_track');
        });
    }
};
