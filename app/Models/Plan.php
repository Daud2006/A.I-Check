<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use InvalidArgumentException;

class Plan extends Model
{
    public const BILLING_INTERVALS = ['none', 'monthly', 'yearly'];

    protected $fillable = [
        'name',
        'slug',
        'description',
        'price',
        'currency',
        'billing_interval',
        'analysis_limit',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'price' => 'decimal:2',
            'analysis_limit' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    /**
     * @return HasMany<Subscription, $this>
     */
    public function subscriptions(): HasMany
    {
        return $this->hasMany(Subscription::class);
    }

    public function setBillingIntervalAttribute(string $interval): void
    {
        if (! in_array($interval, self::BILLING_INTERVALS, true)) {
            throw new InvalidArgumentException('The plan billing interval is invalid.');
        }

        $this->attributes['billing_interval'] = $interval;
    }

    public function setPriceAttribute(string|int|float $price): void
    {
        $numericPrice = (float) $price;

        if (! is_numeric($price) || ! is_finite($numericPrice) || $numericPrice < 0) {
            throw new InvalidArgumentException('The plan price cannot be negative.');
        }

        $this->attributes['price'] = $price;
    }

    public function setAnalysisLimitAttribute(string|int|null $analysisLimit): void
    {
        if ($analysisLimit !== null && ! ctype_digit((string) $analysisLimit)) {
            throw new InvalidArgumentException('The analysis limit must be a non-negative integer or null.');
        }

        $this->attributes['analysis_limit'] = $analysisLimit;
    }
}
