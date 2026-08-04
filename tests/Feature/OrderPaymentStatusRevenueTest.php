<?php

use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

function makeTestProduct(array $overrides = []): Product
{
    return Product::query()->create(array_merge([
        'title' => 'Test Perfume',
        'description' => 'Test description',
        'price' => 100000,
        'image_1' => 'products/test.jpg',
        'bottle_size' => 50,
        'perfume_type' => 'EDP',
        'gender' => 'unisex',
        'quantity' => 50,
    ], $overrides));
}

it('counts only successful payments toward admin revenue', function () {
    $admin = User::factory()->create([
        'is_admin' => true,
        'email_verified_at' => now(),
    ]);

    $product = makeTestProduct();

    Order::query()->create([
        'id' => 'INV-SUCCESS',
        'user_id' => $admin->id,
        'product_id' => $product->id,
        'quantity' => 1,
        'total_price' => 100000,
        'shipping_cost' => 10000,
        'promo_discount' => 0,
        'status' => 'menunggu_konfirmasi',
        'metode_pembayaran' => 'QRIS',
        'payment_status' => Order::PAYMENT_SUCCESS,
    ]);

    Order::query()->create([
        'id' => 'INV-PENDING',
        'user_id' => $admin->id,
        'product_id' => $product->id,
        'quantity' => 1,
        'total_price' => 200000,
        'shipping_cost' => 0,
        'promo_discount' => 0,
        'status' => 'menunggu_konfirmasi',
        'metode_pembayaran' => 'Cash on Delivery',
        'payment_status' => Order::PAYMENT_PENDING,
    ]);

    Order::query()->create([
        'id' => 'INV-CANCEL',
        'user_id' => $admin->id,
        'product_id' => $product->id,
        'quantity' => 1,
        'total_price' => 300000,
        'shipping_cost' => 0,
        'promo_discount' => 0,
        'status' => 'dibatalkan',
        'metode_pembayaran' => 'Cash on Delivery',
        'payment_status' => Order::PAYMENT_CANCELLED,
    ]);

    Sanctum::actingAs($admin);

    $response = $this->getJson('/api/admin/revenue');

    $response->assertSuccessful()
        ->assertJsonPath('data.total_revenue', 110000)
        ->assertJsonPath('data.total_revenue_clean', 100000)
        ->assertJsonPath('data.total_orders_count', 1);
});

it('marks cancelled fulfillment as cancelled payment', function () {
    $admin = User::factory()->create([
        'is_admin' => true,
        'email_verified_at' => now(),
    ]);

    $product = makeTestProduct(['price' => 50000, 'quantity' => 10]);

    $order = Order::query()->create([
        'id' => 'INV-COD-1',
        'user_id' => $admin->id,
        'product_id' => $product->id,
        'quantity' => 1,
        'total_price' => 50000,
        'shipping_cost' => 0,
        'promo_discount' => 0,
        'status' => 'menunggu_konfirmasi',
        'metode_pembayaran' => 'Cash on Delivery',
        'payment_status' => Order::PAYMENT_PENDING,
    ]);

    Sanctum::actingAs($admin);

    $this->postJson("/api/orders/{$order->id}/status", [
        'status' => 'dibatalkan',
    ])->assertSuccessful()
        ->assertJsonPath('data.payment_status', 'cancelled');
});
