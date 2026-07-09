<?php

namespace Catalog\Contracts;

use DomainException;

/**
 * Ném ra khi giữ vé mà một hạng không còn đủ (YC-8.2, YC-8.3). Exception
 * mà Public API ném ra là một phần của hợp đồng API nên nằm trong
 * Contracts\ (tinh thần QĐ-3.3) — module gọi bắt nó mà không phải import
 * gì internal của Catalog.
 */
class NotEnoughTickets extends DomainException
{
    public static function forTicketType(string $name, int $remaining): self
    {
        return new self("Hạng vé \"{$name}\" không đủ vé (còn {$remaining}).");
    }
}
