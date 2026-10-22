<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use InvalidArgumentException;

class Payment extends Model
{
    public const STATUSES = ['pending', 'paid', 'failed', 'refunded'];

    protected $fillable = [
        'provider',
        'provider_transaction_id',
        'amount',
        'currency',
        'paid_at',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'paid_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return BelongsTo<Subscription, $this>
     */
    public function subscription(): BelongsTo
    {
        return $this->belongsTo(Subscription::class);
    }

    public function setStatusAttribute(string $status): void
    {
        if (! in_array($status, self::STATUSES, true)) {
            throw new InvalidArgumentException('The payment status is invalid.');
        }

        $this->attributes['status'] = $status;
    }

    public function setAmountAttribute(string|int|float $amount): void
    {
        $numericAmount = (float) $amount;

        if (! is_numeric($amount) || ! is_finite($numericAmount) || $numericAmount < 0) {
            throw new InvalidArgumentException('The payment amount cannot be negative.');
        }

        $this->attributes['amount'] = $amount;
    }
}
