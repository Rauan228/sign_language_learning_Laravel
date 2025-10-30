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
        Schema::create('career_test_results', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->foreignId('career_test_id')->constrained()->onDelete('cascade');
            $table->json('answers'); // ответы пользователя
            $table->text('disability_info')->nullable(); // информация об инвалидности
            $table->json('ai_analysis'); // анализ ИИ
            $table->json('recommendations'); // рекомендации профессий
            $table->integer('completion_time')->nullable(); // время прохождения в секундах
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('career_test_results');
    }
};
