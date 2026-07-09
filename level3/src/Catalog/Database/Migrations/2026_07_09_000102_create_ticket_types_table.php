<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ticket_types', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            // Giá lưu bằng số nguyên yên (JPY không có phần thập phân) — YC-2.2, YC-6.3.
            $table->unsignedInteger('price');
            // Tổng số vé được bán ra của hạng này (YC-6.3).
            $table->unsignedInteger('quantity');
            // Bộ đếm tồn kho của riêng Catalog (YC-8.2, YC-8.4): mức 3 không
            // được suy ra số vé đang giữ bằng cách JOIN sang bảng orders của
            // Ticketing (QĐ-3.7).
            $table->unsignedInteger('reserved_count')->default(0);
            $table->unsignedInteger('sold_count')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ticket_types');
    }
};
