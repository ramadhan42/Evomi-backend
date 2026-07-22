<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class QuizPersonalityResult extends Model
{
    protected $fillable = [
        'personality_key',
        'title',
        'title_en',
        'description',
        'description_en',
        'color',
        'bg_image',
        'product_image',
        'forced_product_id',
    ];
}
