<?php

namespace Tests\Feature;

use App\Models\ImageAnalysis;
use App\Models\Payment;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\UsageRecord;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use InvalidArgumentException;
use Tests\TestCase;

class DatabaseSchemaTest extends TestCase
{
    use RefreshDatabase;

    public function test_domain_tables_and_user_columns_are_migrated(): void
    {
        foreach ([
            'users',
            'image_analyses',
            'plans',
            'subscriptions',
            'payments',
            'usage_records',
            'password_reset_tokens',
            'sessions',
        ] as $table) {
            $this->assertTrue(Schema::hasTable($table), "Missing table: {$table}");
        }

        $this->assertTrue(Schema::hasColumns('users', ['role', 'avatar']));
        $this->assertTrue(Schema::hasColumns('image_analyses', [
            'user_id',
            'original_filename',
            'image_path',
            'mime_type',
            'file_size',
            'model_name',
            'status',
            'result_label',
            'confidence_score',
        ]));
        $this->assertTrue(Schema::hasColumns('plans', [
            'name',
            'slug',
            'price',
            'currency',
            'billing_interval',
            'analysis_limit',
            'is_active',
        ]));
        $this->assertTrue(Schema::hasColumns('subscriptions', [
            'user_id',
            'plan_id',
            'status',
            'starts_at',
            'ends_at',
            'cancelled_at',
        ]));
        $this->assertTrue(Schema::hasColumns('payments', [
            'user_id',
            'subscription_id',
            'provider',
            'provider_transaction_id',
            'amount',
            'currency',
            'status',
            'paid_at',
        ]));
        $this->assertTrue(Schema::hasColumns('usage_records', [
            'user_id',
            'image_analysis_id',
            'usage_type',
            'quantity',
            'created_at',
        ]));
    }

    public function test_models_expose_the_required_relationships(): void
    {
        $user = User::factory()->create();
        $plan = Plan::query()->create([
            'name' => 'Starter',
            'slug' => 'starter',
            'price' => 0,
            'billing_interval' => 'none',
        ]);
        $analysis = $user->imageAnalyses()->create([
            'original_filename' => 'sample.png',
            'mime_type' => 'image/png',
            'file_size' => 1024,
        ]);
        $subscription = new Subscription([
            'plan_id' => $plan->id,
        ]);
        $subscription->status = 'pending';
        $user->subscriptions()->save($subscription);

        $payment = new Payment([
            'amount' => 0,
        ]);
        $payment->subscription_id = $subscription->id;
        $payment->status = 'pending';
        $user->payments()->save($payment);

        $usageRecord = new UsageRecord([
            'usage_type' => 'analysis',
            'quantity' => 1,
        ]);
        $usageRecord->image_analysis_id = $analysis->id;
        $user->usageRecords()->save($usageRecord);

        $this->assertTrue($analysis->user->is($user));
        $this->assertTrue($subscription->user->is($user));
        $this->assertTrue($subscription->plan->is($plan));
        $this->assertTrue($payment->user->is($user));
        $this->assertTrue($payment->subscription->is($subscription));
        $this->assertTrue($usageRecord->user->is($user));
        $this->assertTrue($usageRecord->imageAnalysis->is($analysis));
        $this->assertSame('user', $user->fresh()->role);
    }

    public function test_sensitive_fields_are_not_mass_assignable_and_values_are_validated(): void
    {
        $user = User::query()->create([
            'name' => 'Example User',
            'email' => 'example@example.test',
            'password' => 'password',
            'role' => 'admin',
        ]);

        $this->assertSame('user', $user->role);
        $this->assertSame(['name', 'email', 'password'], $user->getFillable());

        $this->expectException(InvalidArgumentException::class);
        $user->role = 'owner';
    }

    public function test_negative_payment_amount_is_rejected(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $payment = new Payment;
        $payment->amount = -1;
    }

    public function test_unsupported_status_values_are_rejected(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $payment = new Payment;
        $payment->status = 'authorized';
    }

    public function test_unsigned_values_and_billing_intervals_are_validated(): void
    {
        $analysis = new ImageAnalysis;
        try {
            $analysis->file_size = -1;
            $this->fail('Negative file sizes must be rejected.');
        } catch (InvalidArgumentException) {
            $this->assertTrue(true);
        }

        $plan = new Plan;
        try {
            $plan->billing_interval = 'weekly';
            $this->fail('Unsupported billing intervals must be rejected.');
        } catch (InvalidArgumentException) {
            $this->assertTrue(true);
        }

        $usageRecord = new UsageRecord;
        $this->expectException(InvalidArgumentException::class);
        $usageRecord->quantity = -1;
    }

    public function test_foreign_key_deletion_rules_preserve_usage_history(): void
    {
        $user = User::factory()->create();
        $plan = Plan::query()->create([
            'name' => 'Retained Plan',
            'slug' => 'retained-plan',
            'billing_interval' => 'none',
        ]);
        $subscription = new Subscription(['plan_id' => $plan->id]);
        $subscription->status = 'pending';
        $user->subscriptions()->save($subscription);

        $analysis = $user->imageAnalyses()->create([
            'original_filename' => 'retained.png',
            'mime_type' => 'image/png',
            'file_size' => 10,
        ]);
        $usageRecord = new UsageRecord(['usage_type' => 'analysis']);
        $usageRecord->image_analysis_id = $analysis->id;
        $user->usageRecords()->save($usageRecord);

        $analysis->delete();
        $this->assertNull($usageRecord->fresh()->image_analysis_id);

        $this->expectException(QueryException::class);
        $user->delete();
    }
}
