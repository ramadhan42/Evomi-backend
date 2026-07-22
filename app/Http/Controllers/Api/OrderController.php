<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Cart;
use App\Models\Order;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class OrderController extends Controller
{
    /**
     * READ: Mengambil detail satu pesanan berdasarkan ID untuk user yang login
     */
    public function show($id, Request $request)
    {
        $user = $request->user();

        $order = Order::with('product')
            ->where('user_id', $user->id)
            ->where('id', $id)
            ->first();

        if (!$order) {
            return response()->json([
                'success' => false,
                'message' => 'Pesanan tidak ditemukan atau Anda tidak memiliki akses.'
            ], 404);
        }

        return response()->json([
            'success' => true,
            'message' => 'Detail pesanan berhasil diambil.',
            'data' => $order
        ], 200);
    }

    /**
     * READ ALL: Mengambil semua pesanan dari semua user (Untuk Admin)
     */
    public function getAllOrders()
    {
        // Memuat data produk dan user yang melakukan order
        $orders = Order::with(['product', 'user'])->latest()->get();

        return response()->json([
            'success' => true,
            'message' => 'Semua data pesanan berhasil diambil.',
            'data' => $orders
        ], 200);
    }

    /**
     * DASHBOARD STATS: Mengambil ringkasan total pendapatan termasuk ongkir (Untuk Admin)
     */
    public function getTotalRevenue()
    {
        // 1. Hitung total harga produk asli dari semua order
        $totalProductPrice = Order::sum('total_price');

        // 2. Hitung total item/quantity yang terjual dari semua order
        $totalQuantitySold = Order::sum('quantity');

        // 3. Hitung total ongkir (Rp 1.000 per produk/quantity)
        $shippingCostPerItem = 1000;
        $totalShippingRevenue = $totalQuantitySold * $shippingCostPerItem;

        // 4. Akumulasikan total pendapatan bersih + ongkir
        $totalRevenueWithShipping = $totalProductPrice + $totalShippingRevenue;

        // 5. Hitung jumlah transaksi/invoice secara keseluruhan
        $totalOrdersCount = Order::count();

        return response()->json([
            'success' => true,
            'message' => 'Data ringkasan pendapatan admin (termasuk ongkir) berhasil dimuat.',
            'data' => [
                'total_revenue' => (int) $totalRevenueWithShipping,
                'total_revenue_clean' => (int) $totalProductPrice,
                'total_orders_count' => $totalOrdersCount,
                'total_items_sold' => (int) $totalQuantitySold,
                'total_shipping_cost' => (int) $totalShippingRevenue,
                'currency' => 'IDR'
            ]
        ], 200);
    }

    public function checkout(Request $request)
    {
        // 1. Ambil user yang sudah diverifikasi oleh auth:sanctum
        $user = $request->user();

        // 2. Validasi keamanan ganda (Jika token tidak valid / kedaluwarsa)
        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'Sesi login tidak valid. Harap login kembali.'
            ], 401);
        }

        $items = $request->input('items');
        $invoiceId = $request->input('invoice_id');
        $metodePembayaran = $request->input('payment_method', 'Cash on Delivery');

        // 3. Validasi kelengkapan data request
        if (empty($items) || !is_array($items)) {
            return response()->json([
                'success' => false,
                'message' => 'Daftar pesanan kosong atau tidak valid'
            ], 400);
        }

        if (!$invoiceId) {
            return response()->json([
                'success' => false,
                'message' => 'Invoice ID tidak valid'
            ], 400);
        }

        $now = now();

        // 4. Proses Database dengan Transaction yang aman
        try {
            DB::transaction(function () use ($user, $items, $invoiceId, $now, $metodePembayaran) {
                foreach ($items as $index => $item) {
                    $orderId = count($items) > 1 ? "{$invoiceId}-" . ($index + 1) : $invoiceId;

                    Order::create([
                        'id' => $orderId,
                        'user_id' => $user->id, // Sekarang ini aman karena $user pasti ada
                        'product_id' => $item['product_id'],
                        'quantity' => $item['quantity'],
                        'total_price' => $item['price'] * $item['quantity'],
                        'status' => 'menunggu_konfirmasi',
                        'metode_pembayaran' => $metodePembayaran,
                        'created_at' => $now,
                        'updated_at' => $now
                    ]);
                }

                // Kosongkan keranjang user setelah checkout sukses
                Cart::where('user_id', $user->id)->delete();
            });

            return response()->json([
                'success' => true,
                'message' => 'Checkout berhasil!'
            ], 200);

        } catch (\Exception $e) {
            \Log::error('Error Checkout:', ['detail' => $e->getMessage()]);
            return response()->json([
                'success' => false,
                'message' => 'Gagal memproses pembuatan pesanan',
                'error_detail' => $e->getMessage() // Matikan ini jika sudah dipublish ke production
            ], 500);
        }
    }

    public function confirmReceipt($id, Request $request)
    {
        $user = $request->user();
        $order = Order::where('user_id', $user->id)->where('id', $id)->firstOrFail();

        // Konfirmasi seluruh batch checkout (grup by created_at yang sama)
        $updated = Order::where('user_id', $user->id)
            ->where('created_at', $order->created_at)
            ->update(['status' => 'diterima']);

        return response()->json([
            'success' => true,
            'message' => 'Pesanan telah dikonfirmasi diterima.',
            'updated_count' => $updated,
        ]);
    }

    public function destroy($id, Request $request)
    {
        $order = Order::find($id);

        if (!$order) {
            return response()->json(['message' => 'Riwayat pesanan tidak ditemukan'], 404);
        }

        if (!auth()->user()?->is_admin && $order->user_id !== auth()->id()) {
            return response()->json(['message' => 'Anda tidak diizinkan menghapus pesanan ini.'], 403);
        }

        // Hapus seluruh batch yang sama (riwayat grup di frontend)
        $query = Order::where('created_at', $order->created_at);
        if (!auth()->user()?->is_admin) {
            $query->where('user_id', auth()->id());
        }
        $deleted = $query->delete();

        return response()->json([
            'message' => 'Riwayat pesanan berhasil dihapus',
            'deleted_count' => $deleted,
        ], 200);
    }

    /**
     * Skenario Admin / Postman: Memperbarui status dan metode pembayaran pesanan secara spesifik
     */
    // Di dalam App\Http\Controllers\Api\OrderController
    public function updateStatus($id, Request $request)
    {
        // 1. Validasi input
        $request->validate([
            'status' => [
                'required',
                'string',
                Rule::in(['menunggu_konfirmasi', 'pengemasan', 'dalam_perjalanan', 'diterima', 'dibatalkan', 'selesai'])
            ],
            'metode_pembayaran' => 'sometimes|string|nullable'
        ]);

        // 2. Cari data
        $order = Order::find($id);

        if (!$order) {
            return response()->json(['message' => 'Pesanan tidak ditemukan.'], 404);
        }

        // Admin middleware sudah membatasi akses; tetap jaga jika dipanggil tanpa middleware
        if (!auth()->user()?->is_admin) {
            return response()->json(['message' => 'Anda tidak diizinkan memperbarui pesanan ini.'], 403);
        }

        $order->status = $request->status;

        if ($request->has('metode_pembayaran')) {
            $order->metode_pembayaran = $request->metode_pembayaran;
        }

        $order->save();

        return response()->json([
            'success' => true,
            'message' => 'Data pesanan berhasil diperbarui.',
            'data' => $order
        ], 200);
    }
}