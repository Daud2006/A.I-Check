<?php

namespace Tests\Feature;

use App\Models\Payment;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request as ClientRequest;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class BillingAndTransactionsTest extends TestCase
{
    use RefreshDatabase;

    private const SERVER_KEY = 'SB-Mid-server-test-key';

    private int $planSequence = 0;

    public function test_plan_catalog_uses_active_database_prices_only(): void
    {
        $this->createPlan();
        $this->createPlan('Disabled Plan', 'disabled-plan', false);
        $this->createPlan('Unsupported Currency', 'usd-plan', true, 'USD');

        $this->get(route('plans.index'))
            ->assertOk()
            ->assertSee('Premium Bulanan')
            ->assertSee('IDR 29.000,00')
            ->assertDontSee('Disabled Plan')
            ->assertDontSee('Unsupported Currency');
    }

    public function test_checkout_uses_database_price_and_creates_pending_records_until_webhook(): void
    {
        config(['midtrans.server_key' => self::SERVER_KEY, 'midtrans.is_production' => false]);
        Http::fake([
            'https://app.sandbox.midtrans.com/snap/v1/transactions' => Http::response([
                'token' => 'snap-token-test',
                'redirect_url' => 'https://app.sandbox.midtrans.com/snap/v4/redirection/test',
                'transaction_id' => 'midtrans-transaction-test',
            ], 201),
        ]);
        $user = User::factory()->create();
        $plan = $this->createPlan();

        $response = $this->actingAs($user)->post(route('checkout.store', $plan->id), [
            'amount' => 1,
            'price' => 1,
        ]);

        $payment = Payment::query()->sole();
        $subscription = Subscription::query()->sole();

        $response->assertRedirect('https://app.sandbox.midtrans.com/snap/v4/redirection/test');
        $this->assertSame('pending', $payment->status);
        $this->assertSame('pending', $subscription->status);
        $this->assertSame('29000.00', $payment->amount);
        $this->assertSame('midtrans-transaction-test', $payment->provider_transaction_id);
        $this->assertSame($subscription->id, $payment->subscription_id);

        Http::assertSent(function (ClientRequest $request): bool {
            $payload = $request->data();

            return $request->url() === 'https://app.sandbox.midtrans.com/snap/v1/transactions'
                && $request->hasHeader('Authorization')
                && $payload['transaction_details']['gross_amount'] === 29000.0
                && $payload['item_details'][0]['price'] === 29000.0
                && $payload['transaction_details']['order_id'] === 'AIC-PAY-1';
        });
    }

    public function test_invalid_inactive_or_unsupported_plans_cannot_be_checked_out(): void
    {
        config(['midtrans.server_key' => self::SERVER_KEY]);
        Http::preventStrayRequests();
        $user = User::factory()->create();
        $inactive = $this->createPlan('Inactive Plan', 'inactive-plan', false);
        $usd = $this->createPlan('USD Plan', 'usd-plan', true, 'USD');

        $this->actingAs($user)
            ->post(route('checkout.store', $inactive->id))
            ->assertNotFound();
        $this->post(route('checkout.store', $usd->id))
            ->assertNotFound();
        $this->post('/checkout/999999')
            ->assertNotFound();

        $this->assertDatabaseCount('payments', 0);
        $this->assertDatabaseCount('subscriptions', 0);
    }

    public function test_checkout_requires_configuration_without_creating_payment_records(): void
    {
        config(['midtrans.server_key' => null]);
        $user = User::factory()->create();
        $plan = $this->createPlan();

        $this->actingAs($user)
            ->from(route('plans.index'))
            ->post(route('checkout.store', $plan->id))
            ->assertRedirect(route('plans.index'))
            ->assertSessionHasErrors('checkout');

        $this->assertDatabaseCount('payments', 0);
        $this->assertDatabaseCount('subscriptions', 0);
    }

    public function test_valid_settlement_notification_activates_subscription_once(): void
    {
        config(['midtrans.server_key' => self::SERVER_KEY]);
        [$payment, $subscription] = $this->createPendingPayment();
        $notification = $this->notification($payment, 'settlement');

        $this->postJson(route('webhooks.midtrans'), $notification)->assertOk();

        $payment->refresh();
        $subscription->refresh();
        $this->assertSame('paid', $payment->status);
        $this->assertNotNull($payment->paid_at);
        $this->assertSame('active', $subscription->status);
        $this->assertSame(now()->toDateString(), $subscription->starts_at->toDateString());
        $this->assertSame(now()->addMonthNoOverflow()->toDateString(), $subscription->ends_at->toDateString());

        $paidAt = $payment->paid_at->toDateTimeString();
        $this->postJson(route('webhooks.midtrans'), $notification)->assertOk();
        $this->assertSame($paidAt, $payment->fresh()->paid_at->toDateTimeString());
        $this->assertDatabaseCount('subscriptions', 1);
        $this->assertDatabaseCount('payments', 1);
    }

    public function test_paid_renewal_is_queued_after_the_current_paid_subscription(): void
    {
        config(['midtrans.server_key' => self::SERVER_KEY]);
        $user = User::factory()->create();
        $existingPlan = $this->createPlan('Existing Premium', 'existing-premium');
        $existing = new Subscription([
            'starts_at' => now()->subDays(20),
            'ends_at' => now()->addDays(10),
        ]);
        $existing->plan()->associate($existingPlan);
        $existing->status = 'active';
        $user->subscriptions()->save($existing);
        $existingPayment = new Payment([
            'provider' => 'midtrans',
            'amount' => $existingPlan->price,
            'currency' => $existingPlan->currency,
            'paid_at' => now()->subDays(20),
        ]);
        $existingPayment->status = 'paid';
        $existingPayment->user()->associate($user);
        $existingPayment->subscription()->associate($existing);
        $existingPayment->save();

        [$renewal, $renewalSubscription] = $this->createPendingPayment($user);
        $this->postJson(route('webhooks.midtrans'), $this->notification($renewal, 'settlement'))->assertOk();

        $this->assertSame(
            $existing->ends_at->toDateTimeString(),
            $renewalSubscription->fresh()->starts_at->toDateTimeString()
        );
        $this->assertSame('active', $existing->fresh()->status);
        $this->assertSame('active', $renewalSubscription->fresh()->status);
        $this->assertSame('paid', $renewal->fresh()->status);
    }

    public function test_invalid_webhook_signature_is_rejected_without_changing_records(): void
    {
        config(['midtrans.server_key' => self::SERVER_KEY]);
        [$payment, $subscription] = $this->createPendingPayment();
        $notification = $this->notification($payment, 'settlement');
        $notification['signature_key'] = str_repeat('0', 128);

        $this->postJson(route('webhooks.midtrans'), $notification)
            ->assertUnauthorized();

        $this->assertSame('pending', $payment->fresh()->status);
        $this->assertSame('pending', $subscription->fresh()->status);
    }

    public function test_webhook_amount_must_match_the_record_even_when_signature_is_valid(): void
    {
        config(['midtrans.server_key' => self::SERVER_KEY]);
        [$payment, $subscription] = $this->createPendingPayment();
        $notification = $this->notification($payment, 'settlement');
        $notification['gross_amount'] = '1.00';
        $notification['signature_key'] = hash(
            'sha512',
            $notification['order_id'].$notification['status_code'].$notification['gross_amount'].self::SERVER_KEY
        );

        $this->postJson(route('webhooks.midtrans'), $notification)->assertUnprocessable();

        $this->assertSame('pending', $payment->fresh()->status);
        $this->assertSame('pending', $subscription->fresh()->status);
    }

    public function test_pending_failed_and_expired_notifications_never_grant_premium(): void
    {
        config(['midtrans.server_key' => self::SERVER_KEY]);

        foreach ([
            ['pending', 'pending', 'pending'],
            ['deny', 'failed', 'cancelled'],
            ['expire', 'failed', 'cancelled'],
            ['cancel', 'failed', 'cancelled'],
        ] as [$providerStatus, $expectedPayment, $expectedSubscription]) {
            [$payment, $subscription] = $this->createPendingPayment();

            $this->postJson(
                route('webhooks.midtrans'),
                $this->notification($payment, $providerStatus)
            )->assertOk();

            $this->assertSame($expectedPayment, $payment->fresh()->status);
            $this->assertSame($expectedSubscription, $subscription->fresh()->status);
        }
    }

    public function test_capture_only_activates_after_midtrans_accepts_fraud_status(): void
    {
        config(['midtrans.server_key' => self::SERVER_KEY]);
        [$challengePayment, $challengeSubscription] = $this->createPendingPayment();
        $this->postJson(
            route('webhooks.midtrans'),
            $this->notification($challengePayment, 'capture', 'challenge')
        )->assertOk();
        $this->assertSame('pending', $challengePayment->fresh()->status);
        $this->assertSame('pending', $challengeSubscription->fresh()->status);

        [$acceptedPayment, $acceptedSubscription] = $this->createPendingPayment();
        $this->postJson(
            route('webhooks.midtrans'),
            $this->notification($acceptedPayment, 'capture', 'accept')
        )->assertOk();
        $this->assertSame('paid', $acceptedPayment->fresh()->status);
        $this->assertSame('active', $acceptedSubscription->fresh()->status);
    }

    public function test_refund_revokes_the_related_paid_subscription(): void
    {
        config(['midtrans.server_key' => self::SERVER_KEY]);
        [$payment, $subscription] = $this->createPendingPayment();
        $this->postJson(route('webhooks.midtrans'), $this->notification($payment, 'settlement'))->assertOk();

        $this->postJson(route('webhooks.midtrans'), $this->notification($payment, 'refund'))->assertOk();

        $this->assertSame('refunded', $payment->fresh()->status);
        $this->assertSame('cancelled', $subscription->fresh()->status);
        $this->assertNotNull($subscription->fresh()->cancelled_at);
    }

    public function test_user_transaction_history_is_paginated_and_private_to_owner(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        [$ownedPayment] = $this->createPendingPayment($owner);
        [$otherPayment] = $this->createPendingPayment($other);

        $this->actingAs($owner)
            ->get(route('transactions.index'))
            ->assertOk()
            ->assertSee('#'.$ownedPayment->id)
            ->assertDontSee('#'.$otherPayment->id);

        $this->get(route('transactions.show', $ownedPayment->id))->assertOk();
        $this->get(route('transactions.show', $otherPayment->id))->assertNotFound();
        $this->get(route('transactions.show', 999999))->assertNotFound();
    }

    public function test_user_transaction_history_paginates_newest_records(): void
    {
        $owner = User::factory()->create();

        for ($index = 0; $index < 16; $index++) {
            $this->createPendingPayment($owner);
        }

        $response = $this->actingAs($owner)->get(route('transactions.index', ['page' => 2]));

        $response->assertOk()
            ->assertViewHas('payments', fn ($payments): bool => $payments->currentPage() === 2
                && $payments->perPage() === 15
                && $payments->count() === 1);
    }

    public function test_empty_user_transaction_history_shows_an_informative_state(): void
    {
        $this->actingAs(User::factory()->create())
            ->get(route('transactions.index'))
            ->assertOk()
            ->assertSee('Belum ada transaksi')
            ->assertSee(route('plans.index'));
    }

    public function test_guests_are_redirected_and_admin_can_search_filter_and_view_transactions(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
        $owner = User::factory()->create([
            'name' => 'Admin Payment Search Owner',
            'email' => 'payment-owner@example.test',
        ]);
        [$payment] = $this->createPendingPayment($owner);

        $this->get(route('transactions.index'))->assertRedirect(route('auth'));
        $this->get(route('admin.transactions.index'))->assertRedirect(route('auth'));

        $this->actingAs($admin)
            ->get(route('admin.transactions.index', [
                'search' => 'payment-owner@example.test',
                'status' => 'pending',
                'date_from' => now()->toDateString(),
                'date_to' => now()->toDateString(),
            ]))
            ->assertOk()
            ->assertSee('#'.$payment->id)
            ->assertSee('value="payment-owner@example.test"', false)
            ->assertSee('value="pending" selected', false);

        $this->get(route('admin.transactions.show', $payment->id))
            ->assertOk()
            ->assertSee('Admin Payment Search Owner')
            ->assertSee('Premium '.$owner->id.' 1');

        $this->actingAs(User::factory()->create())
            ->get(route('admin.transactions.index'))
            ->assertForbidden();
        $this->get(route('admin.transactions.show', $payment->id))->assertForbidden();

        $this->actingAs($admin)
            ->get(route('admin.transactions.index', ['search' => (string) $payment->id]))
            ->assertOk()
            ->assertSee('#'.$payment->id);
    }

    public function test_midtrans_session_failure_does_not_activate_the_subscription(): void
    {
        config(['midtrans.server_key' => self::SERVER_KEY, 'midtrans.is_production' => false]);
        Http::fake([
            'https://app.sandbox.midtrans.com/snap/v1/transactions' => Http::response([
                'message' => 'provider unavailable',
            ], 503),
        ]);
        $user = User::factory()->create();
        $plan = $this->createPlan();

        $this->actingAs($user)
            ->from(route('plans.index'))
            ->post(route('checkout.store', $plan->id))
            ->assertRedirect(route('plans.index'))
            ->assertSessionHasErrors('checkout');

        $this->assertSame('failed', Payment::query()->sole()->status);
        $this->assertSame('cancelled', Subscription::query()->sole()->status);
    }

    public function test_duplicate_and_out_of_order_notifications_do_not_downgrade_payment_state(): void
    {
        config(['midtrans.server_key' => self::SERVER_KEY]);
        [$payment, $subscription] = $this->createPendingPayment();

        $this->postJson(route('webhooks.midtrans'), $this->notification($payment, 'settlement'))->assertOk();
        $this->postJson(route('webhooks.midtrans'), $this->notification($payment, 'pending'))->assertOk();
        $this->postJson(route('webhooks.midtrans'), $this->notification($payment, 'deny'))->assertOk();

        $this->assertSame('paid', $payment->fresh()->status);
        $this->assertSame('active', $subscription->fresh()->status);
        $this->assertDatabaseCount('subscriptions', 1);
    }

    public function test_verified_settlement_recovers_a_payment_previously_marked_expired(): void
    {
        config(['midtrans.server_key' => self::SERVER_KEY]);
        [$payment, $subscription] = $this->createPendingPayment();

        $this->postJson(route('webhooks.midtrans'), $this->notification($payment, 'expire'))->assertOk();
        $this->assertSame('failed', $payment->fresh()->status);
        $this->assertSame('cancelled', $subscription->fresh()->status);

        $this->postJson(route('webhooks.midtrans'), $this->notification($payment, 'settlement'))->assertOk();
        $this->assertSame('paid', $payment->fresh()->status);
        $this->assertSame('active', $subscription->fresh()->status);
    }

    private function createPlan(
        string $name = 'Premium Bulanan',
        string $slug = 'premium-monthly',
        bool $active = true,
        string $currency = 'IDR',
        string $interval = 'monthly',
        string $price = '29000.00',
    ): Plan {
        return Plan::query()->create([
            'name' => $name,
            'slug' => $slug,
            'price' => $price,
            'currency' => $currency,
            'billing_interval' => $interval,
            'analysis_limit' => null,
            'is_active' => $active,
        ]);
    }

    /**
     * @return array{0: Payment, 1: Subscription}
     */
    private function createPendingPayment(?User $user = null): array
    {
        $user ??= User::factory()->create();
        $sequence = ++$this->planSequence;
        $plan = $this->createPlan(
            'Premium '.$user->id.' '.$sequence,
            'premium-'.$user->id.'-'.$sequence
        );
        $subscription = new Subscription;
        $subscription->plan()->associate($plan);
        $subscription->status = 'pending';
        $user->subscriptions()->save($subscription);

        $payment = new Payment([
            'provider' => 'midtrans',
            'amount' => $plan->price,
            'currency' => $plan->currency,
        ]);
        $payment->status = 'pending';
        $payment->user()->associate($user);
        $payment->subscription()->associate($subscription);
        $payment->save();

        return [$payment, $subscription];
    }

    /**
     * @return array<string, string>
     */
    private function notification(
        Payment $payment,
        string $transactionStatus,
        string $fraudStatus = 'accept',
    ): array {
        $orderId = 'AIC-PAY-'.$payment->id;
        $statusCode = '200';
        $grossAmount = number_format((float) $payment->amount, 2, '.', '');

        return [
            'order_id' => $orderId,
            'status_code' => $statusCode,
            'gross_amount' => $grossAmount,
            'signature_key' => hash('sha512', $orderId.$statusCode.$grossAmount.self::SERVER_KEY),
            'transaction_status' => $transactionStatus,
            'transaction_id' => 'midtrans-transaction-'.$payment->id,
            'fraud_status' => $fraudStatus,
        ];
    }
}
