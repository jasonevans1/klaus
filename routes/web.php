<?php

use App\Models\HouseholdInvitation;
use Illuminate\Support\Facades\Route;

Route::view('/', 'welcome')->name('home');

Route::get('invitations/{token}', function (string $token) {
    return HouseholdInvitation::findPendingByToken($token)
        ? redirect()->route('register', ['invitation' => $token])
        : redirect()->route('register')->with('error', HouseholdInvitation::INVALID_MESSAGE);
})->middleware('guest')->name('invitations.show');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::view('dashboard', 'dashboard')->name('dashboard');
    Route::livewire('people', 'pages::people.index')->name('people.index');
    Route::livewire('occasions', 'pages::occasions.index')->name('occasions.index');
    Route::livewire('occasions/{occasion}', 'pages::occasions.show')->whereNumber('occasion')->name('occasions.show');
    Route::livewire('occasions/{occasion}/shopping', 'pages::occasions.shopping')->whereNumber('occasion')->name('occasions.shopping');
    Route::livewire('occasions/{occasion}/wrapping', 'pages::occasions.wrapping')->whereNumber('occasion')->name('occasions.wrapping');
});

require __DIR__.'/settings.php';
