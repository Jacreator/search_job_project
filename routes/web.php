<?php

use App\Http\Controllers\DashboardController;
use App\Http\Controllers\Sponsors\SponsorController;
use App\Http\Controllers\Sponsors\SponsorRatingController;
use App\Http\Controllers\Teams\TeamInvitationController;
use App\Http\Middleware\EnsureTeamMembership;
use Illuminate\Support\Facades\Route;

Route::inertia('/', 'welcome')->name('home');

Route::prefix('{current_team}')
    ->middleware(['auth', 'verified', EnsureTeamMembership::class])
    ->group(function () {
        Route::get('dashboard', DashboardController::class)->name('dashboard');

        Route::scopeBindings()->group(function () {
            Route::get('sponsors', [SponsorController::class, 'index'])->name('sponsors.index');
            Route::patch('sponsors/{sponsor}', [SponsorController::class, 'update'])->name('sponsors.update');
            Route::patch('sponsors/{sponsor}/rating', [SponsorRatingController::class, 'update'])->name('sponsors.rating.update');
        });
    });

Route::middleware(['auth'])->group(function () {
    Route::post('invitations/{invitation}/accept', [TeamInvitationController::class, 'accept'])->name('invitations.accept');
    Route::delete('invitations/{invitation}', [TeamInvitationController::class, 'decline'])->name('invitations.decline');
});

require __DIR__.'/settings.php';
