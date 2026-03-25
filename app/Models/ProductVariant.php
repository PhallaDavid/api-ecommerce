<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ProductVariant extends Model
{
    use HasFactory;

    protected $fillable = [
        'product_id',
        'name',
        'sku',
        'price',
        'sale_price',
        'stock',
        'is_in_stock',
        'image',
        'options',
        'status',
    ];

    protected $casts = [
        'options' => 'array',
        'is_in_stock' => 'boolean',
        'price' => 'decimal:2',
        'sale_price' => 'decimal:2',
    ];
}
