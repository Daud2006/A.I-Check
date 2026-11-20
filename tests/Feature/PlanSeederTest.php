<?php

namespace Tests\Feature;

use App\Models\Plan;
use Database\Seeders\PlanSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PlanSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_premium_plans_are_created_with_stable_slugs_and_seeding_is_idempotent(): void
    {
        $this->seed(PlanSeeder::class);
        $this->seed(PlanSeeder::class);

        $this->assertDatabaseCount('plans', 2);
        $this->assertDatabaseHas('plans', [
            'slug' => 'premium-monthly',
            'price' => 29000,
            'currency' => 'IDR',
            'billing_interval' => 'monthly',
            'is_active' => true,
        ]);
        $this->assertDatabaseHas('plans', [
            'slug' => 'premium-yearly',
            'price' => 249000,
            'currency' => 'IDR',
            'billing_interval' => 'yearly',
            'is_active' => true,
        ]);
        $this->assertNull(Plan::query()->where('slug', 'premium-monthly')->firstOrFail()->analysis_limit);
        $this->assertNull(Plan::query()->where('slug', 'premium-yearly')->firstOrFail()->analysis_limit);
    }
}
