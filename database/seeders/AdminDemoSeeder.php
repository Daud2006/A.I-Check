<?php

namespace Database\Seeders;

use App\Models\ImageAnalysis;
use App\Models\Payment;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

class AdminDemoSeeder extends Seeder
{
    public function run(): void
    {
        if (! app()->environment('local')) {
            throw new RuntimeException('Data demo hanya dibuat di lingkungan lokal.');
        }

        DB::transaction(function (): void {
            $plans = Plan::query()->whereIn('slug', ['premium-bulanan', 'premium-plus', 'premium-pro'])->get()->keyBy('slug');
            $admin = User::query()->where('role', User::ROLE_ADMIN)->whereNull('disabled_at')->firstOrFail();
            $profiles = [
                ['Alya', 'free', null, null, null],
                ['Bima', 'bulanan', 'premium-bulanan', 'active', 'paid'],
                ['Citra', 'plus', 'premium-plus', 'pending', 'pending'],
                ['Damar', 'hadiah', 'premium-pro', 'active', null],
                ['Elin', 'berakhir', 'premium-plus', 'expired', 'failed'],
            ];

            foreach ($profiles as [$name, $key, $slug, $status, $paymentStatus]) {
                $user = User::query()->firstOrCreate(['email' => "demo.{$key}@example.test"], [
                    'name' => "Uji Admin – {$name}",
                    'password' => Str::password(24),
                ]);
                if (! $user->wasRecentlyCreated) {
                    continue;
                }

                if ($slug !== null) {
                    $plan = $plans->get($slug) ?? throw new RuntimeException("Paket demo {$slug} belum tersedia.");
                    $start = $status === 'active' ? now()->subDays(4) : ($status === 'expired' ? now()->subDays(38) : null);
                    $subscription = new Subscription([
                        'plan_id' => $plan->id,
                        'starts_at' => $start,
                        'ends_at' => $start?->copy()->addMonthNoOverflow(),
                    ]);
                    $subscription->status = $status;
                    $subscription->plan_terms = $plan->subscriptionTerms();
                    if ($key === 'hadiah') {
                        $subscription->granted_by = $admin->id;
                        $subscription->grant_reference = Str::uuid()->toString();
                    }
                    $user->subscriptions()->save($subscription);

                    if ($paymentStatus !== null) {
                        $payment = new Payment([
                            'provider' => 'demo',
                            'provider_transaction_id' => 'DEMO-'.strtoupper($key),
                            'amount' => $plan->price,
                            'currency' => $plan->currency,
                            'paid_at' => $paymentStatus === 'paid' ? $start : null,
                        ]);
                        $payment->status = $paymentStatus;
                        $payment->environment = 'sandbox';
                        $payment->payment_method = $key === 'bulanan' ? 'qris' : 'dana';
                        $payment->subscription()->associate($subscription);
                        $user->payments()->save($payment);
                    }
                }

                $labels = ['REAL', 'AI-GENERATED', 'AI-EDITED', 'UNCERTAIN'];
                $baseScores = ['REAL' => 0.20, 'AI-GENERATED' => 0.90, 'AI-EDITED' => 0.80, 'UNCERTAIN' => 0.60];
                $count = random_int(3, 5);
                for ($index = 0; $index < $count; $index++) {
                    $label = $labels[($index + array_search($key, array_column($profiles, 1), true)) % count($labels)];
                    $failed = $index === 0 && in_array($key, ['plus', 'berakhir'], true);
                    $analysis = new ImageAnalysis([
                        'original_filename' => "demo-{$key}-".($index + 1).'.png',
                        'mime_type' => 'image/png',
                        'file_size' => random_int(40000, 900000),
                    ]);
                    $analysis->created_at = now()->subDays($index === 0 ? 0 : random_int(1, 6));
                    $analysis->updated_at = $analysis->created_at;
                    $analysis->status = $failed ? 'failed' : 'completed';
                    if (! $failed) {
                        $analysis->result_label = $label;
                        $analysis->confidence_score = $baseScores[$label] + random_int(-4, 4) / 100;
                        $analysis->model_name = 'data-demo';
                    }
                    $user->imageAnalyses()->save($analysis);

                    if (! $failed) {
                        $usage = $user->usageRecords()->make(['usage_type' => 'analysis', 'quantity' => 1]);
                        $usage->created_at = $analysis->created_at;
                        $usage->imageAnalysis()->associate($analysis);
                        $usage->save();
                    }
                }
            }
        });
    }
}
