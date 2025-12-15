<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('career_jobs', function (Blueprint $table) {
            $table->dropColumn('salary_range');
            $table->dropColumn('job_type');

            $table->integer('salary_from')->nullable()->after('location');
            $table->integer('salary_to')->nullable()->after('salary_from');
            $table->string('currency')->default('RUR')->after('salary_to');
            
            $table->string('experience')->nullable()->after('currency'); // Using string for flexibility with enums
            $table->string('schedule')->nullable()->after('experience');
            $table->string('employment')->default('full')->after('schedule');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('career_jobs', function (Blueprint $table) {
            $table->dropColumn(['salary_from', 'salary_to', 'currency', 'experience', 'schedule', 'employment']);
            $table->string('salary_range')->nullable();
            $table->string('job_type')->default('full_time');
        });
    }
};
