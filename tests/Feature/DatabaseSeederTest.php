<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class DatabaseSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_database_seeder_creates_the_admin_and_regular_accounts_idempotently(): void
    {
        $this->seed();
        $this->seed();

        $admin = User::query()->where('email', 'admin@gmail.com')->sole();
        $user = User::query()->where('email', 'user@gmail.com')->sole();

        $this->assertSame(User::ROLE_ADMIN, $admin->role);
        $this->assertSame(User::ROLE_USER, $user->role);
        $this->assertTrue(Hash::check('kucing123', $admin->password));
        $this->assertTrue(Hash::check('kucing123', $user->password));
        $this->assertSame(2, User::query()->count());
        $this->actingAs($admin)->get('/admin')->assertOk();
        $this->actingAs($user)->get('/admin')->assertForbidden();
    }
}
