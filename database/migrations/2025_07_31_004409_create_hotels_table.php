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
        Schema::create('hotels', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->text('description');
            $table->string('city');
            $table->text('address');
            $table->decimal('price_per_night', 10, 2);
            $table->decimal('rating', 2, 1)->default(0);
            $table->integer('stars')->default(3);
            $table->json('images')->nullable();
            $table->json('amenities')->nullable();
            $table->string('phone')->nullable();
            $table->string('email')->nullable();
            $table->string('website')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['city', 'is_active']);
            $table->index(['rating', 'is_active']);
            $table->index(['price_per_night', 'is_active']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('hotels');
    }
};