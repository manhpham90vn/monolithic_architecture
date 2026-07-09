<?php

namespace Ticketing\Contracts;

/**
 * Cửa ngõ DUY NHẤT của module Ticketing cho các module khác (QĐ-3.3).
 * CheckIn gọi qua đây để soát vé — cần kết quả trả về nên là lời gọi API
 * trực tiếp, không dùng event (QĐ-3.5 cấm event khi cần kết quả).
 */
interface TicketingApi
{
    /**
     * Soát một mã QR: trả kết quả hợp lệ / đã dùng / không tồn tại
     * (YC-11.1). Vé hợp lệ được chuyển sang "đã dùng" và không soát được
     * lần hai (YC-11.2, YC-11.3).
     */
    public function checkIn(string $token): CheckInResult;
}
