<?php

namespace Tests\Feature;

use App\Models\ImageAnalysis;
use App\Models\Payment;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class AdminDashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_dashboard_aggregates_real_usage_subscription_and_paid_payment_data(): void
    {
        $this->travelTo(Carbon::parse('2026-11-06 15:00:00'));
        config(['session.driver' => 'database']);

        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
        $premiumUser = User::factory()->create([
            'name' => 'Premium User',
            'email' => 'premium@example.test',
            'created_at' => now()->subHour(),
        ]);
        $freeUser = User::factory()->create([
            'name' => 'Free User',
            'email' => 'free@example.test',
            'created_at' => now()->subMinutes(30),
        ]);

        $activePlan = Plan::query()->create([
            'name' => 'Premium',
            'slug' => 'premium',
            'price' => 29000,
            'currency' => 'IDR',
            'billing_interval' => 'monthly',
            'is_active' => true,
        ]);
        $premiumSubscription = $this->createSubscription($premiumUser, $activePlan, 'active', now()->subDay(), now()->addDay());
        $this->createSubscription($premiumUser, $activePlan, 'active', now()->subDay(), now()->addDay());
        $this->createSubscription($freeUser, $activePlan, 'active', now()->subDays(3), now()->subDay());

        $this->createAnalysis($premiumUser, 'completed', now());
        $this->createAnalysis($freeUser, 'failed', now()->subHours(2));
        $this->createAnalysis($freeUser, 'completed', now()->subDays(6));
        $this->createAnalysis($freeUser, 'completed', now()->subDays(8));

        $paidPayment = $this->createPayment($premiumUser, 'paid', 'IDR', 29000);
        $paidPayment->subscription()->associate($premiumSubscription);
        $paidPayment->save();
        $this->createPayment($premiumUser, 'paid', 'IDR', 1000);
        $this->createPayment($premiumUser, 'paid', 'USD', 10);
        $this->createPayment($freeUser, 'pending', 'IDR', 50000);
        $this->createPayment($freeUser, 'failed', 'IDR', 70000);
        $this->createPayment($freeUser, 'refunded', 'IDR', 90000);

        DB::table('sessions')->insert([
            [
                'id' => 'admin-active-session',
                'user_id' => $admin->id,
                'ip_address' => '127.0.0.1',
                'user_agent' => 'test',
                'payload' => '',
                'last_activity' => now()->timestamp,
            ],
            [
                'id' => 'premium-active-session-one',
                'user_id' => $premiumUser->id,
                'ip_address' => '127.0.0.1',
                'user_agent' => 'test',
                'payload' => '',
                'last_activity' => now()->timestamp,
            ],
            [
                'id' => 'premium-active-session-two',
                'user_id' => $premiumUser->id,
                'ip_address' => '127.0.0.1',
                'user_agent' => 'test',
                'payload' => '',
                'last_activity' => now()->subMinutes(2)->timestamp,
            ],
            [
                'id' => 'free-expired-session',
                'user_id' => $freeUser->id,
                'ip_address' => '127.0.0.1',
                'user_agent' => 'test',
                'payload' => '',
                'last_activity' => now()->subMinutes(6)->timestamp,
            ],
        ]);

        $response = $this->actingAs($admin)->get(route('admin'));
        $response->assertOk()
            ->assertViewHas('userCount', 3)
            ->assertViewHas('analysisCount', 4)
            ->assertViewHas('todayAnalysisCount', 2)
            ->assertViewHas('onlineUsersCount', 2)
            ->assertViewHas('activeSubscriptionUsersCount', 1)
            ->assertViewHas('successfulPaymentCount', 3)
            ->assertViewHas('pendingPaymentCount', 1)
            ->assertViewHas('dashboardError', null)
            ->assertSee('Pengguna terbaru')
            ->assertSee('Analisis terbaru')
            ->assertSee('Premium User')
            ->assertSee('premium@example.test')
            ->assertDontSee('provider_transaction_id');

        $this->assertSame(
            [
                ['currency' => 'IDR', 'amount' => '30000'],
                ['currency' => 'USD', 'amount' => '10'],
            ],
            $response->viewData('paidRevenueByCurrency')
        );

        $chart = $response->viewData('analysisChart');
        $this->assertCount(7, $chart);
        $this->assertSame(1, $chart[0]['total']);
        $this->assertSame(2, $chart[6]['total']);
        $this->assertSame(now()->subDays(6)->toDateString(), $chart[0]['date']);
    }

    public function test_admin_dashboard_shows_empty_states_when_there_is_no_activity_data(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);

        $this->actingAs($admin)
            ->get(route('admin'))
            ->assertOk()
            ->assertViewHas('analysisCount', 0)
            ->assertViewHas('todayAnalysisCount', 0)
            ->assertViewHas('successfulPaymentCount', 0)
            ->assertSee('Belum ada analisis dalam 7 hari terakhir.')
            ->assertSee('Belum ada pengguna terdaftar.')
            ->assertSee('Belum ada analisis gambar.');
    }

    public function test_online_users_metric_is_unavailable_when_sessions_are_not_database_backed(): void
    {
        config(['session.driver' => 'file']);
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);

        $this->actingAs($admin)
            ->get(route('admin'))
            ->assertOk()
            ->assertViewHas('onlineUsersAvailable', false)
            ->assertViewHas('onlineUsersCount', null)
            ->assertSee('Tidak tersedia');
    }

    private function createAnalysis(User $user, string $status, Carbon $createdAt): ImageAnalysis
    {
        $analysis = $user->imageAnalyses()->create([
            'original_filename' => 'private-poster.png',
            'mime_type' => 'image/png',
            'file_size' => 1024,
        ]);
        $analysis->status = $status;
        $analysis->created_at = $createdAt;
        $analysis->save();

        return $analysis;
    }

    private function createSubscription(
        User $user,
        Plan $plan,
        string $status,
        ?Carbon $startsAt,
        ?Carbon $endsAt
    ): Subscription {
        $subscription = new Subscription([
            'plan_id' => $plan->id,
            'starts_at' => $startsAt,
            'ends_at' => $endsAt,
        ]);
        $subscription->status = $status;
        $user->subscriptions()->save($subscription);

        return $subscription;
    }

    private function createPayment(User $user, string $status, string $currency, int $amount): Payment
    {
        $payment = new Payment([
            'amount' => $amount,
        ]);
        $payment->currency = $currency;
        $payment->status = $status;
        $user->payments()->save($payment);

        return $payment;
    }
}
