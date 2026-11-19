<?php

namespace App\Models;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use InvalidArgumentException;

class Subscription extends Model
{
    public const STATUSES = ['pending', 'active', 'expired', 'cancelled'];

    protected $fillable = [
        'plan_id',
        'starts_at',
        'ends_at',
    ];

    protected function casts(): array
    {
        return [
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'cancelled_at' => 'datetime',
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
     * @return BelongsTo<Plan, $this>
     */
    public function plan(): BelongsTo
    {
        return $this->belongsTo(Plan::class);
    }

    /**
     * @return HasMany<Payment, $this>
     */
    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    /**
     * @param  Builder<Subscription>  $query
     * @return Builder<Subscription>
     */
    public function scopeCurrentlyEntitled(Builder $query, CarbonInterface $now): Builder
    {
        return $query
            ->where('status', 'active')
            ->whereNull('cancelled_at')
            ->where(function (Builder $query) use ($now): void {
                $query->whereNull('starts_at')->orWhere('starts_at', '<=', $now);
            })
            ->where(function (Builder $query) use ($now): void {
                $query->whereNull('ends_at')->orWhere('ends_at', '>=', $now);
            })
            ->whereHas('plan', function (Builder $query): void {
                $query->where('is_active', true)->where('price', '>', 0);
            })
            ->whereHas('payments', function (Builder $query): void {
                $query->where('status', 'paid');
            });
    }

    public function setStatusAttribute(string $status): void
    {
        if (! in_array($status, self::STATUSES, true)) {
            throw new InvalidArgumentException('The subscription status is invalid.');
        }

        $this->attributes['status'] = $status;
    }
}
