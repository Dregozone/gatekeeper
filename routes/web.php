<?php

use App\Livewire\AgentsDashboard;
use Illuminate\Support\Facades\Route;

Route::view('/', 'welcome')->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::view('dashboard', 'dashboard')->name('dashboard');
    Route::livewire('agents', AgentsDashboard::class)->name('agents');
});

require __DIR__.'/settings.php';
