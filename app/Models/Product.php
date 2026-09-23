<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Product extends Model
{
    protected $fillable = [
        'name',
        'cost_price',
        'sale_price',
        'stock',
        'is_active',
        'purchase_unit_label',
        'units_per_purchase_unit',
    ];

    protected $casts = [
        'cost_price' => 'decimal:2',
        'sale_price' => 'decimal:2',
        'is_active'  => 'boolean',
    ];

    public function orderItems(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    public function purchases(): HasMany
    {
        return $this->hasMany(ProductPurchase::class);
    }

    // Ganancia unitaria — usada en reportes sin repetir la resta en cada vista
    public function getProfitMarginAttribute(): float
    {
        return (float) $this->sale_price - (float) $this->cost_price;
    }

    // true si este producto se compra empaquetado pero se vende suelto
    public function getIsPackagedAttribute(): bool
    {
        return ! is_null($this->units_per_purchase_unit);
    }

    public function wastes(): HasMany
    {
        return $this->hasMany(ProductWaste::class);
    }

    // Registra una compra/reabasto: convierte a unidades individuales, suma al stock
    // y recalcula el costo como PROMEDIO PONDERADO contra el stock que ya había.
    // Esto evita que el costo salte bruscamente si un lote nuevo sale más caro/barato.
    public function restock(int $purchaseQuantity, float $purchaseTotalCost, ?string $note = null): ProductPurchase
    {
        $unitsAdded = $this->is_packaged
            ? $purchaseQuantity * $this->units_per_purchase_unit
            : $purchaseQuantity;

        $unitCost = round($purchaseTotalCost / $unitsAdded, 2);

        $newAverageCost = $this->stock > 0
            ? (($this->stock * (float) $this->cost_price) + ($unitsAdded * $unitCost)) / ($this->stock + $unitsAdded)
            : $unitCost;

        $purchase = $this->purchases()->create([
            'purchase_quantity'   => $purchaseQuantity,
            'purchase_total_cost' => $purchaseTotalCost,
            'units_added'         => $unitsAdded,
            'unit_cost'           => $unitCost,
            'note'                => $note,
            'purchased_at'        => now(),
        ]);

        $this->update([
            'stock'      => $this->stock + $unitsAdded,
            'cost_price' => round($newAverageCost, 2),
        ]);

        return $purchase;
    }

    // Registra producto perdido (no vendido y ya no aprovechable, dañado, o consumo interno).
    // Descuenta stock y deja registro del costo perdido, para que los reportes reflejen la pérdida real.
    public function registerWaste(int $quantity, string $reason, ?string $note = null): ProductWaste
    {
        $unitCost = (float) $this->cost_price;

        $waste = $this->wastes()->create([
            'quantity'   => $quantity,
            'unit_cost'  => $unitCost,
            'total_cost' => round($unitCost * $quantity, 2),
            'reason'     => $reason,
            'note'       => $note,
            'wasted_at'  => now(),
        ]);

        $this->decrement('stock', $quantity);

        return $waste;
    }
}
