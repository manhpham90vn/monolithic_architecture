<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            // FK về users được phép: users là hạ tầng ở app/, không phải
            // module (QĐ-3.9).
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            // Tham chiếu sang Catalog chỉ bằng ID — KHÔNG khai báo foreign
            // key chéo module (QĐ-3.7).
            $table->unsignedBigInteger('event_id')->index();
            // pending | paid | expired | cancelled (§9).
            $table->string('status')->default('pending');
            // Tổng tiền chốt tại thời điểm tạo đơn, số nguyên yên (YC-8.5).
            $table->unsignedInteger('total_amount');
            // Hạn giữ vé: 15 phút kể từ khi tạo (YC-9.1).
            $table->dateTime('expires_at')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('orders');
    }
};
