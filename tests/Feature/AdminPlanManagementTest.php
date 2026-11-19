<?php

namespace Tests\Feature;

use App\Models\Payment;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminPlanManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_list_create_edit_and_toggle_plans(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
        $plan = $this->createPlan('Starter', 'starter');

        $this->actingAs($admin)
            ->get(route('admin.plans.index'))
            ->assertOk()
            ->assertSee('Starter')
            ->assertSee(route('admin.plans.index'), false);

        $this->get(route('admin.plans.create'))->assertOk();

        $this->post(route('admin.plans.store'), [
            'name' => 'Creator Annual',
            'slug' => 'creator-annual',
            'description' => 'Annual plan',
            'price' => '120000',
            'currency' => 'IDR',
            'billing_interval' => 'yearly',
            'analysis_limit' => '120',
            'is_active' => '1',
        ])->assertRedirect();
        $created = Plan::query()->where('slug', 'creator-annual')->sole();
        $this->assertSame('120', (string) $created->analysis_limit);

        $this->get(route('admin.plans.show', $plan))->assertOk()->assertSee('Paket tidak dapat dihapus');
        $this->get(route('admin.plans.edit', $plan))->assertOk();
        $this->put(route('admin.plans.update', $plan), [
            'name' => 'Starter Updated',
            'slug' => 'starter-updated',
            'description' => 'New text',
            'price' => '5000',
            'currency' => 'IDR',
            'billing_interval' => 'monthly',
            'analysis_limit' => '',
            'is_active' => '1',
        ])->assertRedirect(route('admin.plans.show', $plan));
        $this->assertSame('Starter Updated', $plan->fresh()->name);

        $this->patch(route('admin.plans.active', $plan), ['is_active' => '0'])
            ->assertRedirect();
        $this->assertFalse($plan->fresh()->is_active);
    }

    public function test_non_admin_cannot_access_any_plan_management_action(): void
    {
        $plan = $this->createPlan('Protected Plan', 'protected-plan');
        $user = User::factory()->create();

        $this->actingAs($user);
        $this->get(route('admin.plans.index'))->assertForbidden();
        $this->get(route('admin.plans.create'))->assertForbidden();
        $this->post(route('admin.plans.store'), [])->assertForbidden();
        $this->get(route('admin.plans.show', $plan))->assertForbidden();
        $this->get(route('admin.plans.edit', $plan))->assertForbidden();
        $this->put(route('admin.plans.update', $plan), [])->assertForbidden();
        $this->patch(route('admin.plans.active', $plan), [])->assertForbidden();

        auth()->logout();
        $this->get(route('admin.plans.index'))->assertRedirect(route('auth'));
    }

    public function test_plan_validation_and_activation_respect_entitlements_and_pending_payments(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
        $plan = $this->createPlan('Safe Plan', 'safe-plan', 29000, 'monthly');
        $user = User::factory()->create();
        $subscription = new Subscription(['starts_at' => now()->subDay(), 'ends_at' => now()->addDay()]);
        $subscription->status = 'active';
        $subscription->plan()->associate($plan);
        $user->subscriptions()->save($subscription);
        $payment = new Payment(['amount' => 29000, 'currency' => 'IDR', 'provider' => 'midtrans']);
        $payment->status = 'paid';
        $payment->subscription()->associate($subscription);
        $user->payments()->save($payment);

        $this->actingAs($admin)
            ->patch(route('admin.plans.active', $plan), ['is_active' => '0'])
            ->assertSessionHasErrors('is_active');
        $this->put(route('admin.plans.update', $plan), [
            'name' => $plan->name,
            'slug' => $plan->slug,
            'price' => '0',
            'currency' => 'IDR',
            'billing_interval' => 'none',
            'analysis_limit' => '',
            'is_active' => '1',
        ])->assertSessionHasErrors('is_active');

        $payment->status = 'refunded';
        $payment->save();
        $subscription->status = 'cancelled';
        $subscription->cancelled_at = now();
        $subscription->save();

        $pendingSubscription = new Subscription;
        $pendingSubscription->plan()->associate($plan);
        $pendingSubscription->status = 'pending';
        $user->subscriptions()->save($pendingSubscription);
        $pendingPayment = new Payment(['amount' => 29000, 'currency' => 'IDR', 'provider' => 'midtrans']);
        $pendingPayment->status = 'pending';
        $pendingPayment->subscription()->associate($pendingSubscription);
        $user->payments()->save($pendingPayment);

        $this->patch(route('admin.plans.active', $plan), ['is_active' => '0'])
            ->assertSessionHasErrors('is_active');
        $pendingPayment->status = 'failed';
        $pendingPayment->save();

        $oldAmount = $payment->amount;
        $this->put(route('admin.plans.update', $plan), [
            'name' => $plan->name,
            'slug' => $plan->slug,
            'price' => '39000',
            'currency' => 'IDR',
            'billing_interval' => 'monthly',
            'analysis_limit' => '25',
            'is_active' => '1',
        ])->assertRedirect();
        $this->assertSame($oldAmount, $payment->fresh()->amount);
    }

    public function test_invalid_paid_nonrecurring_plan_is_rejected(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);

        $this->actingAs($admin)
            ->post(route('admin.plans.store'), [
                'name' => 'Invalid Paid',
                'slug' => 'invalid-paid',
                'price' => '1000',
                'currency' => 'IDR',
                'billing_interval' => 'none',
                'analysis_limit' => '',
                'is_active' => '1',
            ])
            ->assertSessionHasErrors('billing_interval');
    }

    private function createPlan(string $name, string $slug, int $price = 0, string $interval = 'none'): Plan
    {
        return Plan::query()->create([
            'name' => $name,
            'slug' => $slug,
            'price' => $price,
            'currency' => 'IDR',
            'billing_interval' => $interval,
            'is_active' => true,
        ]);
    }
}
