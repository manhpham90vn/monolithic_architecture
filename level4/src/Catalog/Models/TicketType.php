<?php

namespace Catalog\Models;

use Database\Factories\TicketTypeFactory;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Hạng vé — Catalog tự đếm tồn kho bằng hai bộ đếm trên chính bảng của nó:
 * `reserved_count` (vé đang giữ cho đơn chờ thanh toán) và `sold_count`
 * (vé đã bán vĩnh viễn). Ở mức 2 số vé đang giữ được suy ra bằng cách
 * query bảng orders — ở mức 3 điều đó là JOIN chéo module nên bị cấm
 * (QĐ-3.7): Ticketing phải báo giữ/trả/chốt vé qua CatalogApi.
 */
class TicketType extends Model
{
    /** @use HasFactory<TicketTypeFactory> */
    use HasFactory;

    /** @var list<string> */
    protected $fillable = ['event_id', 'name', 'price', 'quantity', 'reserved_count', 'sold_count'];

    protected function casts(): array
    {
        return [
            'price' => 'integer',
            'quantity' => 'integer',
            'reserved_count' => 'integer',
            'sold_count' => 'integer',
        ];
    }

    /** @return BelongsTo<Event, $this> */
    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    /**
     * Số vé còn có thể bán của hạng này (YC-6.4): tổng phát hành trừ số
     * đang giữ và số đã bán (YC-8.2, YC-8.4).
     */
    public function remaining(): int
    {
        return max(0, $this->quantity - $this->reserved_count - $this->sold_count);
    }

    public function isSoldOut(): bool
    {
        return $this->remaining() <= 0;
    }

    protected static function newFactory(): Factory
    {
        return TicketTypeFactory::new();
    }
}
