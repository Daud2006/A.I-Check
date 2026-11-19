<?php

namespace Tests\Feature;

use App\Models\Plan;
use App\Models\Payment;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class AnalysisQuotaTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config(['queue.default' => 'database']);
        Storage::fake('image-analyses');
    }

    public function test_free_user_is_limited_to_five_reserved_analyses_in_the_rolling_week(): void
    {
        $user = User::factory()->create();
        DB::table('usage_records')->insert([
            [
                'user_id' => $user->id,
                'image_analysis_id' => null,
                'usage_type' => 'analysis',
                'quantity' => 4,
                'created_at' => now()->subDays(2),
            ],
            [
                'user_id' => $user->id,
                'image_analysis_id' => null,
                'usage_type' => 'analysis',
                'quantity' => 20,
                'created_at' => now()->subDays(8),
            ],
        ]);

        $upload = $this->actingAs($user)
            ->postJson(route('checker.analyses.store'), [
                'image' => UploadedFile::fake()->image('within-quota.png'),
            ])
            ->assertAccepted()
            ->assertJsonPath('quota.limit', 5)
            ->assertJsonPath('quota.used', 5)
            ->assertJsonPath('quota.remaining', 0);

        $this->postJson(route('checker.analyses.store'), [
            'image' => UploadedFile::fake()->image('over-quota.png'),
        ])
            ->assertStatus(429)
            ->assertJsonPath('quota.limit', 5)
            ->assertJsonPath('quota.used', 5);

        $this->assertDatabaseCount('image_analyses', 1);
        $this->assertDatabaseCount('jobs', 1);
        $this->assertDatabaseCount('usage_records', 3);
        $this->assertSame(1, count(Storage::disk('image-analyses')->allFiles()));
    }

    public function test_admin_account_has_unlimited_analysis_quota(): void
    {
        $user = User::factory()->create();
        $user->role = User::ROLE_ADMIN;
        $user->save();

        DB::table('usage_records')->insert([
            'user_id' => $user->id,
            'image_analysis_id' => null,
            'usage_type' => 'analysis',
            'quantity' => 10,
            'created_at' => now(),
        ]);

        $this->actingAs($user)
            ->postJson(route('checker.analyses.store'), [
                'image' => UploadedFile::fake()->image('admin-unlimited.png'),
            ])
            ->assertAccepted()
            ->assertJsonPath('quota.limit', null)
            ->assertJsonPath('quota.used', 11)
            ->assertJsonPath('quota.remaining', null);

        $this->assertDatabaseHas('jobs', ['queue' => 'default']);
        $this->actingAs($user)
            ->get(route('checker'))
            ->assertOk()
            ->assertSee('Kuota analisis: tanpa batas');
    }

    public function test_entitled_paid_plan_applies_its_quota_and_uses_premium_queue(): void
    {
        $user = User::factory()->create();
        $plan = Plan::query()->create([
            'name' => 'Quota Premium',
            'slug' => 'quota-premium',
            'price' => 29000,
            'currency' => 'IDR',
            'billing_interval' => 'monthly',
            'analysis_limit' => 2,
            'is_active' => true,
        ]);
        $subscription = new Subscription([
            'plan_id' => $plan->id,
            'starts_at' => now()->subDay(),
            'ends_at' => now()->addMonth(),
        ]);
        $subscription->status = 'active';
        $user->subscriptions()->save($subscription);
        $payment = new Payment([
            'provider' => 'midtrans',
            'provider_transaction_id' => 'quota-payment-'.$user->id,
            'amount' => 29000,
            'currency' => 'IDR',
            'paid_at' => now(),
        ]);
        $payment->status = 'paid';
        $payment->subscription()->associate($subscription);
        $user->payments()->save($payment);

        $this->actingAs($user)
            ->postJson(route('checker.analyses.store'), [
                'image' => UploadedFile::fake()->image('premium.png'),
            ])
            ->assertAccepted()
            ->assertJsonPath('quota.limit', 2)
            ->assertJsonPath('quota.premium', true);

        $this->assertDatabaseHas('jobs', ['queue' => 'premium']);
    }

    public function test_premium_plan_with_null_limit_is_unlimited(): void
    {
        $user = User::factory()->create();
        $plan = Plan::query()->create([
            'name' => 'Unlimited Premium',
            'slug' => 'unlimited-premium',
            'price' => 29000,
            'currency' => 'IDR',
            'billing_interval' => 'monthly',
            'analysis_limit' => null,
            'is_active' => true,
        ]);
        $subscription = new Subscription([
            'plan_id' => $plan->id,
            'starts_at' => now()->subDay(),
            'ends_at' => now()->addMonth(),
        ]);
        $subscription->status = 'active';
        $user->subscriptions()->save($subscription);
        $payment = new Payment([
            'provider' => 'midtrans',
            'provider_transaction_id' => 'unlimited-payment-'.$user->id,
            'amount' => 29000,
            'currency' => 'IDR',
            'paid_at' => now(),
        ]);
        $payment->status = 'paid';
        $payment->subscription()->associate($subscription);
        $user->payments()->save($payment);

        $this->actingAs($user)
            ->get(route('checker'))
            ->assertOk()
            ->assertSee('Kuota analisis: tanpa batas');
    }

    public function test_unpaid_subscription_does_not_grant_premium_quota_or_queue_priority(): void
    {
        $user = User::factory()->create();
        $plan = Plan::query()->create([
            'name' => 'Unpaid Premium',
            'slug' => 'unpaid-premium',
            'price' => 29000,
            'currency' => 'IDR',
            'billing_interval' => 'monthly',
            'analysis_limit' => 2,
            'is_active' => true,
        ]);
        $subscription = new Subscription([
            'plan_id' => $plan->id,
            'starts_at' => now()->subDay(),
            'ends_at' => now()->addMonth(),
        ]);
        $subscription->status = 'active';
        $user->subscriptions()->save($subscription);
        $payment = new Payment([
            'provider' => 'midtrans',
            'amount' => 29000,
            'currency' => 'IDR',
        ]);
        $payment->status = 'pending';
        $payment->subscription()->associate($subscription);
        $user->payments()->save($payment);

        $this->actingAs($user)
            ->postJson(route('checker.analyses.store'), [
                'image' => UploadedFile::fake()->image('unpaid-premium.png'),
            ])
            ->assertAccepted()
            ->assertJsonPath('quota.limit', 5)
            ->assertJsonPath('quota.premium', false);

        $this->assertDatabaseHas('jobs', ['queue' => 'default']);
        $this->assertDatabaseMissing('jobs', ['queue' => 'premium']);
    }
}
