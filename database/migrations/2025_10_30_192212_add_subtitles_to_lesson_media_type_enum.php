<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Modify the enum to include 'subtitles'
        DB::statement("ALTER TABLE lesson_media MODIFY COLUMN type ENUM('video', 'audio', 'image', 'sign3d', 'document', 'subtitles') DEFAULT 'video'");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Revert back to original enum values
        DB::statement("ALTER TABLE lesson_media MODIFY COLUMN type ENUM('video', 'audio', 'image', 'sign3d', 'document') DEFAULT 'video'");
    }
};
