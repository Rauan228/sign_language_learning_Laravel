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
        if (!Schema::hasColumn('connect_posts', 'original_post_id')) {
            Schema::table('connect_posts', function (Blueprint $table) {
                $table->foreignId('original_post_id')->nullable()->after('type')->constrained('connect_posts')->nullOnDelete();
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('connect_posts', function (Blueprint $table) {
            $table->dropForeign(['original_post_id']);
            $table->dropColumn('original_post_id');
        });
    }
};
