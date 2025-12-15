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
        Schema::create('resumes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->string('title')->nullable(); // Желаемая должность
            $table->text('about')->nullable();
            $table->json('skills')->nullable(); // Массив навыков
            $table->json('experience')->nullable(); // История работы
            $table->json('education')->nullable(); // Образование
            $table->string('video_cv_url')->nullable(); // Видео резюме (для глухих)
            $table->json('accessibility_needs')->nullable(); // ["screen_reader", "wheelchair"]
            $table->boolean('is_public')->default(true);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('resumes');
    }
};
