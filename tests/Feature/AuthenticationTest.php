<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AuthenticationTest extends TestCase
{
    use RefreshDatabase;

    public function test_registration_creates_a_hashed_regular_user_and_authenticates_them(): void
    {
        $response = $this->post(route('register'), [
            'name' => 'Contoh Pengguna',
            'email' => 'contoh@example.test',
            'password' => 'Password123',
            'password_confirmation' => 'Password123',
            'role' => 'admin',
        ]);

        $user = User::query()->where('email', 'contoh@example.test')->firstOrFail();

        $this->assertTrue(Hash::check('Password123', $user->password));
        $this->assertSame('user', $user->role);
        $this->assertAuthenticatedAs($user);
        $response->assertRedirect(route('home'));
    }

    public function test_registration_rejects_an_existing_email(): void
    {
        User::factory()->create(['email' => 'duplikat@example.test']);

        $this->from(route('auth'))->post(route('register'), [
            'name' => 'Pengguna Baru',
            'email' => 'duplikat@example.test',
            'password' => 'Password123',
            'password_confirmation' => 'Password123',
        ])->assertRedirect(route('auth'))
            ->assertSessionHasErrors(['email']);

        $this->get(route('auth'))
            ->assertSee('data-auth-initial-mode="register"', false)
            ->assertSee('Email tersebut sudah terdaftar.');
    }

    public function test_registration_validates_password_strength_and_confirmation(): void
    {
        $this->from(route('auth'))->post(route('register'), [
            'name' => 'Pengguna Baru',
            'email' => 'baru@example.test',
            'password' => 'weak',
            'password_confirmation' => 'different',
        ])->assertRedirect(route('auth'))
            ->assertSessionHasErrors(['password']);
    }

    public function test_login_uses_database_credentials_and_regenerates_the_session(): void
    {
        $user = User::factory()->create([
            'email' => 'login@example.test',
            'password' => 'Password123',
        ]);

        $this->get(route('auth'));
        $originalSessionId = session()->getId();

        $this->post(route('login'), [
            'email' => 'login@example.test',
            'password' => 'Password123',
        ])->assertRedirect(route('home'));

        $this->assertAuthenticatedAs($user);
        $this->assertNotSame($originalSessionId, session()->getId());
    }

    public function test_login_rejects_invalid_credentials_with_a_safe_message(): void
    {
        User::factory()->create(['email' => 'login@example.test']);

        $this->from(route('auth'))->post(route('login'), [
            'email' => 'login@example.test',
            'password' => 'wrong-password',
        ])->assertRedirect(route('auth'))
            ->assertSessionHasErrors([
                'email' => 'Email atau kata sandi yang Anda masukkan salah.',
            ]);

        $this->assertGuest();
    }

    public function test_logout_ends_authentication_and_regenerates_the_csrf_token(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);
        $oldToken = session()->token();

        $this->post(route('logout'))
            ->assertRedirect(route('home'));

        $this->assertGuest();
        $this->assertNotSame($oldToken, session()->token());
    }

    public function test_private_pages_redirect_guests_and_checker_remains_public(): void
    {
        foreach (['history', 'profile', 'admin'] as $route) {
            $this->get(route($route))->assertRedirect(route('auth'));
        }

        $this->get(route('checker'))->assertOk();
    }

    public function test_regular_users_cannot_access_admin(): void
    {
        $this->actingAs(User::factory()->create())
            ->get(route('admin'))
            ->assertForbidden();
    }

    public function test_profile_displays_and_updates_only_the_authenticated_users_name_and_email(): void
    {
        $user = User::factory()->create([
            'name' => 'Nama Lama',
            'email' => 'lama@example.test',
        ]);

        $this->actingAs($user)
            ->get(route('profile'))
            ->assertOk()
            ->assertSee('Nama Lama')
            ->assertSee('lama@example.test');

        $this->put(route('profile.update'), [
            'name' => 'Nama Baru',
            'email' => 'baru@example.test',
            'role' => 'admin',
        ])->assertRedirect(route('profile'));

        $this->assertSame('Nama Baru', $user->fresh()->name);
        $this->assertSame('baru@example.test', $user->fresh()->email);
        $this->assertSame('user', $user->fresh()->role);
    }

    public function test_profile_rejects_invalid_and_duplicate_values(): void
    {
        $user = User::factory()->create([
            'name' => 'Nama Lama',
            'email' => 'lama@example.test',
        ]);
        User::factory()->create(['email' => 'sudah-ada@example.test']);

        $this->actingAs($user)
            ->from(route('profile'))
            ->put(route('profile.update'), [
                'name' => '',
                'email' => 'sudah-ada@example.test',
                'password' => 'new-password',
                'role' => 'admin',
            ])->assertRedirect(route('profile'))
            ->assertSessionHasErrors(['name', 'email']);

        $this->assertSame('Nama Lama', $user->fresh()->name);
        $this->assertSame('lama@example.test', $user->fresh()->email);
        $this->assertSame('user', $user->fresh()->role);

        $this->get(route('profile'))
            ->assertSee('data-auth-open-on-load="false"', false)
            ->assertSee('Nama wajib diisi.');
    }
}
