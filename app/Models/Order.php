<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Order extends Model
{
    // Tambahkan metode_pembayaran di sini
    protected $fillable = [
        'id',
        'user_id',
        'guest_email',
        'product_id',
        'quantity',
        'total_price',
        'shipping_cost',
        'promo_discount',
        'status',
        'metode_pembayaran',
    ];

    protected $casts = [
        'total_price' => 'decimal:2',
        'shipping_cost' => 'decimal:2',
        'promo_discount' => 'decimal:2',
        'quantity' => 'integer',
    ];

    protected $appends = [
        'grand_total',
    ];

    public $incrementing = false;      // Matikan auto-increment
    protected $keyType = 'string';

    /**
     * Total bayar = harga produk (katalog) + ongkir − promo.
     */
    public function getGrandTotalAttribute(): float
    {
        return max(
            0,
            (float) $this->total_price
                + (float) ($this->shipping_cost ?? 0)
                - (float) ($this->promo_discount ?? 0)
        );
    }

    // Relasi: Satu pesanan milik satu produk
    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    // Relasi: Satu pesanan milik satu user
    public function user()
    {
        return $this->belongsTo(User::class);
    }
}