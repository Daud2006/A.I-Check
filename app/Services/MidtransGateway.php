<?php

namespace App\Services;

use Midtrans\Config;
use Midtrans\Snap;
use Midtrans\Transaction;
use RuntimeException;

class MidtransGateway
{
    public function createSnapTransaction(array $parameters): object
    {
        $this->configure();

        return Snap::createTransaction($parameters);
    }

    public function transactionStatus(string $orderId): object
    {
        $this->configure();

        return Transaction::status($orderId);
    }

    public function isValidNotificationSignature(array $notification): bool
    {
        $serverKey = config('services.midtrans.server_key');
        $orderId = $notification['order_id'] ?? null;
        $statusCode = $notification['status_code'] ?? null;
        $grossAmount = $notification['gross_amount'] ?? null;
        $signature = $notification['signature_key'] ?? null;

        if (
            ! is_string($serverKey) || $serverKey === ''
            || ! is_string($orderId) || $orderId === ''
            || (! is_string($statusCode) && ! is_int($statusCode))
            || (! is_string($grossAmount) && ! is_int($grossAmount) && ! is_float($grossAmount))
            || ! is_string($signature) || $signature === ''
        ) {
            return false;
        }

        $expected = hash('sha512', $orderId.(string) $statusCode.(string) $grossAmount.$serverKey);

        return hash_equals($expected, $signature);
    }

    private function configure(): void
    {
        $serverKey = config('services.midtrans.server_key');
        $clientKey = config('services.midtrans.client_key');

        if (! is_string($serverKey) || $serverKey === '' || ! is_string($clientKey) || $clientKey === '') {
            throw new RuntimeException('Konfigurasi Midtrans belum lengkap.');
        }

        Config::$serverKey = $serverKey;
        Config::$clientKey = $clientKey;
        Config::$isProduction = (bool) config('services.midtrans.is_production', false);
        Config::$is3ds = true;
        Config::$isSanitized = true;
    }
}
