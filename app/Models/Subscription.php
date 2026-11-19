<?php

namespace App\Models;

use Carbon\CarbonInterface;
use Carbon\CarbonImmutable;
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
            'plan_terms' => 'array',
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

    public function scopeEligible(Builder $query): Builder
    {
        return $query
            ->where('status', 'active')
            ->whereNull('cancelled_at')
            ->whereHas('plan', fn (Builder $plan) => $plan->where('is_active', true)->where('price', '>', 0))
            ->where(fn (Builder $source) => $source->whereNotNull('granted_by')
                ->orWhereHas('payments', fn (Builder $payments) => $payments->where('status', 'paid')));
    }

    public static function nextStartFor(User $user, CarbonImmutable $now): ?CarbonImmutable
    {
        $subscriptions = $user->subscriptions()->eligible()
            ->where(fn (Builder $dates) => $dates->whereNull('ends_at')->orWhere('ends_at', '>', $now))
            ->lockForUpdate()->get(['ends_at']);

        if ($subscriptions->contains(fn (self $subscription) => $subscription->ends_at === null)) {
            return null;
        }

        return $subscriptions->reduce(
            fn (CarbonImmutable $start, self $subscription) => $start->max(CarbonImmutable::instance($subscription->ends_at)),
            $now,
        );
    }

    public function planName(): ?string
    {
        return $this->plan_terms['name'] ?? $this->plan?->name;
    }

    public function analysisLimit(): ?int
    {
        return $this->plan_terms !== null ? $this->plan_terms['analysis_limit'] : $this->plan?->analysis_limit;
    }

    /**
     * @param  Builder<Subscription>  $query
     * @return Builder<Subscription>
     */
    public function scopeCurrentlyEntitled(Builder $query, CarbonInterface $now): Builder
    {
        return $query
            ->eligible()
            ->where(function (Builder $query) use ($now): void {
                $query->whereNull('starts_at')->orWhere('starts_at', '<=', $now);
            })
            ->where(function (Builder $query) use ($now): void {
                $query->whereNull('ends_at')->orWhere('ends_at', '>=', $now);
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
