<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // User::factory(10)->create();

        $this->call(PlanSeeder::class);

        $adminEmail = (string) env('ADMIN_SEED_EMAIL', 'admin@aicheck.local');
        $adminPassword = (string) env('ADMIN_SEED_PASSWORD', 'AICheckAdmin2026!');

        $admin = User::query()->updateOrCreate(
            ['email' => $adminEmail],
            [
                'name' => 'Administrator A.I-Check',
                'password' => Hash::make($adminPassword),
            ]
        );
        $admin->role = User::ROLE_ADMIN;
        $admin->is_banned = false;
        $admin->save();
    }
}
