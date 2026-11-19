<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use LogicException;
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

    public function test_database_seeder_does_not_change_accounts_that_already_exist(): void
    {
        $admin = User::factory()->create([
            'name' => 'Existing Administrator',
            'email' => 'admin@gmail.com',
            'password' => 'ExistingAdminPassword123',
            'role' => User::ROLE_ADMIN,
        ]);
        $user = User::factory()->create([
            'name' => 'Existing User',
            'email' => 'user@gmail.com',
            'password' => 'ExistingUserPassword123',
        ]);

        $this->seed();

        $this->assertSame('Existing Administrator', $admin->fresh()->name);
        $this->assertTrue(Hash::check('ExistingAdminPassword123', $admin->fresh()->password));
        $this->assertSame(User::ROLE_ADMIN, $admin->fresh()->role);
        $this->assertSame('Existing User', $user->fresh()->name);
        $this->assertTrue(Hash::check('ExistingUserPassword123', $user->fresh()->password));
        $this->assertSame(User::ROLE_USER, $user->fresh()->role);
    }

    public function test_database_seeder_refuses_to_create_default_accounts_in_production(): void
    {
        $originalEnvironment = app()->environment();
        $this->app['env'] = 'production';

        try {
            try {
                (new DatabaseSeeder)->run();
                $this->fail('Default accounts must not be seeded in production.');
            } catch (LogicException $exception) {
                $this->assertSame(
                    'Default accounts are only available in local and testing environments.',
                    $exception->getMessage()
                );
            }
        } finally {
            $this->app['env'] = $originalEnvironment;
        }

        $this->assertDatabaseCount('users', 0);
    }
}
