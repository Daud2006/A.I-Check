<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        foreach ([
            [
                'name' => 'Administrator',
                'email' => 'admin@gmail.com',
                'password' => 'kucing123',
                'role' => User::ROLE_ADMIN,
            ],
            [
                'name' => 'Pengguna',
                'email' => 'user@gmail.com',
                'password' => 'kucing123',
                'role' => User::ROLE_USER,
            ],
        ] as $account) {
            $user = User::query()->firstOrNew(['email' => $account['email']]);
            $user->name = $account['name'];
            $user->password = $account['password'];
            $user->role = $account['role'];
            $user->save();
        }
    }
}
