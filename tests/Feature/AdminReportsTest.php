<?php

namespace Tests\Feature;

use App\Models\ImageAnalysis;
use App\Models\Payment;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\UsageRecord;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class AdminReportsTest extends TestCase
{
    use RefreshDatabase;

    public function test_reports_aggregate_live_users_analyses_usage_and_paid_revenue_by_currency(): void
    {
        $this->travelTo(Carbon::parse('2026-11-06 15:00:00'));
        config(['session.driver' => 'database']);
        $admin = User::factory()->create([
            'role' => User::ROLE_ADMIN,
            'created_at' => now()->subDay(),
        ]);
        $owner = User::factory()->create(['name' => 'Report Owner', 'created_at' => now()->subHour()]);
        $plan = $this->createPlan();
        $subscription = $this->activeSubscription($owner, $plan);

        $ai = $this->analysis($owner, 'completed', 'AI-GENERATED', now()->subMinutes(20));
        $this->analysis($owner, 'completed', 'REAL', now()->subMinutes(10));
        $this->analysis($owner, 'failed', null, now()->subMinutes(5));

        $usage = new UsageRecord(['usage_type' => 'analysis', 'quantity' => 2]);
        $usage->imageAnalysis()->associate($ai);
        $owner->usageRecords()->save($usage);

        $this->payment($owner, $subscription, 'paid', 'IDR', 29000);
        $this->payment($owner, $subscription, 'pending', 'IDR', 90000);
        $this->payment($owner, $subscription, 'paid', 'USD', 10);
        $this->payment($owner, $subscription, 'refunded', 'IDR', 5000);
        DB::table('sessions')->insert([
            'id' => 'report-online-owner',
            'user_id' => $owner->id,
            'ip_address' => '127.0.0.1',
            'user_agent' => 'test',
            'payload' => '',
            'last_activity' => now()->timestamp,
        ]);

        $response = $this->actingAs($admin)->get(route('admin.reports.index', [
            'date_from' => now()->toDateString(),
            'date_to' => now()->toDateString(),
        ]));

        $response->assertOk()
            ->assertViewHas('newUserCount', 1)
            ->assertViewHas('analysisCount', 3)
            ->assertViewHas('aiAnalysisCount', 1)
            ->assertViewHas('humanAnalysisCount', 1)
            ->assertViewHas('unclassifiedAnalysisCount', 1)
            ->assertViewHas('recordedUsageCount', 2)
            ->assertViewHas('onlineUsersCount', 1)
            ->assertSee('AI-GENERATED')
            ->assertSee('REAL')
            ->assertSee('Ekspor CSV transaksi');
        $this->assertSame([
            ['currency' => 'IDR', 'total_amount' => '58000'],
            ['currency' => 'USD', 'total_amount' => '10'],
        ], $response->viewData('paidRevenueByCurrency')->map(fn ($row): array => [
            'currency' => $row->currency,
            'total_amount' => (string) $row->total_amount,
        ])->all());
    }

    public function test_report_filters_are_validated_and_apply_to_matching_data(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
        $owner = User::factory()->create();
        $plan = $this->createPlan();
        $subscription = $this->activeSubscription($owner, $plan);
        $this->analysis($owner, 'completed', 'AI-GENERATED', Carbon::parse('2026-10-01'));
        $this->analysis($owner, 'failed', null, Carbon::parse('2026-11-01'));
        $this->payment($owner, $subscription, 'paid', 'IDR', 29000, Carbon::parse('2026-10-01'));
        $this->payment($owner, $subscription, 'pending', 'IDR', 29000, Carbon::parse('2026-11-01'));

        $this->actingAs($admin)
            ->get(route('admin.reports.index', [
                'date_from' => '2026-10-01',
                'date_to' => '2026-10-31',
                'analysis_status' => 'completed',
                'result_label' => 'AI-GENERATED',
                'payment_status' => 'paid',
                'plan_id' => $plan->id,
            ]))
            ->assertOk()
            ->assertViewHas('analysisCount', 1)
            ->assertViewHas('paymentsByStatus', fn ($statuses): bool => $statuses->count() === 1
                && $statuses[0]->status === 'paid');

        $this->get(route('admin.reports.index', ['date_from' => '2026-11-02', 'date_to' => '2026-11-01']))
            ->assertSessionHasErrors('date_to');
    }

    public function test_report_routes_require_admin_authorization(): void
    {
        $this->get(route('admin.reports.index'))->assertRedirect(route('auth'));
        $this->get(route('admin.reports.export', 'users'))->assertRedirect(route('auth'));
        $this->actingAs(User::factory()->create())
            ->get(route('admin.reports.index'))
            ->assertForbidden();
        $this->get(route('admin.reports.export', 'users'))->assertForbidden();
    }

    public function test_user_csv_export_streams_filtered_values_and_neutralizes_formulas(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
        $this->createPlan();
        User::factory()->create([
            'name' => '=HYPERLINK("https://example.test")',
            'email' => '+SUM(1,1)@example.test',
            'created_at' => Carbon::parse('2026-11-04'),
        ]);
        User::factory()->create(['name' => 'Outside Range', 'created_at' => Carbon::parse('2026-10-01')]);

        $response = $this->actingAs($admin)->get(route('admin.reports.export', [
            'type' => 'users',
            'date_from' => '2026-11-01',
            'date_to' => '2026-11-30',
            'user_role' => 'user',
        ]));

        $response->assertOk()
            ->assertHeader('content-type', 'text/csv; charset=UTF-8')
            ->assertHeader('content-disposition');
        $csv = $response->streamedContent();
        $this->assertStringContainsString("'=HYPERLINK", $csv);
        $this->assertStringContainsString("'+SUM", $csv);
        $this->assertStringNotContainsString('Outside Range', $csv);
        $this->assertStringNotContainsString('password', strtolower($csv));
    }

    public function test_analysis_and_transaction_exports_exclude_private_paths_and_filter_by_status(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
        $owner = User::factory()->create(['name' => 'Export Owner']);
        $plan = $this->createPlan();
        $subscription = $this->activeSubscription($owner, $plan);
        $analysis = $this->analysis($owner, 'completed', 'REAL', now());
        $analysis->image_path = 'private/secret/path.png';
        $analysis->original_filename = '=cmd|danger.png';
        $analysis->save();
        $this->payment($owner, $subscription, 'paid', 'IDR', 29000);
        $this->payment($owner, $subscription, 'pending', 'IDR', 99999);

        $analysisCsv = $this->actingAs($admin)
            ->get(route('admin.reports.export', ['type' => 'analyses', 'analysis_status' => 'completed']))
            ->assertOk()
            ->streamedContent();
        $this->assertStringContainsString("'=cmd|danger.png", $analysisCsv);
        $this->assertStringNotContainsString('private/secret/path.png', $analysisCsv);

        $transactionCsv = $this->get(route('admin.reports.export', [
            'type' => 'transactions',
            'payment_status' => 'paid',
        ]))->assertOk()->streamedContent();
        $this->assertStringContainsString('Export Owner', $transactionCsv);
        $this->assertStringNotContainsString('99999', $transactionCsv);
        $this->assertStringNotContainsString('MIDTRANS_SERVER_KEY', $transactionCsv);
    }

    private function createPlan(): Plan
    {
        return Plan::query()->create([
            'name' => 'Report Premium',
            'slug' => 'report-premium',
            'price' => 29000,
            'currency' => 'IDR',
            'billing_interval' => 'monthly',
            'is_active' => true,
            'analysis_limit' => 100,
        ]);
    }

    private function activeSubscription(User $user, Plan $plan): Subscription
    {
        $subscription = new Subscription(['starts_at' => now()->subDay(), 'ends_at' => now()->addDays(30)]);
        $subscription->status = 'active';
        $subscription->plan()->associate($plan);
        $user->subscriptions()->save($subscription);
        $payment = new Payment(['amount' => $plan->price, 'currency' => $plan->currency]);
        $payment->status = 'paid';
        $payment->subscription()->associate($subscription);
        $user->payments()->save($payment);

        return $subscription;
    }

    private function analysis(User $user, string $status, ?string $label, Carbon $createdAt): ImageAnalysis
    {
        $analysis = $user->imageAnalyses()->create([
            'original_filename' => 'report.png',
            'image_path' => 'private/report.png',
            'mime_type' => 'image/png',
            'file_size' => 100,
        ]);
        $analysis->status = $status;
        $analysis->result_label = $label;
        $analysis->created_at = $createdAt;
        $analysis->save();

        return $analysis;
    }

    private function payment(
        User $user,
        Subscription $subscription,
        string $status,
        string $currency,
        int $amount,
        Carbon $createdAt = new Carbon
    ): Payment {
        $payment = new Payment(['amount' => $amount, 'currency' => $currency, 'provider' => 'midtrans']);
        $payment->status = $status;
        $payment->subscription()->associate($subscription);
        $payment->created_at = $createdAt;
        $user->payments()->save($payment);

        return $payment;
    }
}
