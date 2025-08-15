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
        Schema::create('bookings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->string('bookable_type'); // Hotel or Yacht
            $table->unsignedBigInteger('bookable_id');
            $table->date('check_in_date');
            $table->date('check_out_date');
            $table->decimal('total_price', 10, 2);
            $table->enum('status', ['pending', 'confirmed', 'cancelled'])->default('pending');
            $table->integer('guests_count')->default(1);
            $table->text('special_requests')->nullable();
            $table->enum('payment_method', ['credit_card', 'paypal', 'bank_transfer'])->default('credit_card');
            $table->enum('payment_status', ['pending', 'paid', 'failed', 'refunded'])->default('pending');
            $table->timestamps();

            $table->index(['user_id', 'status']);
            $table->index(['bookable_type', 'bookable_id']);
            $table->index(['check_in_date', 'check_out_date']);
            $table->index(['status', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('bookings');
    }
};