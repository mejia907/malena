<?php

namespace App\Models;

use App\Enums\PaymentMethod;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Payment extends Model
{
    protected $fillable = [
        'order_id', 'method', 'amount', 'paid_at',
        'reversed_at', 'reversed_reason',
    ];

    protected $casts = [
        'amount'      => 'decimal:2',
        'paid_at'     => 'datetime',
        'reversed_at' => 'datetime',
        'method'      => PaymentMethod::class,
    ];

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    // true si el pago sigue vigente (no fue anulado)
    public function getIsValidAttribute(): bool
    {
        return is_null($this->reversed_at);
    }
}