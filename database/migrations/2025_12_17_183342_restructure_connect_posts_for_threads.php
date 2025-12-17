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
        // 1. Modify connect_posts to support threads
        Schema::table('connect_posts', function (Blueprint $table) {
            $table->unsignedBigInteger('root_thread_id')->nullable()->after('id')->index(); // ID корневого поста ветки
            $table->unsignedBigInteger('parent_id')->nullable()->after('root_thread_id')->index(); // ID родительского поста
            $table->integer('depth')->default(0)->after('parent_id'); // Глубина вложенности (0 = корень)
            $table->text('path')->nullable()->after('depth'); // Путь для быстрых выборок (например "1/5/27")
            $table->integer('reply_count')->default(0)->after('comments_count'); // Количество прямых или всех ответов
            
            $table->string('title')->nullable()->change(); // Заголовок необязателен для ответов
            $table->unsignedBigInteger('category_id')->nullable()->change(); // Категория может наследоваться или быть null для ответов
            $table->string('type')->default('discussion')->change(); // Тип по умолчанию

            $table->enum('status', ['active', 'deleted', 'hidden', 'blocked'])->default('active')->after('views');
            $table->enum('moderation_state', ['ok', 'pending', 'flagged'])->default('ok')->after('status');
        });

        // 2. Drop connect_comments table as we will use connect_posts for everything
        Schema::dropIfExists('connect_comments');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Recreate connect_comments table (simplified for rollback)
        Schema::create('connect_comments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('post_id')->constrained('connect_posts')->onDelete('cascade');
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->foreignId('parent_id')->nullable()->constrained('connect_comments')->onDelete('cascade');
            $table->text('content');
            $table->boolean('is_anonymous')->default(false);
            $table->integer('likes_count')->default(0);
            $table->softDeletes();
            $table->timestamps();
        });

        Schema::table('connect_posts', function (Blueprint $table) {
            $table->dropColumn(['root_thread_id', 'parent_id', 'depth', 'path', 'reply_count', 'status', 'moderation_state']);
            $table->string('title')->nullable(false)->change();
            $table->unsignedBigInteger('category_id')->nullable(false)->change();
        });
    }
};
