<?php

use App\Http\Controllers\ProfileController;
use Illuminate\Foundation\Application;
use App\Http\Controllers\CashClosureController;
use App\Http\Controllers\OrderController;
use App\Http\Controllers\PaymentController;
use App\Http\Controllers\TableController;
use App\Http\Controllers\ProductCategoryController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\ReportController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', function () {
    return Inertia::render('Welcome', [
        'canLogin' => Route::has('login'),
        'canRegister' => Route::has('register'),
        'laravelVersion' => Application::VERSION,
        'phpVersion' => PHP_VERSION,
    ]);
});

Route::get('/dashboard', function () {
    return Inertia::render('Dashboard');
})->middleware(['auth', 'verified'])->name('dashboard');

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

    // Mesas
    Route::get('/', [TableController::class, 'index'])->name('tables.index');
    Route::get('/tables/{table}', [TableController::class, 'show'])->name('tables.show');

    // Pedidos
    Route::post('/tables/{table}/items', [OrderController::class, 'addItem'])->name('orders.addItem');
    Route::patch('/order-items/{item}', [OrderController::class, 'updateItemQuantity'])->name('orders.updateItem');
    Route::delete('/order-items/{item}', [OrderController::class, 'removeItem'])->name('orders.removeItem');
    Route::patch('/orders/{order}/move', [OrderController::class, 'moveTable'])->name('orders.move');
    Route::patch('/orders/{order}/cancel', [OrderController::class, 'cancel'])->name('orders.cancel');

    // Pagos
    Route::post('/orders/{order}/payment', [PaymentController::class, 'store'])->name('payments.store');
    Route::patch('/payments/{payment}/reverse', [PaymentController::class, 'reverse'])->name('payments.reverse');
    Route::patch('/orders/{order}/customer-name', [OrderController::class, 'updateCustomerName'])->name('orders.updateCustomerName');

    // Cierre de caja
    Route::get('/cash-closures', [CashClosureController::class, 'pending'])->name('cash-closures.pending');
    Route::post('/cash-closures', [CashClosureController::class, 'store'])->name('cash-closures.store');

    // Categorías
    Route::get('/product-categories', [ProductCategoryController::class, 'index'])->name('product-categories.index');
    Route::post('/product-categories', [ProductCategoryController::class, 'store'])->name('product-categories.store');
    Route::patch('/product-categories/reorder', [ProductCategoryController::class, 'reorder'])->name('product-categories.reorder');
    Route::patch('/product-categories/{productCategory}', [ProductCategoryController::class, 'update'])->name('product-categories.update');
    Route::delete('/product-categories/{productCategory}', [ProductCategoryController::class, 'destroy'])->name('product-categories.destroy');

    // Productos
    Route::get('/products', [ProductController::class, 'index'])->name('products.index');
    Route::post('/products', [ProductController::class, 'store'])->name('products.store');
    Route::patch('/products/{product}', [ProductController::class, 'update'])->name('products.update');
    Route::patch('/products/{product}/deactivate', [ProductController::class, 'destroy'])->name('products.destroy');
    Route::patch('/products/{product}/restore', [ProductController::class, 'restore'])->name('products.restore');
    Route::post('/products/{product}/restock', [ProductController::class, 'restock'])->name('products.restock');
    Route::post('/products/{product}/waste', [ProductController::class, 'registerWaste'])->name('products.registerWaste');
    Route::delete('/products/{product}/image', [ProductController::class, 'removeImage'])->name('products.removeImage');

    // Reportes
    Route::get('/reports', [ReportController::class, 'index'])->name('reports.index');

    // Menú agrupado para toma de pedido
    Route::get('/menu', [ProductController::class, 'menu'])->name('products.menu');
});

require __DIR__ . '/auth.php';
