<?php

namespace Tests\Feature;

use App\Models\User;
use App\Notifications\PasswordResetOtp;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Tests\TestCase;

class PasswordResetOtpTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config(['mail.default' => 'smtp']);
    }

    public function test_it_sends_a_generic_reset_otp_notification_without_exposing_the_code(): void
    {
        Notification::fake();
        $user = User::factory()->create(['email' => 'reset@example.test']);

        $response = $this->postJson(route('password.otp.send'), ['email' => $user->email])
            ->assertOk()
            ->assertJson([
                'message' => 'Jika email tersebut terdaftar, kode OTP akan dikirim. Periksa kotak masuk Anda.',
            ]);

        $otp = null;
        Notification::assertSentTo($user, PasswordResetOtp::class, function (PasswordResetOtp $notification, array $channels) use (&$otp): bool {
            $otp = $notification->token;

            return in_array('mail', $channels, true);
        });

        $this->assertIsString($otp);
        $this->assertSame(64, strlen($otp));
        $response->assertDontSee($otp);
        $this->assertDatabaseHas('password_reset_tokens', ['email' => $user->email]);
        $this->assertNotSame($otp, DB::table('password_reset_tokens')->where('email', $user->email)->value('token'));
        $this->assertTrue(Hash::check($otp, DB::table('password_reset_tokens')->where('email', $user->email)->value('token')));
    }

    public function test_unknown_email_receives_the_same_response_and_is_rate_limited_per_account(): void
    {
        Notification::fake();

        $unknownResponse = $this->postJson(route('password.otp.send'), [
            'email' => 'unknown@example.test',
        ])->assertOk();

        $user = User::factory()->create(['email' => 'reset@example.test']);
        $this->postJson(route('password.otp.send'), ['email' => $user->email])
            ->assertExactJson($unknownResponse->json());

        $this->postJson(route('password.otp.send'), ['email' => $user->email])
            ->assertStatus(429)
            ->assertJsonPath('message', 'Permintaan terlalu sering. Tunggu sebentar sebelum meminta OTP baru.');

        Notification::assertSentToTimes($user, PasswordResetOtp::class, 1);
    }

    public function test_it_refuses_to_send_otp_through_the_log_mailer(): void
    {
        Notification::fake();
        config(['mail.default' => 'log']);
        $user = User::factory()->create(['email' => 'reset@example.test']);

        $this->postJson(route('password.otp.send'), ['email' => $user->email])
            ->assertStatus(503)
            ->assertJsonPath(
                'message',
                'Pengiriman email belum diaktifkan. Atur MAIL_MAILER ke layanan SMTP sebelum meminta OTP.'
            );

        $this->assertDatabaseMissing('password_reset_tokens', ['email' => $user->email]);
        Notification::assertNothingSent();
    }

    public function test_it_rejects_wrong_and_reused_otp_and_accepts_a_valid_reset(): void
    {
        $user = User::factory()->create([
            'email' => 'reset@example.test',
            'password' => 'OldPassword123',
        ]);
        $otp = Password::broker()->createToken($user);

        $this->postJson(route('password.otp.reset'), [
            'email' => $user->email,
            'otp' => str_repeat('x', 64),
            'password' => 'NewPassword123',
            'password_confirmation' => 'NewPassword123',
        ])->assertUnprocessable()
            ->assertJsonPath('message', 'Kode OTP tidak valid atau sudah kedaluwarsa. Minta kode baru dan coba kembali.');

        $this->postJson(route('password.otp.reset'), [
            'email' => $user->email,
            'otp' => $otp,
            'password' => 'NewPassword123',
            'password_confirmation' => 'DifferentPassword123',
        ])->assertUnprocessable()
            ->assertJsonValidationErrors(['password']);

        $this->assertTrue(Hash::check('OldPassword123', $user->fresh()->password));

        $this->postJson(route('password.otp.reset'), [
            'email' => $user->email,
            'otp' => $otp,
            'password' => 'NewPassword123',
            'password_confirmation' => 'NewPassword123',
        ])->assertOk()
            ->assertJsonPath('message', 'Password berhasil diubah. Silakan kembali ke Login dengan password baru.');

        $this->assertTrue(Hash::check('NewPassword123', $user->fresh()->password));
        $this->assertDatabaseMissing('password_reset_tokens', ['email' => $user->email]);

        $this->postJson(route('password.otp.reset'), [
            'email' => $user->email,
            'otp' => $otp,
            'password' => 'AnotherPassword123',
            'password_confirmation' => 'AnotherPassword123',
        ])->assertUnprocessable();

        $this->post(route('login'), [
            'email' => $user->email,
            'password' => 'NewPassword123',
        ])->assertRedirect(route('home'));

        $this->assertAuthenticatedAs($user);
    }

    public function test_it_rejects_an_expired_otp(): void
    {
        $user = User::factory()->create([
            'email' => 'expired@example.test',
            'password' => 'OldPassword123',
        ]);
        $otp = Password::broker()->createToken($user);

        $this->travel(6)->minutes();

        $this->postJson(route('password.otp.reset'), [
            'email' => $user->email,
            'otp' => $otp,
            'password' => 'NewPassword123',
            'password_confirmation' => 'NewPassword123',
        ])->assertUnprocessable();

        $this->assertTrue(Hash::check('OldPassword123', $user->fresh()->password));
    }
}
