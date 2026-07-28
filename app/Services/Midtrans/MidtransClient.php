<?php

namespace App\Services\Midtrans;

use App\Models\PaymentSetting;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use Illuminate\Validation\ValidationException;
use RuntimeException;

class MidtransClient
{
    public function __construct(private ?PaymentSetting $settings = null) {}

    public function settings(): PaymentSetting
    {
        return $this->settings ??= PaymentSetting::current();
    }

    /**
     * @return array{token: string, redirect_url?: string|null}
     */
    public function createSnapTransaction(array $payload): array
    {
        $settings = $this->settings();

        if (! $settings->usesMidtrans() || ! $settings->isConfigured()) {
            throw ValidationException::withMessages([
                'payment' => ['Midtrans belum dikonfigurasi. Isi kredensial di Pengaturan Pembayaran.'],
            ]);
        }

        try {
            $response = Http::withBasicAuth((string) $settings->server_key, '')
                ->acceptJson()
                ->asJson()
                ->timeout(30)
                ->post($this->snapBaseUrl().'/snap/v1/transactions', $payload)
                ->throw()
                ->json();
        } catch (RequestException $exception) {
            $message = data_get($exception->response?->json(), 'error_messages.0')
                ?? data_get($exception->response?->json(), 'status_message')
                ?? 'Gagal membuat transaksi Midtrans Snap.';

            throw ValidationException::withMessages([
                'payment' => [is_string($message) ? $message : 'Gagal membuat transaksi Midtrans Snap.'],
            ]);
        }

        $token = $response['token'] ?? null;

        if (! is_string($token) || $token === '') {
            throw new RuntimeException('Respons Midtrans Snap tidak berisi token.');
        }

        return [
            'token' => $token,
            'redirect_url' => is_string($response['redirect_url'] ?? null)
                ? $response['redirect_url']
                : null,
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function verifyNotificationSignature(array $payload): bool
    {
        $orderId = (string) ($payload['order_id'] ?? '');
        $statusCode = (string) ($payload['status_code'] ?? '');
        $grossAmount = (string) ($payload['gross_amount'] ?? '');
        $signature = (string) ($payload['signature_key'] ?? '');
        $serverKey = (string) $this->settings()->server_key;

        if ($orderId === '' || $statusCode === '' || $grossAmount === '' || $signature === '' || $serverKey === '') {
            return false;
        }

        $expected = hash('sha512', $orderId.$statusCode.$grossAmount.$serverKey);

        return hash_equals($expected, $signature);
    }

    private function snapBaseUrl(): string
    {
        return $this->settings()->is_production
            ? 'https://app.midtrans.com'
            : 'https://app.sandbox.midtrans.com';
    }
}
