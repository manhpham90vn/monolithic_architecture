<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('order_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            // ID tham chiếu sang Catalog, không FK chéo module (QĐ-3.7).
            $table->unsignedBigInteger('ticket_type_id')->index();
            // Tên hạng vé chụp tại thời điểm tạo đơn — hiển thị không cần
            // gọi Catalog; giá cũng chốt tại thời điểm này (YC-8.5).
            $table->string('ticket_type_name');
            $table->unsignedInteger('quantity');
            $table->unsignedInteger('unit_price');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('order_items');
    }
};
