<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\PaymentSetting;
use App\Services\Midtrans\MidtransClient;
use App\Services\Xendit\XenditClient;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class PaymentGatewayController extends Controller
{
    /**
     * POST /api/payments/xendit/qr
     * Body: { reference_id, amount, expires_at? }
     */
    public function createXenditQr(Request $request, XenditClient $xendit)
    {
        $validator = Validator::make($request->all(), [
            'reference_id' => 'required|string|max:80',
            'amount' => 'required|numeric|min:1',
            'expires_at' => 'nullable|date',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validasi gagal.',
                'errors' => $validator->errors(),
            ], 422);
        }

        $data = $validator->validated();
        $expiresAt = $data['expires_at']
            ?? now()->addMinutes(15)->toIso8601String();

        try {
            $qr = $xendit->createQrCode([
                'reference_id' => $data['reference_id'],
                'type' => 'DYNAMIC',
                'currency' => 'IDR',
                'amount' => (int) round((float) $data['amount']),
                'expires_at' => $expiresAt,
            ]);

            return response()->json([
                'success' => true,
                'data' => [
                    'id' => $qr['id'],
                    'qr_string' => $qr['qr_string'],
                    'status' => $qr['status'] ?? null,
                    'reference_id' => $qr['reference_id'] ?? $data['reference_id'],
                    'invoice_id' => $data['reference_id'],
                ],
            ]);
        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => collect($e->errors())->flatten()->first() ?: 'Gagal membuat QRIS.',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Throwable $e) {
            Log::error('Xendit QR create failed', ['detail' => $e->getMessage()]);

            return response()->json([
                'success' => false,
                'message' => 'Gagal membuat QRIS Xendit.',
            ], 500);
        }
    }

    /** GET /api/payments/xendit/qr/{id} */
    public function showXenditQr(string $id, XenditClient $xendit)
    {
        try {
            $qr = $xendit->getQrCode($id);

            return response()->json([
                'success' => true,
                'data' => [
                    'id' => $qr['id'] ?? $id,
                    'status' => $qr['status'] ?? null,
                    'reference_id' => $qr['reference_id'] ?? null,
                ],
            ]);
        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => collect($e->errors())->flatten()->first() ?: 'Gagal cek status QRIS.',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Throwable $e) {
            Log::error('Xendit QR status failed', ['detail' => $e->getMessage()]);

            return response()->json([
                'success' => false,
                'message' => 'Gagal mengecek status QRIS.',
            ], 500);
        }
    }

    /**
     * POST /api/payments/midtrans/snap
     * Body: { order_id, amount, customer_name?, customer_email?, item_name? }
     */
    public function createMidtransSnap(Request $request, MidtransClient $midtrans)
    {
        $validator = Validator::make($request->all(), [
            'order_id' => 'required|string|max:80',
            'amount' => 'required|numeric|min:1',
            'customer_name' => 'nullable|string|max:255',
            'customer_email' => 'nullable|email|max:255',
            'item_name' => 'nullable|string|max:255',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validasi gagal.',
                'errors' => $validator->errors(),
            ], 422);
        }

        $data = $validator->validated();
        $amount = (int) round((float) $data['amount']);
        $settings = PaymentSetting::current();

        try {
            $snap = $midtrans->createSnapTransaction([
                'transaction_details' => [
                    'order_id' => $data['order_id'],
                    'gross_amount' => $amount,
                ],
                'customer_details' => array_filter([
                    'first_name' => $data['customer_name'] ?? null,
                    'email' => $data['customer_email'] ?? null,
                ]),
                'item_details' => [[
                    'id' => 'evomi-order',
                    'price' => $amount,
                    'quantity' => 1,
                    'name' => $data['item_name'] ?? 'Pesanan Evomi',
                ]],
            ]);

            return response()->json([
                'success' => true,
                'data' => [
                    'token' => $snap['token'],
                    'redirect_url' => $snap['redirect_url'] ?? null,
                    'client_key' => $settings->client_key,
                    'is_production' => (bool) $settings->is_production,
                    'order_id' => $data['order_id'],
                ],
            ]);
        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => collect($e->errors())->flatten()->first() ?: 'Gagal membuat Snap.',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Throwable $e) {
            Log::error('Midtrans Snap create failed', ['detail' => $e->getMessage()]);

            return response()->json([
                'success' => false,
                'message' => 'Gagal membuat transaksi Midtrans.',
            ], 500);
        }
    }

    /** POST /api/payments/midtrans/notification — webhook stub (ack + verify) */
    public function midtransNotification(Request $request, MidtransClient $midtrans)
    {
        $payload = $request->all();

        if (! $midtrans->verifyNotificationSignature($payload)) {
            return response()->json(['success' => false, 'message' => 'Invalid signature'], 403);
        }

        Log::info('Midtrans notification received', [
            'order_id' => $payload['order_id'] ?? null,
            'transaction_status' => $payload['transaction_status'] ?? null,
        ]);

        return response()->json(['success' => true]);
    }

    /** POST /api/payments/xendit/notification — webhook stub */
    public function xenditNotification(Request $request, XenditClient $xendit)
    {
        $token = $request->header('x-callback-token');

        if (! $xendit->verifyCallbackToken($token)) {
            return response()->json(['success' => false, 'message' => 'Invalid callback token'], 403);
        }

        Log::info('Xendit notification received', [
            'id' => $request->input('id'),
            'status' => $request->input('status'),
            'reference_id' => $request->input('reference_id'),
        ]);

        return response()->json(['success' => true]);
    }
}
