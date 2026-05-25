<?php

use App\Http\Controllers\InvoiceController;
use Illuminate\Support\Facades\Route;

Route::view('/', 'welcome')->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::view('dashboard', 'dashboard')->name('dashboard');
});

// Cashier Point of Sale (Livewire SFC)
Route::middleware(['auth', 'role:cashier|super_admin'])->group(function () {
    Route::livewire('/pos', 'pages::pos')->name('pos');
    Route::get('/orders/{order}/invoice', [InvoiceController::class, 'download'])->name('orders.invoice');
    Route::get('/orders/{order}/receipt', [InvoiceController::class, 'receipt'])->name('orders.receipt');
});

// Kitchen Display System (Livewire SFC)
Route::middleware(['auth', 'role:kitchen|super_admin'])->group(function () {
    Route::livewire('/kitchen', 'pages::kitchen')->name('kitchen');
});

require __DIR__.'/settings.php';
