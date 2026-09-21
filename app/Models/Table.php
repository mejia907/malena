<?php

namespace App\Models;

use App\Enums\TableStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Table extends Model
{
    protected $fillable = ['name', 'capacity', 'status'];

    protected $casts = [
        'status' => TableStatus::class,
    ];

    // Todos los pedidos históricos de la mesa
    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }

    // Pedido activo actual (si existe) — evita repetir where() en los controladores
    public function activeOrder(): HasOne
    {
        return $this->hasOne(Order::class)->where('status', 'open');
    }
}
