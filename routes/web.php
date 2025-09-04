<?php

use App\Http\Controllers\Dashboard\DashboardController;
use App\Http\Controllers\Feed\FeedController;
use App\Http\Controllers\Item\ItemController;
use App\Http\Controllers\List\ListController;
use App\Http\Controllers\Trending\TrendingController;
use App\Http\Controllers\User\UserController;
use App\Http\Controllers\ViewedItems\ViewedItemsController;
use App\Http\Middleware\LocaleMiddleware;
use App\Http\Middleware\RedirectIfAuthenticatedToDashboard;
use App\Livewire\Settings\Account;
use App\Livewire\Settings\Appearance;
use App\Livewire\Settings\Cache;
use App\Livewire\Settings\Password;
use App\Livewire\Settings\Profile;
use App\Livewire\Settings\Tmdb;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/dashboard');

Route::middleware([RedirectIfAuthenticatedToDashboard::class, LocaleMiddleware::class])->group(function () {
    Route::get('dashboard', DashboardController::class)->name('dashboard');

    Route::get('trending', TrendingController::class)->name('trending.index');

    Route::get('viewed', ViewedItemsController::class)->name('viewed.index');

    Route::get('feed', FeedController::class)->name('feed.index');

    Route::get('lists/{uuid}', ListController::class)->name('wishlists.show');

    Route::get('items/{uuid}', ItemController::class)->name('items.show');

    Route::redirect('settings', 'settings/profile');

    Route::get('settings/profile', Profile::class)->name('settings.profile');
    Route::get('settings/password', Password::class)->name('settings.password');
    Route::get('settings/account', Account::class)->name('settings.account');
    Route::get('settings/the-movie-database-token', Tmdb::class)->name('settings.tmdb');
    Route::get('settings/appearance', Appearance::class)->name('settings.appearance');
    Route::get('settings/cache', Cache::class)->name('settings.cache');
});

Route::prefix('/@{username}')->group(function () {
    Route::get('/', UserController::class)->name('profile.show');
});

// Diagnostic route for debugging
Route::get('/_diagnostic/image-support', [\App\Http\Controllers\DiagnosticController::class, 'imageSupport']);

require __DIR__.'/auth.php';

Route::get('{anything}', function () {
    abort(404);
})->name('404.show');
