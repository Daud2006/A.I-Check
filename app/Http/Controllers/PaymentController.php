<?php

namespace App\Http\Controllers;

use App\Models\Payment;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\User;
use App\Services\MidtransGateway;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

class PaymentController extends Controller
{
    public function checkout(Request $request, MidtransGateway $midtrans): JsonResponse
    {
        $validated = $request->validate([
            'plan' => ['required', 'string', 'in:premium-monthly,premium-yearly'],
        ]);

        $plan = Plan::query()
            ->where('slug', $validated['plan'])
            ->where('is_active', true)
            ->where('price', '>', 0)
            ->first();

        if (! $plan || ! in_array($plan->billing_interval, ['monthly', 'yearly'], true)) {
            return response()->json([
                'message' => 'Paket Premium yang dipilih belum tersedia.',
            ], 422);
        }

        $user = $request->user();
        $orderId = 'AIC-'.str_replace('-', '', (string) Str::uuid());

        $payment = DB::transaction(function () use ($plan, $user, $orderId): Payment {
            $subscription = new Subscription(['plan_id' => $plan->id]);
            $subscription->status = 'pending';
            $user->subscriptions()->save($subscription);

            $payment = new Payment([
                'provider' => 'midtrans',
                'order_id' => $orderId,
                'amount' => $plan->price,
                'currency' => $plan->currency,
            ]);
            $payment->status = 'pending';
            $payment->subscription()->associate($subscription);
            $user->payments()->save($payment);

            return $payment;
        });

        try {
            $snap = $midtrans->createSnapTransaction([
                'transaction_details' => [
                    'order_id' => $payment->order_id,
                    'gross_amount' => (float) $plan->price,
                ],
                'item_details' => [[
                    'id' => $plan->slug,
                    'price' => (float) $plan->price,
                    'quantity' => 1,
                    'name' => $plan->name,
                ]],
                'customer_details' => [
                    'first_name' => $user->name,
                    'email' => $user->email,
                ],
            ]);
        } catch (Throwable $exception) {
            $this->failPendingPayment($payment->getKey());
            Log::warning('Midtrans Snap transaction creation failed.', [
                'payment_id' => $payment->getKey(),
                'exception_class' => $exception::class,
            ]);

            return response()->json([
                'message' => 'Pembayaran belum dapat dimulai. Silakan coba kembali.',
            ], 502);
        }

        if (! isset($snap->token) || ! is_string($snap->token) || $snap->token === '') {
            $this->failPendingPayment($payment->getKey());
            Log::warning('Midtrans Snap response did not contain a token.', [
                'payment_id' => $payment->getKey(),
            ]);

            return response()->json([
                'message' => 'Pembayaran belum dapat dimulai. Silakan coba kembali.',
            ], 502);
        }

        return response()->json([
            'payment_id' => $payment->getKey(),
            'snap_token' => $snap->token,
            'status_url' => route('payments.status', ['paymentId' => $payment->getKey()]),
            'status' => 'pending',
        ], 201);
    }

    public function status(Request $request, int $paymentId, MidtransGateway $midtrans): JsonResponse
    {
        $payment = $request->user()->payments()
            ->with(['subscription.plan'])
            ->whereKey($paymentId)
            ->firstOrFail();

        if ($payment->provider !== 'midtrans' || ! is_string($payment->order_id) || $payment->order_id === '') {
            return response()->json([
                'status' => $payment->status,
                'message' => 'Transaksi ini tidak memiliki referensi Midtrans yang valid.',
            ], 409);
        }

        if ($payment->status === 'paid') {
            return $this->statusResponse($payment);
        }

        try {
            $providerStatus = $midtrans->transactionStatus((string) $payment->order_id);
        } catch (Throwable $exception) {
            Log::warning('Midtrans payment status lookup failed.', [
                'payment_id' => $payment->getKey(),
                'exception_class' => $exception::class,
            ]);

            return response()->json([
                'status' => $payment->status,
                'message' => 'Status pembayaran belum dapat diperiksa. Silakan coba kembali.',
            ], 503);
        }

        if (! $this->providerStatusMatchesPayment($providerStatus, $payment)) {
            Log::warning('Midtrans status did not match the stored payment.', [
                'payment_id' => $payment->getKey(),
            ]);

            return response()->json([
                'status' => $payment->status,
                'message' => 'Status pembayaran belum dapat diverifikasi. Silakan coba kembali.',
            ], 409);
        }

        try {
            $payment = $this->applyVerifiedStatus($payment, $providerStatus);
        } catch (Throwable $exception) {
            Log::error('Unable to apply verified Midtrans status.', [
                'payment_id' => $payment->getKey(),
                'exception_class' => $exception::class,
            ]);

            return response()->json([
                'status' => $payment->fresh()->status,
                'message' => 'Status pembayaran belum dapat diperbarui. Silakan coba kembali.',
            ], 500);
        }

        return $this->statusResponse($payment);
    }

    public function notification(Request $request, MidtransGateway $midtrans): JsonResponse
    {
        $notification = $request->all();
        if (! $midtrans->isValidNotificationSignature($notification)) {
            return response()->json(['message' => 'Notifikasi pembayaran tidak valid.'], 403);
        }

        $orderId = $notification['order_id'];
        $transactionId = $notification['transaction_id'] ?? null;
        if (! is_string($transactionId) || $transactionId === '') {
            return response()->json(['message' => 'Identitas transaksi tidak valid.'], 422);
        }

        $payment = Payment::query()
            ->with(['subscription.plan'])
            ->where('provider', 'midtrans')
            ->where('order_id', $orderId)
            ->first();

        if (! $payment) {
            return response()->json(['message' => 'Transaksi tidak ditemukan.'], 404);
        }

        try {
            $providerStatus = $midtrans->transactionStatus($orderId);
        } catch (Throwable $exception) {
            Log::warning('Midtrans notification status lookup failed.', [
                'payment_id' => $payment->getKey(),
                'exception_class' => $exception::class,
            ]);

            return response()->json(['message' => 'Status transaksi belum dapat diverifikasi.'], 503);
        }

        if (
            ! $this->providerStatusMatchesPayment($providerStatus, $payment)
            || (string) ($providerStatus->transaction_id ?? '') !== $transactionId
        ) {
            Log::warning('Midtrans notification did not match the verified transaction.', [
                'payment_id' => $payment->getKey(),
            ]);

            return response()->json(['message' => 'Notifikasi tidak cocok dengan transaksi.'], 422);
        }

        try {
            $payment = $this->applyVerifiedStatus($payment, $providerStatus);
        } catch (Throwable $exception) {
            Log::error('Unable to apply verified Midtrans notification.', [
                'payment_id' => $payment->getKey(),
                'exception_class' => $exception::class,
            ]);

            return response()->json(['message' => 'Notifikasi belum dapat diproses.'], 500);
        }

        return response()->json(['status' => $payment->status]);
    }

    private function providerStatusMatchesPayment(object $providerStatus, Payment $payment): bool
    {
        $subscription = $payment->subscription;
        $plan = $subscription?->plan;
        $transactionId = $providerStatus->transaction_id ?? null;
        $orderId = $providerStatus->order_id ?? null;
        $grossAmount = $providerStatus->gross_amount ?? null;
        $currency = $providerStatus->currency ?? null;

        return $payment->provider === 'midtrans'
            && is_string($payment->order_id)
            && is_string($orderId)
            && hash_equals($payment->order_id, $orderId)
            && is_string($transactionId)
            && $transactionId !== ''
            && $this->sameAmount($payment->amount, $grossAmount)
            && ($currency === null || (is_string($currency) && $currency === $payment->currency))
            && $subscription instanceof Subscription
            && $subscription->user_id === $payment->user_id
            && $plan instanceof Plan
            && $subscription->plan_id === $plan->getKey()
            && (int) $payment->user_id > 0;
    }

    private function sameAmount(mixed $expected, mixed $actual): bool
    {
        $expectedCents = $this->amountInCents($expected);
        $actualCents = $this->amountInCents($actual);

        return $expectedCents !== null && $expectedCents === $actualCents;
    }

    private function amountInCents(mixed $amount): ?int
    {
        if (
            (! is_string($amount) && ! is_int($amount) && ! is_float($amount))
            || ! is_numeric($amount)
        ) {
            return null;
        }

        $amount = (string) $amount;
        if (! preg_match('/^\d+(?:\.\d{1,2})?$/D', $amount)) {
            return null;
        }

        [$whole, $fraction] = array_pad(explode('.', $amount, 2), 2, '0');

        return ((int) $whole * 100) + (int) str_pad($fraction, 2, '0');
    }

    private function applyVerifiedStatus(Payment $payment, object $providerStatus): Payment
    {
        $transactionStatus = strtolower((string) ($providerStatus->transaction_status ?? ''));
        $fraudStatus = strtolower((string) ($providerStatus->fraud_status ?? ''));
        $paid = $transactionStatus === 'settlement'
            || ($transactionStatus === 'capture' && $fraudStatus === 'accept');
        $failed = in_array($transactionStatus, ['expire', 'cancel', 'deny'], true)
            || ($transactionStatus === 'capture' && $fraudStatus === 'deny');

        return DB::transaction(function () use ($payment, $providerStatus, $paid, $failed): Payment {
            $lockedPayment = Payment::query()->lockForUpdate()->findOrFail($payment->getKey());
            $subscription = Subscription::query()
                ->lockForUpdate()
                ->findOrFail($lockedPayment->subscription_id);

            if ($subscription->user_id !== $lockedPayment->user_id) {
                throw new \LogicException('The payment and subscription owners do not match.');
            }

            $transactionId = (string) $providerStatus->transaction_id;
            $duplicateTransaction = Payment::query()
                ->where('provider', 'midtrans')
                ->where('provider_transaction_id', $transactionId)
                ->whereKeyNot($lockedPayment->getKey())
                ->exists();
            if ($duplicateTransaction) {
                throw new \LogicException('The provider transaction is already linked to another payment.');
            }

            if ($lockedPayment->provider_transaction_id !== null
                && $lockedPayment->provider_transaction_id !== $transactionId) {
                throw new \LogicException('The provider transaction ID changed for this payment.');
            }

            if ($lockedPayment->status === 'paid') {
                return $lockedPayment;
            }

            if ($paid) {
                $user = User::query()->lockForUpdate()->findOrFail($lockedPayment->user_id);
                $now = now();
                $latestEnd = Subscription::query()
                    ->where('user_id', $user->getKey())
                    ->where('status', 'active')
                    ->whereNull('cancelled_at')
                    ->where('ends_at', '>', $now)
                    ->whereKeyNot($subscription->getKey())
                    ->whereHas('plan', fn ($query) => $query->where('price', '>', 0))
                    ->max('ends_at');

                $startsAt = $latestEnd
                    ? Carbon::parse($latestEnd)
                    : Carbon::parse($now);
                $endsAt = match ($subscription->plan()->value('billing_interval')) {
                    'monthly' => $startsAt->copy()->addMonthNoOverflow(),
                    'yearly' => $startsAt->copy()->addYearNoOverflow(),
                    default => throw new \LogicException('The Premium plan interval is invalid.'),
                };

                $subscription->starts_at = $startsAt;
                $subscription->ends_at = $endsAt;
                $subscription->status = 'active';
                $subscription->cancelled_at = null;
                $subscription->save();

                $lockedPayment->provider_transaction_id = $transactionId;
                $lockedPayment->status = 'paid';
                $lockedPayment->paid_at = $now;
                $lockedPayment->save();

                return $lockedPayment;
            }

            if ($failed) {
                if ($lockedPayment->status !== 'paid') {
                    $lockedPayment->provider_transaction_id = $transactionId;
                    $lockedPayment->status = 'failed';
                    $lockedPayment->save();

                    if ($subscription->status === 'pending') {
                        $subscription->status = 'cancelled';
                        $subscription->cancelled_at = now();
                        $subscription->save();
                    }
                }

                return $lockedPayment;
            }

            if ($lockedPayment->status === 'pending') {
                $lockedPayment->provider_transaction_id = $transactionId;
                $lockedPayment->status = 'pending';
                $lockedPayment->save();
            }

            return $lockedPayment;
        });
    }

    private function failPendingPayment(int $paymentId): void
    {
        DB::transaction(function () use ($paymentId): void {
            $payment = Payment::query()->lockForUpdate()->findOrFail($paymentId);
            if ($payment->status !== 'pending') {
                return;
            }

            $payment->status = 'failed';
            $payment->save();

            if ($payment->subscription_id) {
                $subscription = Subscription::query()->lockForUpdate()->findOrFail($payment->subscription_id);
                if ($subscription->status === 'pending') {
                    $subscription->status = 'cancelled';
                    $subscription->cancelled_at = now();
                    $subscription->save();
                }
            }
        });
    }

    private function statusResponse(Payment $payment): JsonResponse
    {
        $payment->refresh();
        $messages = [
            'pending' => 'Pembayaran sedang menunggu konfirmasi.',
            'paid' => 'Pembayaran berhasil. Premium aktif sesuai masa langganan.',
            'failed' => 'Pembayaran gagal, dibatalkan, atau kedaluwarsa. Akun tetap Free.',
            'refunded' => 'Pembayaran telah dikembalikan.',
        ];

        return response()->json([
            'status' => $payment->status,
            'message' => $messages[$payment->status] ?? 'Status pembayaran belum tersedia.',
        ]);
    }
}
