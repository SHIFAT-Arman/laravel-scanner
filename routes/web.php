<?php

use App\Http\Controllers\AttendeeLookupController;
use Illuminate\Support\Facades\Route;

Route::view('/', 'welcome')->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::view('dashboard', 'dashboard')->name('dashboard');

    Route::livewire('scanner', 'pages::scanner')->name('scanner');

    Route::get('attendees/{uuid}', AttendeeLookupController::class)
        ->name('attendees.show');

    Route::livewire('admin/attendees', 'pages::attendees')
        ->middleware('admin')
        ->name('attendees.index');

    Route::livewire('admin/staff', 'pages::staff')
        ->middleware('admin')
        ->name('staff.index');
});

require __DIR__.'/settings.php';
