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
        Schema::create('career_jobs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employer_id')->constrained('users')->onDelete('cascade');
            $table->string('title');
            $table->text('description');
            $table->string('company_name')->nullable();
            $table->string('location'); // Город или "Remote"
            $table->string('salary_range')->nullable(); // Например "100k - 150k"
            $table->enum('job_type', ['full_time', 'part_time', 'contract', 'internship', 'project'])->default('full_time');
            $table->json('accessibility_features')->nullable(); // ["wheelchair", "sign_language", "remote"]
            $table->enum('status', ['active', 'closed', 'draft'])->default('draft');
            $table->timestamp('published_at')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('career_jobs');
    }
};
