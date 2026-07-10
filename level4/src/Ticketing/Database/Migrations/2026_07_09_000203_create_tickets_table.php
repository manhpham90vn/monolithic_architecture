<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tickets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            // ID tham chiếu sang Catalog, không FK chéo module (QĐ-3.7).
            $table->unsignedBigInteger('ticket_type_id')->index();
            $table->string('ticket_type_name');
            $table->unsignedBigInteger('event_id')->index();
            // FK về users được phép (QĐ-3.9).
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            // Token duy nhất mã hoá trong QR; dùng để soát vé (YC-10.1, §11).
            $table->string('token')->unique();
            // issued | used (§11).
            $table->string('status')->default('issued');
            $table->timestamp('used_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tickets');
    }
};
