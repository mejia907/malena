<?php

namespace App\Models;

use App\Enums\OrderStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Order extends Model
{
    protected $fillable = [
        'table_id', 'status', 'total', 'total_cost',
        'cancel_reason', 'opened_at', 'closed_at',
    ];

    protected $casts = [
        'total'      => 'decimal:2',
        'total_cost' => 'decimal:2',
        'opened_at'  => 'datetime',
        'closed_at'  => 'datetime',
        'status'     => OrderStatus::class,
    ];

    public function table(): BelongsTo
    {
        return $this->belongsTo(Table::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    // Un pedido solo puede tener un pago vigente (no reversado)
    public function payment(): HasOne
    {
        return $this->hasOne(Payment::class)->whereNull('reversed_at');
    }

    // Recalcula total y total_cost sumando los items — se llama tras cada cambio en order_items
    public function recalculateTotals(): void
    {
        $this->loadMissing('items');

        $this->update([
            'total'      => $this->items->sum('subtotal'),
            'total_cost' => $this->items->sum(fn ($item) => $item->unit_cost * $item->quantity),
        ]);
    }

    // Ganancia neta del pedido (para reportes)
    public function getProfitAttribute(): float
    {
        return (float) $this->total - (float) $this->total_cost;
    }
}