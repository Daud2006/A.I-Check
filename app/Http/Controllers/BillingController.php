<?php

namespace App\Http\Controllers;

use App\Models\Payment;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\User;
use App\Services\MidtransSnapClient;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class BillingController extends Controller
{
    public function plans(): View
    {
        $plans = Plan::query()
            ->where('is_active', true)
            ->where('price', '>', 0)
            ->where('currency', 'IDR')
            ->whereIn('billing_interval', ['monthly', 'yearly'])
            ->orderBy('price')
            ->orderBy('name')
            ->get(['id', 'name', 'description', 'price', 'currency', 'billing_interval']);

        return view('plans.index', [
            'plans' => $plans,
            'midtransConfigured' => is_string(config('midtrans.server_key'))
                && trim((string) config('midtrans.server_key')) !== '',
        ]);
    }

    public function checkout(Request $request, Plan $plan, MidtransSnapClient $midtrans): RedirectResponse
    {
        try {
            $midtrans->assertConfigured();
        } catch (RuntimeException) {
            return back()->withErrors([
                'checkout' => 'Pembayaran belum dikonfigurasi. Hubungi administrator.',
            ]);
        }

        $plan = Plan::query()
            ->whereKey($plan->getKey())
            ->where('is_active', true)
            ->where('price', '>', 0)
            ->where('currency', 'IDR')
            ->whereIn('billing_interval', ['monthly', 'yearly'])
            ->firstOrFail();

        $user = $request->user();
        $hasUnboundedPaidSubscription = Subscription::query()
            ->currentlyEntitled(now())
            ->where('user_id', $user->getKey())
            ->whereNull('ends_at')
            ->exists();

        if ($hasUnboundedPaidSubscription) {
            return back()->withErrors([
                'checkout' => 'Langganan aktif Anda belum memiliki tanggal berakhir. Hubungi administrator sebelum membeli paket baru.',
            ]);
        }

        [$payment, $subscription] = DB::transaction(function () use ($plan, $user): array {
            $lockedUser = User::query()->whereKey($user->getKey())->lockForUpdate()->firstOrFail();
            $subscription = new Subscription;
            $subscription->plan()->associate($plan);
            $subscription->status = 'pending';
            $lockedUser->subscriptions()->save($subscription);

            $payment = new Payment([
                'provider' => 'midtrans',
                'amount' => $plan->price,
                'currency' => $plan->currency,
            ]);
            $payment->status = 'pending';
            $payment->user()->associate($lockedUser);
            $payment->subscription()->associate($subscription);
            $payment->save();

            return [$payment, $subscription];
        });

        try {
            $checkout = $midtrans->createCheckout(
                $payment,
                $plan,
                $user,
                route('transactions.index')
            );
        } catch (RuntimeException $exception) {
            DB::transaction(function () use ($payment, $subscription): void {
                $lockedPayment = Payment::query()->whereKey($payment->getKey())->lockForUpdate()->firstOrFail();
                $lockedPayment->status = 'failed';
                $lockedPayment->save();

                $lockedSubscription = Subscription::query()
                    ->whereKey($subscription->getKey())
                    ->lockForUpdate()
                    ->firstOrFail();
                $lockedSubscription->status = 'cancelled';
                $lockedSubscription->cancelled_at = now();
                $lockedSubscription->save();
            });

            Log::warning('Unable to create Midtrans checkout session.', [
                'payment_id' => $payment->getKey(),
                'exception_class' => $exception::class,
            ]);

            return back()->withErrors([
                'checkout' => 'Sesi pembayaran belum dapat dibuat. Silakan coba kembali beberapa saat lagi.',
            ]);
        }

        if ($checkout['transaction_id'] !== null) {
            $payment->provider_transaction_id = $checkout['transaction_id'];
            $payment->save();
        }

        return redirect()->away($checkout['redirect_url']);
    }

    public function transactions(Request $request): View
    {
        $payments = $request->user()->payments()
            ->select([
                'id',
                'user_id',
                'subscription_id',
                'provider',
                'provider_transaction_id',
                'amount',
                'currency',
                'status',
                'paid_at',
                'created_at',
            ])
            ->with('subscription.plan:id,name')
            ->latest('created_at')
            ->latest('id')
            ->paginate(15);

        return view('transactions.index', [
            'payments' => $payments,
        ]);
    }

    public function showTransaction(Request $request, int $paymentId): View
    {
        $payment = $request->user()->payments()
            ->select([
                'id',
                'user_id',
                'subscription_id',
                'provider',
                'provider_transaction_id',
                'amount',
                'currency',
                'status',
                'paid_at',
                'created_at',
                'updated_at',
            ])
            ->with('subscription.plan:id,name,billing_interval')
            ->findOrFail($paymentId);

        return view('transactions.show', [
            'payment' => $payment,
        ]);
    }
}
