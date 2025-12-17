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
        // Categories for the forum
        Schema::create('connect_categories', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->text('description')->nullable();
            $table->string('icon')->nullable(); // e.g., 'heart', 'brain'
            $table->timestamps();
        });

        // Posts
        Schema::create('connect_posts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
            $table->foreignId('category_id')->constrained('connect_categories')->onDelete('cascade');
            $table->string('title');
            $table->text('content');
            $table->string('type')->default('discussion'); // question, experience, discussion, support
            $table->boolean('is_anonymous')->default(false);
            $table->json('tags')->nullable();
            $table->unsignedInteger('views')->default(0);
            $table->unsignedInteger('likes_count')->default(0);
            $table->unsignedInteger('comments_count')->default(0);
            $table->timestamp('last_activity_at')->useCurrent();
            $table->timestamps();
            $table->softDeletes();
        });

        // Comments
        Schema::create('connect_comments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('post_id')->constrained('connect_posts')->onDelete('cascade');
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
            $table->foreignId('parent_id')->nullable()->constrained('connect_comments')->onDelete('cascade');
            $table->text('content');
            $table->boolean('is_anonymous')->default(false);
            $table->unsignedInteger('likes_count')->default(0);
            $table->timestamps();
            $table->softDeletes();
        });

        // Likes (Polymorphic)
        Schema::create('connect_likes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
            $table->morphs('likeable'); // likeable_type, likeable_id
            $table->timestamps();
            
            // Unique like per user per item
            $table->unique(['user_id', 'likeable_type', 'likeable_id']);
        });

        // Direct Messages
        Schema::create('connect_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sender_id')->constrained('users')->onDelete('cascade');
            $table->foreignId('receiver_id')->constrained('users')->onDelete('cascade');
            $table->text('content');
            $table->boolean('is_read')->default(false);
            $table->boolean('is_anonymous')->default(false); // If sender wants to be anon (might be restricted)
            $table->timestamps();
            $table->softDeletes();
        });
        
        // Reports (Moderation)
        Schema::create('connect_reports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('reporter_id')->constrained('users')->onDelete('cascade');
            $table->morphs('reportable'); // post, comment, user
            $table->string('reason');
            $table->text('details')->nullable();
            $table->string('status')->default('pending'); // pending, resolved, dismissed
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('connect_reports');
        Schema::dropIfExists('connect_messages');
        Schema::dropIfExists('connect_likes');
        Schema::dropIfExists('connect_comments');
        Schema::dropIfExists('connect_posts');
        Schema::dropIfExists('connect_categories');
    }
};
