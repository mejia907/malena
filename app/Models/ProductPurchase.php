<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductPurchase extends Model
{
    protected $fillable = [
        'product_id', 'purchase_quantity', 'purchase_total_cost',
        'units_added', 'unit_cost', 'note', 'purchased_at',
    ];

    protected $casts = [
        'purchase_total_cost' => 'decimal:2',
        'unit_cost'           => 'decimal:2',
        'purchased_at'        => 'datetime',
    ];

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}