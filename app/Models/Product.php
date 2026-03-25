<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Product extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'slug',
        'sku',
        'description',
        'short_description',
        'price',
        'sale_price',
        'cost_price',
        'promotion_percent',
        'promotion_start_date',
        'promotion_end_date',
        'stock',
        'is_in_stock',
        'image',
        'gallery',
        'category_id',
        'brand_id',
        'is_featured',
        'status',
        'meta_title',
        'meta_description',
    ];

    protected $casts = [
        'gallery' => 'array',
        'promotion_start_date' => 'datetime',
        'promotion_end_date' => 'datetime',
        'is_in_stock' => 'boolean',
        'is_featured' => 'boolean',
        'price' => 'decimal:2',
        'sale_price' => 'decimal:2',
        'cost_price' => 'decimal:2',
    ];
}
