<?php

namespace App\Traits;

use App\Models\OrderItem;
use App\Models\Product;
use Illuminate\Http\Exceptions\ValidationException;

trait StockReservable
{
    /**
     * Reservar stock para un producto (decrementar stock disponible).
     * Usa bloqueo optimista para evitar condiciones de carrera.
     *
     * @param  int  $quantity
     * @param  string  $operation 'reserve' o 'release'
     * @return bool
     * @throws ValidationException
     */
    protected function reserveStock(Product $product, int $quantity, string $operation = 'reserve'): bool
    {
        $currentStock = $product->fresh()->stock;

        if ($operation === 'reserve') {
            if ($currentStock < $quantity) {
                throw ValidationException::withMessages([
                    'stock' => "Stock insuficiente de \"{$product->name}\" (disponible: {$currentStock}).",
                ]);
            }
        }

        $newStock = $operation === 'reserve'
            ? $currentStock - $quantity
            : $currentStock + $quantity;

        return $product->update(['stock' => $newStock]);
    }

    /**
     * Reservar stock para items de un pedido.
     * Se llama cuando se crea/actualiza un item en un pedido.
     *
     * @param  \App\Models\OrderItem  $item
     * @param  int  $delta  Cambio positivo (añadir) o negativo (quitar)
     * @return void
     * @throws ValidationException
     */
    protected function syncOrderItemStock(OrderItem $item, int $delta = 0): void
    {
        $product = $item->product()->lockForUpdate()->first();

        if ($delta > 0) {
            // Añadir items: reservar stock adicional
            $this->reserveStock($product, $delta, 'reserve');
        } elseif ($delta < 0) {
            // Remover items: liberar stock
            $this->reserveStock($product, abs($delta), 'release');
        }
    }

    /**
     * Obtener la cantidad actual de un item en el pedido (para cálculos de delta).
     *
     * @param  \App\Models\OrderItem  $item
     * @return int
     */
    protected function getCurrentItemQuantity(OrderItem $item): int
    {
        return $item->order->items->where('product_id', $item->product_id)
            ->sum('quantity');
    }
}