<?php

namespace App\Services;

use App\Models\Payment;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use RuntimeException;

class MidtransNotificationProcessor
{
    /**
     * @param  array<string, mixed>  $notification
     */
    public function process(array $notification): void
    {
        $orderId = $notification['order_id'] ?? null;

        if (! is_string($orderId) || ! preg_match('/^AIC-PAY-([1-9][0-9]*)$/', $orderId, $matches)) {
            throw new InvalidArgumentException('Midtrans order reference is invalid.');
        }

        $paymentId = (int) $matches[1];
        $status = $this->mapStatus(
            (string) ($notification['transaction_status'] ?? ''),
            (string) ($notification['fraud_status'] ?? '')
        );

        DB::transaction(function () use ($notification, $orderId, $paymentId, $status): void {
            $payment = Payment::query()
                ->whereKey($paymentId)
                ->where('provider', 'midtrans')
                ->lockForUpdate()
                ->first();

            if (! $payment instanceof Payment) {
                throw (new ModelNotFoundException)->setModel(Payment::class, [$paymentId]);
            }

            if ($orderId !== 'AIC-PAY-'.$payment->getKey()) {
                throw new InvalidArgumentException('Midtrans order reference does not match.');
            }

            if ($this->normalizeAmount((string) $notification['gross_amount']) !== $this->normalizeAmount((string) $payment->amount)) {
                throw new InvalidArgumentException('Midtrans payment amount does not match.');
            }

            $providerTransactionId = $notification['transaction_id'] ?? null;

            if (
                is_string($payment->provider_transaction_id)
                && $payment->provider_transaction_id !== ''
                && is_string($providerTransactionId)
                && $payment->provider_transaction_id !== $providerTransactionId
            ) {
                throw new InvalidArgumentException('Midtrans transaction reference does not match.');
            }

            if ($status === null) {
                return;
            }

            if ($status === 'pending') {
                if ($payment->status === 'pending') {
                    $this->saveTransactionId($payment, $providerTransactionId);
                }

                return;
            }

            if ($status === 'failed') {
                if ($payment->status !== 'pending') {
                    return;
                }

                $payment->status = 'failed';
                $this->saveTransactionId($payment, $providerTransactionId);
                $payment->save();
                $this->cancelSubscription($payment->subscription_id);

                return;
            }

            if ($status === 'paid') {
                if (in_array($payment->status, ['pending', 'failed'], true)) {
                    $user = User::query()->whereKey($payment->user_id)->lockForUpdate()->firstOrFail();
                    $payment->status = 'paid';
                    $payment->paid_at = now();
                    $this->saveTransactionId($payment, $providerTransactionId);
                    $payment->save();
                    $this->activateSubscription($payment, $user);
                }

                return;
            }

            if ($status === 'refunded' && $payment->status === 'paid') {
                $payment->status = 'refunded';
                $this->saveTransactionId($payment, $providerTransactionId);
                $payment->save();
                $this->cancelSubscription($payment->subscription_id);
            }
        });
    }

    private function activateSubscription(Payment $payment, User $user): void
    {
        if ($payment->subscription_id === null) {
            return;
        }

        $subscription = Subscription::query()
            ->whereKey($payment->subscription_id)
            ->where('user_id', $user->getKey())
            ->lockForUpdate()
            ->first();

        if (! $subscription instanceof Subscription || $subscription->status === 'active') {
            return;
        }

        if (! in_array($subscription->status, ['pending', 'cancelled'], true)) {
            return;
        }

        $plan = Plan::query()->find($subscription->plan_id);

        if (! $plan instanceof Plan || ! in_array($plan->billing_interval, ['monthly', 'yearly'], true)) {
            throw new RuntimeException('The purchased plan does not have a supported billing interval.');
        }

        $now = CarbonImmutable::now();
        $existingSubscriptions = Subscription::query()
            ->where('user_id', $user->getKey())
            ->where('status', 'active')
            ->whereNull('cancelled_at')
            ->where(function ($query) use ($now): void {
                $query->whereNull('ends_at')->orWhere('ends_at', '>', $now);
            })
            ->whereHas('plan', function ($query): void {
                $query->where('is_active', true)->where('price', '>', 0);
            })
            ->whereHas('payments', function ($query): void {
                $query->where('status', 'paid');
            })
            ->lockForUpdate()
            ->get(['id', 'ends_at']);

        if ($existingSubscriptions->contains(fn (Subscription $item): bool => $item->ends_at === null)) {
            throw new RuntimeException('An existing paid subscription has no end date.');
        }

        $startsAt = $existingSubscriptions
            ->pluck('ends_at')
            ->filter()
            ->map(fn ($endDate): CarbonImmutable => CarbonImmutable::parse($endDate))
            ->reduce(
                fn (CarbonImmutable $latest, CarbonImmutable $endDate): CarbonImmutable => $endDate->greaterThan($latest)
                    ? $endDate
                    : $latest,
                $now
            );

        $endsAt = match ($plan->billing_interval) {
            'monthly' => $startsAt->addMonthNoOverflow(),
            'yearly' => $startsAt->addYearNoOverflow(),
            default => throw new RuntimeException('The purchased plan does not have a supported billing interval.'),
        };

        $subscription->starts_at = $startsAt;
        $subscription->ends_at = $endsAt;
        $subscription->cancelled_at = null;
        $subscription->status = 'active';
        $subscription->save();
    }

    private function cancelSubscription(?int $subscriptionId): void
    {
        if ($subscriptionId === null) {
            return;
        }

        $subscription = Subscription::query()->whereKey($subscriptionId)->lockForUpdate()->first();

        if ($subscription instanceof Subscription && $subscription->status !== 'cancelled') {
            $subscription->status = 'cancelled';
            $subscription->cancelled_at = now();
            $subscription->save();
        }
    }

    private function saveTransactionId(Payment $payment, mixed $transactionId): void
    {
        if (
            is_string($transactionId)
            && $transactionId !== ''
            && $payment->provider_transaction_id === null
        ) {
            $payment->provider_transaction_id = $transactionId;
        }
    }

    private function mapStatus(string $transactionStatus, string $fraudStatus): ?string
    {
        return match ($transactionStatus) {
            'pending', 'authorize' => 'pending',
            'settlement' => 'paid',
            'capture' => match ($fraudStatus) {
                'accept' => 'paid',
                'deny' => 'failed',
                default => 'pending',
            },
            'expire', 'cancel', 'deny' => 'failed',
            'refund', 'partial_refund', 'chargeback', 'partial_chargeback' => 'refunded',
            default => null,
        };
    }

    private function normalizeAmount(string $amount): string
    {
        if (! preg_match('/^([0-9]+)(?:\.([0-9]{1,2}))?$/', $amount, $matches)) {
            throw new InvalidArgumentException('Midtrans payment amount format is invalid.');
        }

        $whole = ltrim($matches[1], '0');
        $fraction = str_pad($matches[2] ?? '', 2, '0');

        return ($whole === '' ? '0' : $whole).'.'.$fraction;
    }
}
