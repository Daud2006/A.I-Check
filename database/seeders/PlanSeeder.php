<?php

namespace Database\Seeders;

use App\Models\Plan;
use Illuminate\Database\Seeder;

class PlanSeeder extends Seeder
{
    public function run(): void
    {
        Plan::query()->updateOrCreate(
            ['slug' => 'premium-monthly'],
            [
                'name' => 'Premium Bulanan',
                'description' => 'Akses Premium selama 1 bulan.',
                'price' => 29000,
                'currency' => 'IDR',
                'billing_interval' => 'monthly',
                'analysis_limit' => null,
                'is_active' => true,
            ],
        );

        Plan::query()->updateOrCreate(
            ['slug' => 'premium-yearly'],
            [
                'name' => 'Premium Tahunan',
                'description' => 'Akses Premium selama 1 tahun.',
                'price' => 249000,
                'currency' => 'IDR',
                'billing_interval' => 'yearly',
                'analysis_limit' => null,
                'is_active' => true,
            ],
        );
    }
}
