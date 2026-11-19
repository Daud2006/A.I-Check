<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('subscriptions', function (Blueprint $table): void {
            $table->foreignId('granted_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->uuid('grant_reference')->nullable()->unique();
            $table->json('plan_terms')->nullable();
        });

        Schema::table('payments', function (Blueprint $table): void {
            $table->string('environment', 16)->nullable();
            $table->string('payment_method', 32)->nullable();
            $table->timestamp('verified_at')->nullable();
        });

        DB::table('subscriptions')->join('plans', 'plans.id', '=', 'subscriptions.plan_id')
            ->whereIn('subscriptions.status', ['active', 'pending'])
            ->select('subscriptions.id', 'plans.name', 'plans.billing_interval', 'plans.analysis_limit')
            ->chunkById(200, function ($subscriptions): void {
                foreach ($subscriptions as $subscription) {
                    DB::table('subscriptions')->where('id', $subscription->id)->update([
                        'plan_terms' => json_encode([
                            'name' => $subscription->name,
                            'billing_interval' => $subscription->billing_interval,
                            'analysis_limit' => $subscription->analysis_limit === null ? null : (int) $subscription->analysis_limit,
                        ], JSON_THROW_ON_ERROR),
                    ]);
                }
            }, 'subscriptions.id', 'id');
    }

    public function down(): void
    {
        Schema::table('subscriptions', function (Blueprint $table): void {
            $table->dropForeign(['granted_by']);
            $table->dropUnique(['grant_reference']);
            $table->dropColumn(['granted_by', 'grant_reference', 'plan_terms']);
        });
        Schema::table('payments', fn (Blueprint $table) => $table->dropColumn(['environment', 'payment_method', 'verified_at']));
    }
};
