<?php

namespace App\Notifications;

use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class PasswordResetOtp extends Notification
{
    public function __construct(#[\SensitiveParameter] public readonly string $token) {}

    /**
     * @return list<string>
     */
    public function via($notifiable): array
    {
        return ['mail'];
    }

    public function toMail($notifiable): MailMessage
    {
        $expiresIn = config('auth.passwords.'.config('auth.defaults.passwords').'.expire', 5);

        return (new MailMessage)
            ->subject('Kode OTP Reset Password A.I-Check')
            ->greeting('Permintaan reset password')
            ->line('Gunakan kode berikut untuk mengatur ulang password Anda:')
            ->line($this->token)
            ->line("Kode ini hanya berlaku selama {$expiresIn} menit dan hanya dapat digunakan satu kali.")
            ->line('Jika Anda tidak meminta reset password, abaikan email ini.');
    }
}
