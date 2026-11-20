<?php

namespace Tests\Feature;

use App\Models\BanHistory;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Auth;
use Tests\TestCase;

class AdminPanelTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_login_redirects_to_admin_with_one_time_popup_signal(): void
    {
        $admin = User::factory()->create([
            'email' => 'admin@aicheck.local',
            'password' => 'AICheckAdmin2026!',
            'role' => 'admin',
        ]);

        $this->post(route('login'), [
            'email' => $admin->email,
            'password' => 'AICheckAdmin2026!',
        ])->assertRedirect(route('admin'))
            ->assertSessionHas('admin_login_success', true);

        $this->get(route('admin'))
            ->assertOk()
            ->assertSee('data-admin-login-success="true"', false);

        $this->get(route('admin'))
            ->assertOk()
            ->assertSee('data-admin-login-success="false"', false);
    }

    public function test_regular_user_cannot_access_admin_and_admin_cannot_ban_an_admin(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user)->get(route('admin'))->assertForbidden();

        $admin = User::factory()->create(['role' => 'admin']);
        $otherAdmin = User::factory()->create(['role' => 'admin']);
        $this->actingAs($admin)
            ->post(route('admin.users.ban', $otherAdmin), ['reason' => 'Tidak diperbolehkan'])
            ->assertForbidden();
    }

    public function test_admin_can_ban_and_unban_a_regular_user(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $user = User::factory()->create();

        $this->actingAs($admin)
            ->post(route('admin.users.ban', $user), ['reason' => 'Melanggar aturan penggunaan'])
            ->assertRedirect(route('admin.accounts'));

        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'is_banned' => true,
            'banned_by' => $admin->id,
        ]);
        $this->assertDatabaseHas('ban_histories', [
            'user_id' => $user->id,
            'admin_id' => $admin->id,
            'action' => BanHistory::ACTION_BANNED,
        ]);

        Auth::guard('web')->logout();
        $this->post(route('login'), [
            'email' => $user->email,
            'password' => 'password',
        ])->assertSessionHasErrors('email');

        $this->actingAs($admin)
            ->post(route('admin.users.unban', $user))
            ->assertRedirect(route('admin.accounts'));

        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'is_banned' => false,
            'unbanned_by' => $admin->id,
        ]);
        $this->assertDatabaseHas('ban_histories', [
            'user_id' => $user->id,
            'admin_id' => $admin->id,
            'action' => BanHistory::ACTION_UNBANNED,
        ]);
    }

    public function test_admin_cannot_ban_self(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($admin)
            ->post(route('admin.users.ban', $admin), ['reason' => 'Tes larangan'])
            ->assertForbidden();
    }

    public function test_admin_password_is_hashed_and_role_is_not_mass_assignable(): void
    {
        $user = User::query()->create([
            'name' => 'Pengguna',
            'email' => 'hashed@example.test',
            'password' => 'Password123',
            'role' => 'admin',
        ]);

        $this->assertSame('user', $user->fresh()->role);
        $this->assertTrue(Hash::check('Password123', $user->fresh()->password));
    }
}
