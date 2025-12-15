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
        $afterColumn = null;
        if (Schema::hasColumn('progress', 'watched_duration')) {
            $afterColumn = 'watched_duration';
        } elseif (Schema::hasColumn('progress', 'time_spent_minutes')) {
            $afterColumn = 'time_spent_minutes';
        }

        Schema::table('progress', function (Blueprint $table) use ($afterColumn) {
            if ($afterColumn) {
                $table->integer('last_position_seconds')->default(0)->after($afterColumn);
            } else {
                $table->integer('last_position_seconds')->default(0);
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasColumn('progress', 'last_position_seconds')) {
            Schema::table('progress', function (Blueprint $table) {
                $table->dropColumn('last_position_seconds');
            });
        }
    }
};
