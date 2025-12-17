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
        // Professional Profile
        Schema::create('connect_professionals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
            $table->string('title'); // e.g. "Certified Psychologist"
            $table->string('specialization'); // psychologist, teacher
            $table->text('bio')->nullable();
            $table->json('languages')->nullable(); // ['ru', 'en', 'sign_ru']
            $table->decimal('price_per_hour', 10, 2)->nullable();
            $table->float('rating')->default(0);
            $table->unsignedInteger('reviews_count')->default(0);
            $table->boolean('is_verified')->default(false);
            $table->timestamps();
        });

        // Services offered by professionals
        Schema::create('connect_services', function (Blueprint $table) {
            $table->id();
            $table->foreignId('professional_id')->constrained('connect_professionals')->onDelete('cascade');
            $table->string('type'); // private, group
            $table->string('name');
            $table->text('description')->nullable();
            $table->integer('duration_minutes')->default(60);
            $table->decimal('price', 10, 2);
            $table->integer('max_participants')->default(1);
            $table->timestamps();
        });

        // Availability / Schedule slots
        Schema::create('connect_schedules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('professional_id')->constrained('connect_professionals')->onDelete('cascade');
            $table->dateTime('start_time');
            $table->dateTime('end_time');
            $table->boolean('is_booked')->default(false);
            $table->timestamps();
        });

        // Bookings
        Schema::create('connect_bookings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
            $table->foreignId('professional_id')->constrained('connect_professionals')->onDelete('cascade');
            $table->foreignId('service_id')->constrained('connect_services')->onDelete('cascade');
            $table->foreignId('schedule_id')->nullable()->constrained('connect_schedules')->onDelete('set null'); // Nullable for group classes that might have different scheduling logic
            $table->string('status')->default('pending'); // pending, confirmed, completed, cancelled
            $table->boolean('is_anonymous')->default(false);
            $table->text('notes')->nullable();
            $table->string('meeting_link')->nullable();
            $table->timestamps();
        });

        // User Subscriptions to Connect Plus
        Schema::create('connect_subscriptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
            $table->string('plan_type')->default('plus'); // basic, plus
            $table->dateTime('starts_at');
            $table->dateTime('ends_at');
            $table->string('status')->default('active'); // active, expired, cancelled
            $table->timestamps();
        });

        // Reviews for professionals
        Schema::create('connect_reviews', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
            $table->foreignId('professional_id')->constrained('connect_professionals')->onDelete('cascade');
            $table->foreignId('booking_id')->nullable()->constrained('connect_bookings')->onDelete('set null');
            $table->integer('rating'); // 1-5
            $table->text('comment')->nullable();
            $table->boolean('is_anonymous')->default(false);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('connect_reviews');
        Schema::dropIfExists('connect_subscriptions');
        Schema::dropIfExists('connect_bookings');
        Schema::dropIfExists('connect_schedules');
        Schema::dropIfExists('connect_services');
        Schema::dropIfExists('connect_professionals');
    }
};
