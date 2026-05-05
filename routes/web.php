<?php

use App\Http\Controllers\BuyerController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\InquiryController;
use App\Http\Controllers\ItemController;
use App\Http\Controllers\ItemPhotoController;
use App\Http\Controllers\ItemPhotoZipController;
use App\Http\Controllers\ItemTransitionController;
use Illuminate\Support\Facades\Route;
use Laravel\Fortify\Features;

Route::inertia('/', 'welcome', [
    'canRegister' => Features::enabled(Features::registration()),
])->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('dashboard', DashboardController::class)->name('dashboard');

    Route::resource('items', ItemController::class);
    Route::post('items/{item}/transition', ItemTransitionController::class)->name('items.transition');
    Route::post('items/{item}/photos', [ItemPhotoController::class, 'store'])->name('items.photos.store');
    Route::patch('items/{item}/photos/reorder', [ItemPhotoController::class, 'reorder'])->name('items.photos.reorder');
    Route::delete('items/{item}/photos/{photo}', [ItemPhotoController::class, 'destroy'])->name('items.photos.destroy');
    Route::get('items/{item}/photos.zip', ItemPhotoZipController::class)->name('items.photos.zip');
    Route::post('items/{item}/inquiries', [InquiryController::class, 'store'])->name('inquiries.store');
    Route::patch('inquiries/{inquiry}', [InquiryController::class, 'update'])->name('inquiries.update');

    Route::get('buyers', [BuyerController::class, 'index'])->name('buyers.index');
    Route::post('buyers', [BuyerController::class, 'store'])->name('buyers.store');
    Route::get('buyers/{buyer}', [BuyerController::class, 'show'])->name('buyers.show');
    Route::patch('buyers/{buyer}', [BuyerController::class, 'update'])->name('buyers.update');
});

require __DIR__.'/settings.php';
