<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            // ID tham chiếu sang Ticketing, không FK chéo module (QĐ-3.7).
            $table->unsignedBigInteger('order_id')->index();
            // Số tiền chốt theo tổng đơn, số nguyên yên (YC-2.2).
            $table->unsignedInteger('amount');
            // pending | succeeded.
            $table->string('status')->default('pending');
            $table->string('stripe_session_id')->nullable()->index();
            $table->string('stripe_payment_intent')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payments');
    }
};
