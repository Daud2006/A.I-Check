<?php

namespace Tests\Feature;

use App\Models\Payment;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\User;
use App\Services\MidtransGateway;
use Carbon\Carbon;
use Database\Seeders\PlanSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

class PaymentTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'services.midtrans.server_key' => 'sandbox-test-server-key',
            'services.midtrans.client_key' => 'sandbox-test-client-key',
            'services.midtrans.is_production' => false,
        ]);
        $this->seed(PlanSeeder::class);
    }

    public function test_checkout_uses_the_database_price_and_creates_pending_records(): void
    {
        $user = User::factory()->create();
        $this->mock(MidtransGateway::class, function ($gateway): void {
            $gateway->shouldReceive('createSnapTransaction')
                ->once()
                ->with(Mockery::on(function (array $parameters): bool {
                    return $parameters['transaction_details']['gross_amount'] === 29000.0
                        && $parameters['item_details'][0]['price'] === 29000.0
                        && $parameters['item_details'][0]['id'] === 'premium-monthly';
                }))
                ->andReturn((object) ['token' => 'sandbox-snap-token']);
        });

        $response = $this->actingAs($user)
            ->postJson(route('payments.checkout'), [
                'plan' => 'premium-monthly',
                'price' => 1,
            ])
            ->assertCreated()
            ->assertJsonPath('snap_token', 'sandbox-snap-token')
            ->assertJsonPath('status', 'pending');

        $payment = Payment::query()->with('subscription')->findOrFail($response->json('payment_id'));
        $this->assertSame('midtrans', $payment->provider);
        $this->assertSame('pending', $payment->status);
        $this->assertSame(29000.0, (float) $payment->amount);
        $this->assertMatchesRegularExpression('/^AIC-[a-f0-9]{32}$/', $payment->order_id);
        $this->assertSame('pending', $payment->subscription->status);
        $this->assertFalse($user->fresh()->hasPremiumAccess());
    }

    public function test_checkout_requires_login_and_rejects_unknown_plan_slugs(): void
    {
        $this->postJson(route('payments.checkout'), ['plan' => 'premium-monthly'])
            ->assertUnauthorized();

        $this->actingAs(User::factory()->create())
            ->postJson(route('payments.checkout'), ['plan' => 'premium-custom'])
            ->assertUnprocessable();
    }

    public function test_invalid_notification_signature_cannot_activate_a_subscription(): void
    {
        $payment = $this->startCheckout(User::factory()->create());
        $gateway = Mockery::mock(MidtransGateway::class)->makePartial();
        $gateway->shouldNotReceive('transactionStatus');
        $this->app->instance(MidtransGateway::class, $gateway);

        $this->postJson(route('payments.midtrans.notifications'), [
            ...$this->notification($payment, 'settlement'),
            'signature_key' => str_repeat('0', 128),
        ])->assertForbidden();

        $this->assertSame('pending', $payment->fresh()->status);
        $this->assertSame('pending', $payment->subscription->fresh()->status);
    }

    public function test_verified_settlement_activates_monthly_premium_and_duplicate_notification_is_idempotent(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-01-31 10:00:00'));
        $user = User::factory()->create();
        $payment = $this->startCheckout($user);
        $providerStatus = $this->providerStatus($payment, 'settlement');
        $gateway = Mockery::mock(MidtransGateway::class)->makePartial();
        $gateway->shouldReceive('transactionStatus')->twice()->andReturn($providerStatus);
        $this->app->instance(MidtransGateway::class, $gateway);

        $notification = $this->notification($payment, 'settlement');
        $this->postJson(route('payments.midtrans.notifications'), $notification)
            ->assertOk()
            ->assertJsonPath('status', 'paid');
        $this->postJson(route('payments.midtrans.notifications'), $notification)
            ->assertOk()
            ->assertJsonPath('status', 'paid');

        $subscription = $payment->subscription->fresh();
        $this->assertSame('active', $subscription->status);
        $this->assertSame('2026-01-31 10:00:00', $subscription->starts_at->toDateTimeString());
        $this->assertSame('2026-02-28 10:00:00', $subscription->ends_at->toDateTimeString());
        $this->assertSame('paid', $payment->fresh()->status);
        $this->assertSame('sandbox-transaction-'.$payment->getKey(), $payment->fresh()->provider_transaction_id);
        $this->assertSame(1, $user->subscriptions()->where('status', 'active')->count());
        $this->assertTrue($user->fresh()->hasPremiumAccess());
    }

    public function test_pending_and_challenged_capture_never_activate_premium(): void
    {
        $firstPayment = $this->startCheckout(User::factory()->create());
        $secondPayment = $this->startCheckout(User::factory()->create());
        $gateway = Mockery::mock(MidtransGateway::class)->makePartial();
        $gateway->shouldReceive('transactionStatus')->twice()->andReturn(
            $this->providerStatus($firstPayment, 'pending'),
            $this->providerStatus($secondPayment, 'capture', 'challenge'),
        );
        $this->app->instance(MidtransGateway::class, $gateway);

        $this->postJson(route('payments.midtrans.notifications'), $this->notification($firstPayment, 'pending'))
            ->assertOk()
            ->assertJsonPath('status', 'pending');
        $this->postJson(route('payments.midtrans.notifications'), $this->notification($secondPayment, 'capture'))
            ->assertOk()
            ->assertJsonPath('status', 'pending');

        $this->assertFalse($firstPayment->user->fresh()->hasPremiumAccess());
        $this->assertFalse($secondPayment->user->fresh()->hasPremiumAccess());
    }

    public function test_capture_with_accepted_fraud_status_activates_premium(): void
    {
        $payment = $this->startCheckout(User::factory()->create());
        $gateway = Mockery::mock(MidtransGateway::class)->makePartial();
        $gateway->shouldReceive('transactionStatus')
            ->once()
            ->andReturn($this->providerStatus($payment, 'capture', 'accept'));
        $this->app->instance(MidtransGateway::class, $gateway);

        $this->postJson(route('payments.midtrans.notifications'), $this->notification($payment, 'capture'))
            ->assertOk()
            ->assertJsonPath('status', 'paid');

        $this->assertTrue($payment->user->fresh()->hasPremiumAccess());
    }

    public function test_expired_cancelled_and_denied_payments_remain_free(): void
    {
        foreach (['expire', 'cancel', 'deny'] as $status) {
            $payment = $this->startCheckout(User::factory()->create());
            $gateway = Mockery::mock(MidtransGateway::class)->makePartial();
            $gateway->shouldReceive('transactionStatus')->once()->andReturn($this->providerStatus($payment, $status));
            $this->app->instance(MidtransGateway::class, $gateway);

            $this->postJson(route('payments.midtrans.notifications'), $this->notification($payment, $status))
                ->assertOk()
                ->assertJsonPath('status', 'failed');

            $this->assertSame('cancelled', $payment->subscription->fresh()->status);
            $this->assertFalse($payment->user->fresh()->hasPremiumAccess());
        }
    }

    public function test_yearly_payment_extends_an_existing_premium_period_without_losing_remaining_time(): void
    {
        $user = User::factory()->create();
        $monthlyPlan = Plan::query()->where('slug', 'premium-monthly')->firstOrFail();
        $currentEnd = now()->addDays(10)->startOfSecond();
        $existing = new Subscription([
            'plan_id' => $monthlyPlan->getKey(),
            'starts_at' => now()->subMonth(),
            'ends_at' => $currentEnd,
        ]);
        $existing->status = 'active';
        $user->subscriptions()->save($existing);

        $payment = $this->startCheckout($user, 'premium-yearly');
        $gateway = Mockery::mock(MidtransGateway::class)->makePartial();
        $gateway->shouldReceive('transactionStatus')
            ->once()
            ->andReturn($this->providerStatus($payment, 'settlement'));
        $this->app->instance(MidtransGateway::class, $gateway);

        $this->postJson(route('payments.midtrans.notifications'), $this->notification($payment, 'settlement'))
            ->assertOk();

        $renewal = $payment->subscription->fresh();
        $this->assertSame($currentEnd->toDateTimeString(), $renewal->starts_at->toDateTimeString());
        $this->assertSame(
            $currentEnd->copy()->addYearNoOverflow()->toDateTimeString(),
            $renewal->ends_at->toDateTimeString(),
        );
        $this->assertTrue($user->fresh()->hasPremiumAccess());
    }

    public function test_authenticated_status_check_uses_midtrans_and_is_scoped_to_the_owner(): void
    {
        $user = User::factory()->create();
        $payment = $this->startCheckout($user);
        $gateway = Mockery::mock(MidtransGateway::class)->makePartial();
        $gateway->shouldReceive('transactionStatus')
            ->once()
            ->andReturn($this->providerStatus($payment, 'settlement'));
        $this->app->instance(MidtransGateway::class, $gateway);

        $this->actingAs($user)
            ->getJson(route('payments.status', ['paymentId' => $payment->getKey()]))
            ->assertOk()
            ->assertJsonPath('status', 'paid');

        $this->actingAs(User::factory()->create())
            ->getJson(route('payments.status', ['paymentId' => $payment->getKey()]))
            ->assertNotFound();
    }

    public function test_provider_amount_mismatch_does_not_activate_premium(): void
    {
        $payment = $this->startCheckout(User::factory()->create());
        $providerStatus = $this->providerStatus($payment, 'settlement');
        $providerStatus->gross_amount = '1.00';
        $gateway = Mockery::mock(MidtransGateway::class)->makePartial();
        $gateway->shouldReceive('transactionStatus')->once()->andReturn($providerStatus);
        $this->app->instance(MidtransGateway::class, $gateway);

        $this->postJson(route('payments.midtrans.notifications'), $this->notification($payment, 'settlement'))
            ->assertUnprocessable();

        $this->assertSame('pending', $payment->fresh()->status);
        $this->assertFalse($payment->user->fresh()->hasPremiumAccess());
    }

    public function test_subscription_modal_prices_come_from_seeded_database_plans(): void
    {
        $this->get(route('home'))
            ->assertOk()
            ->assertSee('Rp29.000')
            ->assertSee('Rp249.000')
            ->assertSee('premium-monthly')
            ->assertSee('premium-yearly')
            ->assertSee('data-client-key="sandbox-test-client-key"', false)
            ->assertDontSee('sandbox-test-server-key');
    }

    private function startCheckout(User $user, string $planSlug = 'premium-monthly'): Payment
    {
        $gateway = Mockery::mock(MidtransGateway::class);
        $gateway->shouldReceive('createSnapTransaction')
            ->once()
            ->andReturn((object) ['token' => 'sandbox-snap-token']);
        $this->app->instance(MidtransGateway::class, $gateway);

        $response = $this->actingAs($user)
            ->postJson(route('payments.checkout'), ['plan' => $planSlug])
            ->assertCreated()
            ->assertJsonPath('status', 'pending');

        return Payment::query()->with('subscription.plan')->findOrFail($response->json('payment_id'));
    }

    private function providerStatus(Payment $payment, string $status, ?string $fraudStatus = null): object
    {
        return (object) array_filter([
            'order_id' => $payment->order_id,
            'transaction_id' => 'sandbox-transaction-'.$payment->getKey(),
            'gross_amount' => number_format((float) $payment->amount, 2, '.', ''),
            'currency' => $payment->currency,
            'transaction_status' => $status,
            'fraud_status' => $fraudStatus,
        ], static fn ($value): bool => $value !== null);
    }

    private function notification(Payment $payment, string $status): array
    {
        $statusCode = '200';
        $grossAmount = number_format((float) $payment->amount, 2, '.', '');

        return [
            'order_id' => $payment->order_id,
            'transaction_id' => 'sandbox-transaction-'.$payment->getKey(),
            'transaction_status' => $status,
            'status_code' => $statusCode,
            'gross_amount' => $grossAmount,
            'signature_key' => hash(
                'sha512',
                $payment->order_id.$statusCode.$grossAmount.'sandbox-test-server-key',
            ),
        ];
    }
}
