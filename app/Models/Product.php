<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Product extends Model
{
    use HasFactory;

    protected $fillable = [
        'title',
        'title_en',
        'description',
        'description_en',
        'color',
        'price',
        'personality_type',
        'personality_type_en',
        'top_note',
        'top_note_en',
        'middle_note',
        'middle_note_en',
        'base_note',
        'base_note_en',
        'image_1',
        'image_2',
        'image_3',
        'image_4',
        'image_produk_belanja',
        'bottle_size',
        'perfume_type',
        'perfume_type_en',
        'gender',
        'gender_en',
        'quantity',
        'stock_status',
        'stock_status_en',
        'alamat_awal_pengiriman',
        'kondisi',
        'kondisi_en',
        'kategori',
        'kategori_en',
        'berat_satuan',
        'brand',
        'brand_en',
        'etalase',
        'etalase_en',
    ];
}