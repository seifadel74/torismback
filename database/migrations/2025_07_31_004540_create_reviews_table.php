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
        Schema::create('reviews', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->string('reviewable_type'); // Hotel or Yacht
            $table->unsignedBigInteger('reviewable_id');
            $table->foreignId('booking_id')->nullable()->constrained()->onDelete('set null');
            $table->integer('rating'); // 1-5 stars
            $table->text('comment');
            $table->boolean('is_verified')->default(false); // User has booking
            $table->timestamps();

            $table->index(['reviewable_type', 'reviewable_id']);
            $table->index(['user_id', 'created_at']);
            $table->index(['rating', 'created_at']);
            $table->unique(['user_id', 'reviewable_type', 'reviewable_id']); // One review per user per item
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('reviews');
    }
};