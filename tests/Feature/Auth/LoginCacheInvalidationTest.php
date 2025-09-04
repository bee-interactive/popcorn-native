<?php

use App\Helpers\Popcorn;
use App\Livewire\Auth\Login;
use App\Services\Cache\MobileCacheService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;

beforeEach(function () {
    Cache::flush();
});

it('invalidates all user cache on successful login', function () {
    $apiUrl = config('services.api.url');

    Http::fake([
        $apiUrl.'items' => Http::response(['data' => [
            ['id' => 1, 'name' => 'Cached Item'],
        ]], 200),
        $apiUrl.'wishlists' => Http::response(['data' => [
            ['id' => 1, 'name' => 'Cached Wishlist'],
        ]], 200),
    ]);

    session(['app-access-token' => 'old-token']);

    Popcorn::get('items');
    Popcorn::get('wishlists');

    $itemsCacheKey = 'popcorn.get.'.md5($apiUrl.'items'.serialize(null));
    $wishlistsCacheKey = 'popcorn.get.'.md5($apiUrl.'wishlists'.serialize(null));

    expect(Cache::get($itemsCacheKey))->not->toBeNull();
    expect(Cache::get($wishlistsCacheKey))->not->toBeNull();

    Http::fake([
        $apiUrl.'auth/login' => Http::response([
            'success' => [
                'token' => 'new-token-12345',
            ],
        ], 200),
        $apiUrl.'users/me' => Http::response([
            'data' => (object) [
                'uuid' => 'user-123',
                'name' => 'Test User',
                'username' => 'testuser',
                'description' => null,
                'language' => 'en',
                'email' => 'test@example.com',
                'public_profile' => true,
                'tmdb_token' => 'tmdb-token',
                'profile_picture' => null,
            ],
        ], 200),
    ]);

    Livewire::test(Login::class)
        ->set('email', 'test@example.com')
        ->set('password', 'password123')
        ->call('login');

    expect(Cache::get($itemsCacheKey))->toBeNull();
    expect(Cache::get($wishlistsCacheKey))->toBeNull();
    expect(session('app-access-token'))->toBe('new-token-12345');
    expect(session('app-user')['username'])->toBe('testuser');
});

it('clears mobile cache service patterns on login', function () {
    $apiUrl = config('services.api.url');
    $cacheService = app(MobileCacheService::class);

    Cache::put('popcorn.get.test1', 'value1', 3600);
    Cache::put('popcorn.get.test2', 'value2', 3600);
    Cache::put('other.cache.key', 'value3', 3600);

    expect(Cache::get('popcorn.get.test1'))->toBe('value1');
    expect(Cache::get('popcorn.get.test2'))->toBe('value2');
    expect(Cache::get('other.cache.key'))->toBe('value3');

    Http::fake([
        $apiUrl.'auth/login' => Http::response([
            'success' => [
                'token' => 'new-token',
            ],
        ], 200),
        $apiUrl.'users/me' => Http::response([
            'data' => (object) [
                'uuid' => 'user-123',
                'name' => 'Test User',
                'username' => 'testuser',
                'description' => null,
                'language' => 'en',
                'email' => 'test@example.com',
                'public_profile' => true,
                'tmdb_token' => 'tmdb-token',
                'profile_picture' => null,
            ],
        ], 200),
    ]);

    Livewire::test(Login::class)
        ->set('email', 'test@example.com')
        ->set('password', 'password')
        ->call('login');

    expect(Cache::get('popcorn.get.test1'))->toBeNull();
    expect(Cache::get('popcorn.get.test2'))->toBeNull();
    expect(Cache::get('other.cache.key'))->toBeNull();
});

it('does not clear cache on failed login attempt', function () {
    $apiUrl = config('services.api.url');

    Http::fake([
        $apiUrl.'items' => Http::response(['data' => [
            ['id' => 1, 'name' => 'Cached Item'],
        ]], 200),
    ]);

    session(['app-access-token' => 'existing-token']);

    Popcorn::get('items');

    $cacheKey = 'popcorn.get.'.md5($apiUrl.'items'.serialize(null));
    expect(Cache::get($cacheKey))->not->toBeNull();

    Http::fake([
        $apiUrl.'auth/login' => Http::response([
            'error' => 'Invalid credentials',
        ], 401),
    ]);

    Livewire::test(Login::class)
        ->set('email', 'wrong@example.com')
        ->set('password', 'wrongpassword')
        ->call('login')
        ->assertRedirect('/login');

    expect(Cache::get($cacheKey))->not->toBeNull();
});
