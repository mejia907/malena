<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CashClosure extends Model
{
    protected $fillable = [
        'period_start',
        'period_end',
        'total_sales',
        'total_cash',
        'total_transfer',
        'total_cost',
        'total_profit',
        'total_waste_cost',
        'net_profit_after_waste',
        'orders_count',
    ];

    protected $casts = [
        'period_start'            => 'datetime',
        'period_end'               => 'datetime',
        'total_sales'              => 'decimal:2',
        'total_cash'               => 'decimal:2',
        'total_transfer'           => 'decimal:2',
        'total_cost'               => 'decimal:2',
        'total_profit'             => 'decimal:2',
        'total_waste_cost'         => 'decimal:2',
        'net_profit_after_waste'   => 'decimal:2',
    ];
}
