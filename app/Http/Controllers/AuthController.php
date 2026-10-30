<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password as PasswordRule;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    // Menampilkan halaman autentikasi.
    public function index(): RedirectResponse|View
    {
        if (Auth::check()) {
            return redirect()->route('home');
        }

        return view('autentikasi.index');
    }

    public function register(Request $request): RedirectResponse
    {
        $request->session()->flash('auth_mode', 'register');

        $validated = $request->validate([
            'name' => ['required', 'string', 'min:3', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'confirmed', PasswordRule::min(8)->letters()->numbers()],
        ], [
            'name.required' => 'Nama wajib diisi.',
            'name.min' => 'Nama minimal 3 karakter.',
            'name.max' => 'Nama maksimal 255 karakter.',
            'email.required' => 'Email wajib diisi.',
            'email.email' => 'Masukkan alamat email yang valid.',
            'email.max' => 'Email maksimal 255 karakter.',
            'email.unique' => 'Email tersebut sudah terdaftar.',
            'password.required' => 'Kata sandi wajib diisi.',
            'password.confirmed' => 'Konfirmasi kata sandi tidak cocok.',
            'password.min' => 'Kata sandi minimal 8 karakter.',
            'password.letters' => 'Kata sandi harus mengandung huruf.',
            'password.numbers' => 'Kata sandi harus mengandung angka.',
        ]);

        $user = User::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => Hash::make($validated['password']),
        ]);

        Auth::login($user);
        $request->session()->regenerate();

        return redirect()->intended(route('home'))->with('status', 'Pendaftaran berhasil. Anda sudah masuk.');
    }

    public function login(Request $request): RedirectResponse
    {
        $request->session()->flash('auth_mode', 'login');

        $validated = $request->validate([
            'email' => ['required', 'string', 'email'],
            'password' => ['required', 'string'],
        ], [
            'email.required' => 'Email wajib diisi.',
            'email.email' => 'Masukkan alamat email yang valid.',
            'password.required' => 'Kata sandi wajib diisi.',
        ]);

        if (! Auth::attempt($validated, $request->boolean('remember'))) {
            throw ValidationException::withMessages([
                'email' => 'Email atau kata sandi yang Anda masukkan salah.',
            ]);
        }

        $request->session()->regenerate();

        return redirect()->intended(route('home'));
    }

    public function sendPasswordResetOtp(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'email' => ['required', 'string', 'email', 'max:255'],
        ], [
            'email.required' => 'Email wajib diisi.',
            'email.email' => 'Masukkan alamat email yang valid.',
            'email.max' => 'Email maksimal 255 karakter.',
        ]);

        if ($this->mailerDoesNotDeliverSecurely((string) config('mail.default', 'log'))) {
            return response()->json([
                'message' => 'Pengiriman email belum diaktifkan. Atur MAIL_MAILER ke layanan SMTP sebelum meminta OTP.',
            ], 503);
        }

        $status = Password::sendResetLink(['email' => $validated['email']]);

        if ($status === Password::RESET_THROTTLED) {
            return response()->json([
                'message' => 'Permintaan terlalu sering. Tunggu sebentar sebelum meminta OTP baru.',
            ], 429);
        }

        if (! in_array($status, [Password::RESET_LINK_SENT, Password::INVALID_USER], true)) {
            return response()->json([
                'message' => 'Permintaan OTP tidak dapat diproses saat ini.',
            ], 500);
        }

        return response()->json([
            'message' => 'Jika email tersebut terdaftar, kode OTP akan dikirim. Periksa kotak masuk Anda.',
        ]);
    }

    public function resetPassword(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'email' => ['required', 'string', 'email', 'max:255'],
            'otp' => ['required', 'string', 'max:64'],
            'password' => ['required', 'confirmed', PasswordRule::min(8)->letters()->numbers()],
        ], [
            'email.required' => 'Email wajib diisi.',
            'email.email' => 'Masukkan alamat email yang valid.',
            'email.max' => 'Email maksimal 255 karakter.',
            'otp.required' => 'Kode OTP wajib diisi.',
            'otp.max' => 'Kode OTP tidak valid.',
            'password.required' => 'Password baru wajib diisi.',
            'password.confirmed' => 'Konfirmasi password baru tidak cocok.',
            'password.min' => 'Password minimal 8 karakter.',
            'password.letters' => 'Password harus mengandung huruf.',
            'password.numbers' => 'Password harus mengandung angka.',
        ]);

        $status = Password::reset([
            'email' => $validated['email'],
            'token' => $validated['otp'],
            'password' => $validated['password'],
            'password_confirmation' => $request->input('password_confirmation'),
        ], function (User $user, string $password): void {
            $user->forceFill([
                'password' => Hash::make($password),
                'remember_token' => Str::random(60),
            ])->save();

            event(new PasswordReset($user));
        });

        if ($status !== Password::PASSWORD_RESET) {
            return response()->json([
                'message' => 'Kode OTP tidak valid atau sudah kedaluwarsa. Minta kode baru dan coba kembali.',
            ], 422);
        }

        return response()->json([
            'message' => 'Password berhasil diubah. Silakan kembali ke Login dengan password baru.',
        ]);
    }

    private function mailerDoesNotDeliverSecurely(string $mailer, array $visited = []): bool
    {
        if (in_array($mailer, $visited, true)) {
            return false;
        }

        $visited[] = $mailer;
        $settings = config("mail.mailers.{$mailer}", []);
        $transport = $settings['transport'] ?? $mailer;

        if (in_array($transport, ['log', 'array'], true)) {
            return true;
        }

        foreach ($settings['mailers'] ?? [] as $fallbackMailer) {
            if ($this->mailerDoesNotDeliverSecurely($fallbackMailer, $visited)) {
                return true;
            }
        }

        return false;
    }

    public function logout(Request $request): RedirectResponse
    {
        Auth::guard('web')->logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('home')->with('logout_success', true);
    }
}
