<?php

namespace Ticketing\Infrastructure;

use Illuminate\Support\Str;
use Ticketing\Domain\Order\TokenGenerator;

/**
 * Hiện thực TokenGenerator bằng ULID của Laravel. Nằm ở Infrastructure vì
 * đây là chi tiết framework — Domain chỉ thấy interface (QĐ-4.1, QĐ-4.2).
 */
final class UlidTokenGenerator implements TokenGenerator
{
    public function generate(): string
    {
        return (string) Str::ulid();
    }
}
