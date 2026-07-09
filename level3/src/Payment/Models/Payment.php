<?php

namespace Payment\Models;

use Database\Factories\PaymentFactory;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Bản ghi thanh toán — internal của Payment (QĐ-3.4). `order_id` chỉ là ID
 * tham chiếu sang Ticketing, không FK chéo module (QĐ-3.7).
 */
class Payment extends Model
{
    /** @use HasFactory<PaymentFactory> */
    use HasFactory;

    /** @var list<string> */
    protected $fillable = [
        'order_id', 'amount', 'status', 'stripe_session_id', 'stripe_payment_intent',
    ];

    public const string STATUS_PENDING = 'pending';

    public const string STATUS_SUCCEEDED = 'succeeded';

    protected function casts(): array
    {
        return [
            'order_id' => 'integer',
            'amount' => 'integer',
        ];
    }

    public function isSucceeded(): bool
    {
        return $this->status === self::STATUS_SUCCEEDED;
    }

    protected static function newFactory(): Factory
    {
        return PaymentFactory::new();
    }
}
