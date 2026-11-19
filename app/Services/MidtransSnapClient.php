<?php

namespace App\Services;

use App\Models\Payment;
use App\Models\Plan;
use App\Models\User;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class MidtransSnapClient
{
    public function assertConfigured(): void
    {
        $serverKey = config('midtrans.server_key');

        if (! is_string($serverKey) || trim($serverKey) === '') {
            throw new RuntimeException('Konfigurasi Midtrans belum lengkap.');
        }
    }

    /**
     * @return array{redirect_url: string, transaction_id: string|null}
     */
    public function createCheckout(Payment $payment, Plan $plan, User $user, string $finishUrl): array
    {
        $this->assertConfigured();

        $serverKey = (string) config('midtrans.server_key');
        $baseUrl = config('midtrans.is_production')
            ? 'https://app.midtrans.com/snap/v1'
            : 'https://app.sandbox.midtrans.com/snap/v1';
        $orderId = $this->orderId($payment);
        $amount = (float) $payment->amount;

        try {
            $response = Http::acceptJson()
                ->asJson()
                ->withBasicAuth($serverKey, '')
                ->connectTimeout(5)
                ->timeout(20)
                ->post($baseUrl.'/transactions', [
                    'transaction_details' => [
                        'order_id' => $orderId,
                        'gross_amount' => $amount,
                    ],
                    'item_details' => [[
                        'id' => (string) $plan->getKey(),
                        'price' => $amount,
                        'quantity' => 1,
                        'name' => mb_substr($plan->name, 0, 50),
                    ]],
                    'customer_details' => [
                        'first_name' => mb_substr($user->name, 0, 50),
                        'email' => $user->email,
                    ],
                    'callbacks' => [
                        'finish' => $finishUrl,
                    ],
                ]);
        } catch (ConnectionException $exception) {
            throw new RuntimeException('Midtrans tidak dapat dihubungi.', previous: $exception);
        }

        if (! $response->successful()) {
            throw new RuntimeException('Midtrans gagal membuat sesi pembayaran.');
        }

        $payload = $response->json();
        $redirectUrl = is_array($payload) ? ($payload['redirect_url'] ?? null) : null;
        $transactionId = is_array($payload) ? ($payload['transaction_id'] ?? null) : null;

        if (! is_string($redirectUrl) || ! $this->isTrustedRedirect($redirectUrl)) {
            throw new RuntimeException('Midtrans mengembalikan URL pembayaran yang tidak valid.');
        }

        return [
            'redirect_url' => $redirectUrl,
            'transaction_id' => is_string($transactionId) ? $transactionId : null,
        ];
    }

    public function orderId(Payment $payment): string
    {
        return 'AIC-PAY-'.$payment->getKey();
    }

    private function isTrustedRedirect(string $url): bool
    {
        $parts = parse_url($url);

        if (! is_array($parts) || ($parts['scheme'] ?? null) !== 'https') {
            return false;
        }

        $expectedHost = config('midtrans.is_production')
            ? 'app.midtrans.com'
            : 'app.sandbox.midtrans.com';

        return ($parts['host'] ?? null) === $expectedHost
            && isset($parts['path'])
            && str_starts_with($parts['path'], '/snap/');
    }
}
