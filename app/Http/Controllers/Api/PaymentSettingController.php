<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\PaymentSetting;
use App\PaymentProvider;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class PaymentSettingController extends Controller
{
    /** GET /api/payment-settings — public (checkout) */
    public function publicShow()
    {
        $settings = PaymentSetting::current();

        return response()->json([
            'success' => true,
            'data' => [
                'provider' => $settings->provider?->value ?? 'manual',
                'is_production' => (bool) $settings->is_production,
                'configured' => $settings->isConfigured(),
                'client_key' => $settings->usesMidtrans() ? $settings->client_key : null,
            ],
        ]);
    }

    /** GET /api/admin/payment-settings */
    public function show()
    {
        $settings = PaymentSetting::current();

        return response()->json([
            'success' => true,
            'data' => [
                'provider' => $settings->provider?->value ?? 'manual',
                'is_production' => (bool) $settings->is_production,
                'merchant_id' => $settings->merchant_id,
                'client_key' => $settings->client_key,
                'server_key_masked' => $settings->maskedServerKey(),
                'has_server_key' => filled($settings->server_key),
                'configured' => $settings->isConfigured(),
                'updated_by' => $settings->updated_by,
                'updated_at' => $settings->updated_at,
            ],
        ]);
    }

    /** PUT/PATCH /api/admin/payment-settings */
    public function update(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'provider' => ['sometimes', 'required', Rule::enum(PaymentProvider::class)],
            'is_production' => ['sometimes', 'boolean'],
            'merchant_id' => ['sometimes', 'nullable', 'string', 'max:100'],
            'client_key' => ['sometimes', 'nullable', 'string', 'max:255'],
            'server_key' => ['sometimes', 'nullable', 'string', 'max:255'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validasi gagal.',
                'errors' => $validator->errors(),
            ], 422);
        }

        $settings = PaymentSetting::current();
        $data = $validator->validated();

        if (array_key_exists('server_key', $data) && trim((string) $data['server_key']) === '') {
            unset($data['server_key']);
        }

        if (array_key_exists('client_key', $data) && trim((string) ($data['client_key'] ?? '')) === '') {
            $data['client_key'] = null;
        }

        $nextProvider = isset($data['provider'])
            ? PaymentProvider::from((string) $data['provider'])
            : $settings->provider;

        $nextClientKey = array_key_exists('client_key', $data)
            ? $data['client_key']
            : $settings->client_key;
        $nextServerKey = array_key_exists('server_key', $data)
            ? $data['server_key']
            : $settings->server_key;

        if (
            in_array($nextProvider, [PaymentProvider::Midtrans, PaymentProvider::Xendit], true)
            && (! filled($nextClientKey) || ! filled($nextServerKey))
        ) {
            $isXendit = $nextProvider === PaymentProvider::Xendit;

            throw ValidationException::withMessages([
                'client_key' => [
                    $isXendit
                        ? 'Callback token dan secret key wajib diisi saat Xendit aktif.'
                        : 'Client key dan server key wajib diisi saat Midtrans aktif.',
                ],
            ]);
        }

        $data['updated_by'] = $request->user()?->id;
        $settings->fill($data);
        $settings->save();

        return $this->show();
    }
}
