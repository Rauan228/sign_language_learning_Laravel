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
        // 1. Create Follows/Subscriptions table
        Schema::create('connect_follows', function (Blueprint $table) {
            $table->id();
            $table->foreignId('follower_id')->constrained('users')->onDelete('cascade');
            $table->foreignId('following_id')->constrained('users')->onDelete('cascade');
            $table->string('status')->default('accepted'); // accepted, pending
            $table->timestamps();

            $table->unique(['follower_id', 'following_id']);
        });

        // 2. Add Repost support to connect_posts
        Schema::table('connect_posts', function (Blueprint $table) {
            $table->unsignedBigInteger('original_post_id')->nullable()->after('id')->index();
            $table->foreign('original_post_id')->references('id')->on('connect_posts')->onDelete('set null');
            
            // We can also assume that if original_post_id is set, it's a repost.
            // But having an explicit type might be useful if we want to differentiate 'share' vs 'repost' later.
            // However, the existing 'type' column can be used or we just rely on original_post_id.
            // Let's rely on original_post_id and type='repost'
        });

        // 3. Ensure Messages table exists (it was in create_connect_community_tables but might need adjustments)
        // Since we are not sure if it was created properly or if we need to add fields, let's check/update.
        // The previous migration 'create_connect_community_tables' created 'connect_messages'.
        // Let's verify it has what we need. It had: sender_id, receiver_id, content, is_read, is_anonymous.
        // That seems sufficient for now.
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('connect_posts', function (Blueprint $table) {
            $table->dropForeign(['original_post_id']);
            $table->dropColumn(['original_post_id']);
        });

        Schema::dropIfExists('connect_follows');
    }
};
