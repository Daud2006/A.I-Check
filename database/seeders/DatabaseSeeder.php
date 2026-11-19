<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use LogicException;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        if (! app()->environment(['local', 'testing'])) {
            throw new LogicException('Default accounts are only available in local and testing environments.');
        }

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

            if ($user->exists) {
                continue;
            }

            $user->name = $account['name'];
            $user->password = $account['password'];
            $user->role = $account['role'];
            $user->save();
        }
    }
}
