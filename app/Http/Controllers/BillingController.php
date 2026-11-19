<?php

namespace App\Http\Controllers;

use App\Models\Payment;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\User;
use App\Services\AnalysisQuotaService;
use App\Services\MidtransSnapClient;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;
use RuntimeException;

class BillingController extends Controller
{
    public function plans(Request $request, AnalysisQuotaService $quota): View
    {
        $plans = Plan::query()
            ->where('is_active', true)
            ->where('price', '>', 0)
            ->where('currency', 'IDR')
            ->whereIn('billing_interval', ['monthly', 'yearly'])
            ->orderBy('price')
            ->orderBy('name')
            ->get(['id', 'name', 'description', 'price', 'currency', 'billing_interval', 'analysis_limit']);

        $user = $request->user();

        return view('plans.index', [
            'plans' => $plans,
            'account' => $user ? $quota->accountSummary($user) : null,
            'freeLimit' => AnalysisQuotaService::FREE_WEEKLY_LIMIT,
            'midtransConfigured' => is_string(config('midtrans.server_key'))
                && trim((string) config('midtrans.server_key')) !== '',
        ]);
    }

    public function checkoutSummary(Plan $plan, MidtransSnapClient $midtrans): View
    {
        try {
            $midtrans->assertConfigured();
            $paymentAvailable = true;
        } catch (RuntimeException) {
            $paymentAvailable = false;
        }

        return view('plans.checkout', [
            'plan' => $this->purchasablePlan($plan),
            'paymentAvailable' => $paymentAvailable,
            'paymentMethods' => MidtransSnapClient::PAYMENT_METHODS,
        ]);
    }

    private function purchasablePlan(Plan $plan): Plan
    {
        return Plan::query()
            ->whereKey($plan->getKey())
            ->where('is_active', true)
            ->where('price', '>', 0)
            ->where('currency', 'IDR')
            ->whereIn('billing_interval', ['monthly', 'yearly'])
            ->firstOrFail();
    }

    public function checkout(Request $request, Plan $plan, MidtransSnapClient $midtrans): RedirectResponse
    {
        $validated = $request->validate([
            'payment_method' => ['sometimes', 'required', Rule::in(array_keys(MidtransSnapClient::PAYMENT_METHODS))],
        ]);

        try {
            $midtrans->assertConfigured();
        } catch (RuntimeException) {
            return back()->withErrors([
                'checkout' => 'Pembayaran belum dikonfigurasi. Hubungi administrator.',
            ]);
        }

        $plan = $this->purchasablePlan($plan);

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
            $plan = Plan::query()->whereKey($plan->id)
                ->where('is_active', true)->where('price', '>', 0)->where('currency', 'IDR')
                ->whereIn('billing_interval', ['monthly', 'yearly'])->lockForUpdate()->firstOrFail();
            $existing = $lockedUser->payments()
                ->where('provider', 'midtrans')
                ->where('status', 'pending')
                ->whereHas('subscription', fn ($query) => $query->where('plan_id', $plan->getKey()))
                ->latest('id')
                ->first();

            if ($existing) {
                return [$existing, null];
            }

            $subscription = new Subscription;
            $subscription->plan()->associate($plan);
            $subscription->status = 'pending';
            $subscription->plan_terms = $plan->subscriptionTerms();
            $lockedUser->subscriptions()->save($subscription);

            $payment = new Payment([
                'provider' => 'midtrans',
                'amount' => $plan->price,
                'currency' => $plan->currency,
            ]);
            $payment->status = 'pending';
            $payment->environment = config('midtrans.is_production') ? 'production' : 'sandbox';
            $payment->user()->associate($lockedUser);
            $payment->subscription()->associate($subscription);
            $payment->save();

            return [$payment, $subscription];
        }, 3);

        if ($subscription === null) {
            return redirect()->route('transactions.show', $payment->getKey());
        }
        $plan = $subscription->plan;

        try {
            $checkout = $midtrans->createCheckout(
                $payment,
                $plan,
                $user,
                route('transactions.index'),
                $validated['payment_method'] ?? null
            );
        } catch (RuntimeException $exception) {
            DB::transaction(function () use ($payment, $subscription): void {
                $lockedPayment = Payment::query()->whereKey($payment->getKey())->lockForUpdate()->firstOrFail();
                if ($lockedPayment->status !== 'pending') {
                    return;
                }

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
            Payment::query()->whereKey($payment->getKey())
                ->whereNull('provider_transaction_id')
                ->update(['provider_transaction_id' => $checkout['transaction_id']]);
        }

        $request->session()->put('midtrans.checkout.'.$payment->getKey(), $checkout['redirect_url']);

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
            'checkoutUrl' => $payment->status === 'pending'
                ? $request->session()->get('midtrans.checkout.'.$payment->getKey())
                : null,
        ]);
    }
}
