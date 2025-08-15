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
        Schema::create('yachts', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->text('description');
            $table->string('location');
            $table->decimal('price_per_day', 10, 2);
            $table->decimal('rating', 2, 1)->default(0);
            $table->json('images')->nullable();
            $table->json('amenities')->nullable();
            $table->integer('capacity');
            $table->decimal('length', 5, 2)->nullable();
            $table->integer('year_built')->nullable();
            $table->integer('crew_size')->default(0);
            $table->string('phone')->nullable();
            $table->string('email')->nullable();
            $table->string('website')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['location', 'is_active']);
            $table->index(['rating', 'is_active']);
            $table->index(['price_per_day', 'is_active']);
            $table->index(['capacity', 'is_active']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('yachts');
    }
};