<?php

namespace Ticketing\Contracts;

/**
 * Kết quả soát một mã QR: hợp lệ / đã dùng / không tồn tại (YC-11.1).
 */
enum CheckInStatus: string
{
    case Valid = 'valid';
    case Used = 'used';
    case Nonexistent = 'nonexistent';
}
