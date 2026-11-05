<?php

namespace Tests\Feature;

use App\Models\Plan;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class AuthenticationTest extends TestCase
{
    use RefreshDatabase;

    public function test_registration_creates_a_hashed_regular_user_and_authenticates_them(): void
    {
        Notification::fake();

        $response = $this->post(route('register'), [
            'name' => 'Contoh Pengguna',
            'email' => 'contoh@example.test',
            'password' => 'Password123',
            'password_confirmation' => 'Password123',
            'terms' => '1',
            'role' => 'admin',
        ]);

        $user = User::query()->where('email', 'contoh@example.test')->firstOrFail();

        $this->assertTrue(Hash::check('Password123', $user->password));
        $this->assertSame('user', $user->role);
        $this->assertAuthenticatedAs($user);
        $response->assertRedirect(route('home'));
        $this->assertDatabaseHas('users', ['id' => $user->id, 'email' => 'contoh@example.test']);
        Notification::assertNothingSent();
    }

    public function test_registration_requires_terms_acceptance(): void
    {
        $this->from(route('auth'))->post(route('register'), [
            'name' => 'Pengguna Baru',
            'email' => 'baru@example.test',
            'password' => 'Password123',
            'password_confirmation' => 'Password123',
        ])->assertRedirect(route('auth'))
            ->assertSessionHasErrors([
                'terms' => 'Anda harus menyetujui Syarat Penggunaan dan Kebijakan Privasi terlebih dahulu.',
            ]);

        $this->assertDatabaseMissing('users', ['email' => 'baru@example.test']);
        $this->assertGuest();
    }

    public function test_registration_rejects_an_invalid_email(): void
    {
        $this->from(route('auth'))->post(route('register'), [
            'name' => 'Pengguna Baru',
            'email' => 'bukan-email',
            'password' => 'Password123',
            'password_confirmation' => 'Password123',
            'terms' => '1',
        ])->assertRedirect(route('auth'))
            ->assertSessionHasErrors(['email']);

        $this->assertDatabaseMissing('users', ['email' => 'bukan-email']);
    }

    public function test_registration_rejects_an_existing_email(): void
    {
        User::factory()->create(['email' => 'duplikat@example.test']);

        $this->from(route('auth'))->post(route('register'), [
            'name' => 'Pengguna Baru',
            'email' => 'duplikat@example.test',
            'password' => 'Password123',
            'password_confirmation' => 'Password123',
            'terms' => '1',
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
            ->assertRedirect(route('home'))
            ->assertSessionHas('logout_success', true)
            ->assertSessionMissing('status');

        $this->assertGuest();
        $this->assertNotSame($oldToken, session()->token());
    }

    public function test_logout_success_notice_is_consumed_once_on_the_home_page(): void
    {
        $this->actingAs(User::factory()->create())
            ->post(route('logout'))
            ->assertRedirect(route('home'));

        $this->get(route('home'))
            ->assertSee('data-logout-success="true"', false)
            ->assertDontSee('Anda telah keluar.');

        $this->get(route('home'))
            ->assertSee('data-logout-success="false"', false)
            ->assertDontSee('Anda telah keluar.');
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

    public function test_profile_displays_email_as_read_only_and_updates_only_the_authenticated_users_name(): void
    {
        $user = User::factory()->create([
            'name' => 'Nama Lama',
            'email' => 'lama@example.test',
        ]);
        $otherUser = User::factory()->create([
            'name' => 'Pengguna Lain',
            'email' => 'lain@example.test',
        ]);

        $profileResponse = $this->actingAs($user)->get(route('profile'));
        $profileResponse->assertOk()
            ->assertSee('Nama Lama')
            ->assertSee('lama@example.test');

        $profileView = file_get_contents(resource_path('views/beranda/profil.blade.php'));
        $this->assertIsString($profileView);
        $this->assertStringNotContainsString('name="email"', $profileView);

        $this->put(route('profile.update'), [
            'name' => 'Nama Baru',
            'email' => 'baru@example.test',
            'role' => 'admin',
        ])->assertRedirect(route('profile'))
            ->assertSessionHasErrors(['email']);

        $this->assertSame('Nama Lama', $user->fresh()->name);
        $this->assertSame('lama@example.test', $user->fresh()->email);

        $this->put(route('profile.update'), [
            'name' => 'Nama Baru',
            'role' => 'admin',
        ])->assertRedirect(route('profile'))
            ->assertSessionHas('status', 'Profil berhasil diperbarui.');

        $this->assertSame('Nama Baru', $user->fresh()->name);
        $this->assertSame('lama@example.test', $user->fresh()->email);
        $this->assertSame('user', $user->fresh()->role);
        $this->assertSame('Pengguna Lain', $otherUser->fresh()->name);
        $this->assertSame('lain@example.test', $otherUser->fresh()->email);

        $this->get(route('profile'))
            ->assertOk()
            ->assertSee('value="Nama Baru"', false);
        $this->get(route('home'))
            ->assertOk()
            ->assertSee('Nama Baru');
    }

    public function test_profile_rejects_invalid_names_without_changing_the_account(): void
    {
        $user = User::factory()->create([
            'name' => 'Nama Lama',
        ]);

        $this->actingAs($user)
            ->from(route('profile'))
            ->put(route('profile.update'), [
                'name' => '',
            ])->assertRedirect(route('profile'))
            ->assertSessionHasErrors(['name']);

        $this->assertSame('Nama Lama', $user->fresh()->name);
        $this->assertSame('user', $user->fresh()->role);

        $this->get(route('profile'))
            ->assertSee('data-auth-open-on-load="false"', false)
            ->assertSee('Nama wajib diisi.');
    }

    public function test_profile_displays_premium_only_for_a_current_active_paid_subscription(): void
    {
        $user = User::factory()->create();
        $plan = Plan::query()->create([
            'name' => 'Premium',
            'slug' => 'premium',
            'price' => 29000,
            'currency' => 'IDR',
            'billing_interval' => 'monthly',
            'is_active' => true,
        ]);
        $subscription = new Subscription([
            'plan_id' => $plan->id,
            'starts_at' => now()->subDay(),
            'ends_at' => now()->addDay(),
        ]);
        $subscription->status = 'active';
        $user->subscriptions()->save($subscription);

        $this->actingAs($user)
            ->get(route('profile'))
            ->assertOk()
            ->assertSee('Premium')
            ->assertSee('profile-account-badge is-premium', false);
    }

    public function test_profile_displays_free_for_an_inactive_or_unpaid_subscription(): void
    {
        $user = User::factory()->create();
        $plan = Plan::query()->create([
            'name' => 'Premium',
            'slug' => 'premium',
            'price' => 29000,
            'currency' => 'IDR',
            'billing_interval' => 'monthly',
            'is_active' => true,
        ]);
        $subscription = new Subscription([
            'plan_id' => $plan->id,
            'starts_at' => now()->subDay(),
            'ends_at' => now()->addDay(),
        ]);
        $subscription->status = 'pending';
        $user->subscriptions()->save($subscription);

        $this->actingAs($user)
            ->get(route('profile'))
            ->assertOk()
            ->assertSee('Free')
            ->assertSee('profile-account-badge is-free', false)
            ->assertDontSee('profile-account-badge is-premium', false);
    }
}
