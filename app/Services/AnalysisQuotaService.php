<?php

namespace App\Services;

use App\Exceptions\AnalysisQuotaExceededException;
use App\Models\ImageAnalysis;
use App\Models\Plan;
use App\Models\UsageRecord;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

class AnalysisQuotaService
{
    public const FREE_WEEKLY_LIMIT = 5;

    /**
     * @return array{limit: int|null, used: int, remaining: int|null, resets_at: string|null, premium: bool}
     */
    public function snapshot(User $user, ?CarbonImmutable $now = null): array
    {
        $now ??= CarbonImmutable::now();
        $periodStart = $now->subDays(7);
        $usage = $user->usageRecords()
            ->where('usage_type', 'analysis')
            ->where('created_at', '>', $periodStart);
        $used = (int) (clone $usage)->sum('quantity');
        $oldestUsage = (clone $usage)->orderBy('created_at')->value('created_at');
        $plan = $this->entitledPlan($user, $now);
        $limit = $user->role === User::ROLE_ADMIN
            ? null
            : ($plan?->analysis_limit ?? ($plan === null ? self::FREE_WEEKLY_LIMIT : null));

        return [
            'limit' => $limit,
            'used' => $used,
            'remaining' => $limit === null ? null : max(0, $limit - $used),
            'resets_at' => $limit === null || $oldestUsage === null
                ? null
                : CarbonImmutable::parse($oldestUsage)->addDays(7)->toIso8601String(),
            'premium' => $plan !== null,
        ];
    }

    /**
     * Locking the account row serializes quota reservations for that account.
     *
     * @return array{limit: int|null, used: int, remaining: int|null, resets_at: string|null, premium: bool}
     */
    public function reserve(User $user, ImageAnalysis $analysis): array
    {
        return DB::transaction(function () use ($user, $analysis): array {
            $lockedUser = User::query()->whereKey($user->getKey())->lockForUpdate()->firstOrFail();
            $quota = $this->snapshot($lockedUser);

            if ($quota['limit'] !== null && $quota['used'] >= $quota['limit']) {
                throw new AnalysisQuotaExceededException($quota);
            }

            $usage = new UsageRecord([
                'usage_type' => 'analysis',
                'quantity' => 1,
            ]);
            $usage->imageAnalysis()->associate($analysis);
            $lockedUser->usageRecords()->save($usage);

            $quota['used']++;
            $quota['remaining'] = $quota['limit'] === null
                ? null
                : max(0, $quota['limit'] - $quota['used']);

            return $quota;
        });
    }

    public function releaseReservation(ImageAnalysis $analysis): void
    {
        UsageRecord::query()
            ->where('image_analysis_id', $analysis->getKey())
            ->where('usage_type', 'analysis')
            ->delete();
    }

    public function entitledPlan(User $user, ?CarbonImmutable $now = null): ?Plan
    {
        $subscription = $user->subscriptions()
            ->currentlyEntitled($now ?? CarbonImmutable::now())
            ->with('plan')
            ->orderByDesc('starts_at')
            ->orderByDesc('id')
            ->first();

        return $subscription?->plan;
    }
}
