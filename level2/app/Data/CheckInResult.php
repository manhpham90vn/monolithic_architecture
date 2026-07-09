<?php

namespace App\Data;

use App\Enums\CheckInStatus;
use App\Models\Ticket;
use Spatie\LaravelData\Data;

/**
 * Kết quả trả về từ Action CheckInTicket cho tầng HTTP (QĐ-2.3).
 */
class CheckInResult extends Data
{
    public function __construct(
        public CheckInStatus $status,
        public ?Ticket $ticket,
        public string $scannedToken,
    ) {}
}
