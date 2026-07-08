<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Product extends Model
{
    use HasFactory;

    protected $fillable = [
        'title',
        'description',
        'color',
        'price',
        'personality_type',
        'top_note',
        'middle_note',
        'base_note',
        'image_1',
        'image_2',
        'image_3',
        'image_4',
        'image_produk_belanja',
        'bottle_size',
        'perfume_type',
        'gender',
        'quantity',
        'stock_status',
    ];
}