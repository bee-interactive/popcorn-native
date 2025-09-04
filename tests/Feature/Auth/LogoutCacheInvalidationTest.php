<?php

use App\Helpers\Popcorn;
use App\Livewire\Actions\Logout;
use App\Services\Cache\MobileCacheService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Session;

beforeEach(function () {
    Cache::flush();

    session(['app-access-token' => 'test-token']);
    session(['app-user' => [
        'uuid' => 'user-123',
        'name' => 'Test User',
        'username' => 'testuser',
        'description' => null,
        'language' => 'en',
        'email' => 'test@example.com',
        'public_profile' => true,
        'tmdb_token' => 'tmdb-token',
        'profile_picture' => null,
    ]]);
});

it('invalidates all user cache on logout', function () {
    $apiUrl = config('services.api.url');

    Http::fake([
        $apiUrl.'items' => Http::response(['data' => [
            ['id' => 1, 'name' => 'Cached Item'],
        ]], 200),
        $apiUrl.'wishlists' => Http::response(['data' => [
            ['id' => 1, 'name' => 'Cached Wishlist'],
        ]], 200),
    ]);

    Popcorn::get('items');
    Popcorn::get('wishlists');

    $itemsCacheKey = 'popcorn.get.'.md5($apiUrl.'items'.serialize(null));
    $wishlistsCacheKey = 'popcorn.get.'.md5($apiUrl.'wishlists'.serialize(null));

    expect(Cache::get($itemsCacheKey))->not->toBeNull();
    expect(Cache::get($wishlistsCacheKey))->not->toBeNull();
    expect(session('app-access-token'))->toBe('test-token');
    expect(session('app-user')['username'])->toBe('testuser');

    $logout = new Logout;
    $response = $logout();

    expect(Cache::get($itemsCacheKey))->toBeNull();
    expect(Cache::get($wishlistsCacheKey))->toBeNull();
    expect(session('app-access-token'))->toBeNull();
    expect(session('app-user'))->toBeNull();
    expect($response->getTargetUrl())->toBe(route('login'));
});

it('clears all cache patterns on logout', function () {
    $apiUrl = config('services.api.url');
    $cacheService = app(MobileCacheService::class);

    Cache::put('popcorn.get.test1', 'value1', 3600);
    Cache::put('popcorn.get.test2', 'value2', 3600);
    Cache::put('mobile.cache.test', 'value3', 3600);
    Cache::put('other.cache.key', 'value4', 3600);

    expect(Cache::get('popcorn.get.test1'))->toBe('value1');
    expect(Cache::get('popcorn.get.test2'))->toBe('value2');
    expect(Cache::get('mobile.cache.test'))->toBe('value3');
    expect(Cache::get('other.cache.key'))->toBe('value4');

    $logout = new Logout;
    $logout();

    expect(Cache::get('popcorn.get.test1'))->toBeNull();
    expect(Cache::get('popcorn.get.test2'))->toBeNull();
    expect(Cache::get('mobile.cache.test'))->toBeNull();
    expect(Cache::get('other.cache.key'))->toBeNull();
});

it('clears session and regenerates token on logout', function () {
    session(['app-access-token' => 'test-token']);
    session(['app-user' => ['username' => 'testuser']]);
    session(['other-data' => 'should-be-cleared']);

    $originalToken = Session::token();

    expect(session('app-access-token'))->toBe('test-token');
    expect(session('app-user')['username'])->toBe('testuser');
    expect(session('other-data'))->toBe('should-be-cleared');

    $logout = new Logout;
    $logout();

    expect(session('app-access-token'))->toBeNull();
    expect(session('app-user'))->toBeNull();
    expect(session('other-data'))->toBeNull();
    expect(Session::token())->not->toBe($originalToken);
});

it('ensures cache is invalidated before session is cleared', function () {
    $apiUrl = config('services.api.url');

    Http::fake([
        $apiUrl.'items' => Http::response(['data' => [
            ['id' => 1, 'name' => 'Item 1'],
        ]], 200),
    ]);

    Popcorn::get('items');

    $cacheKey = 'popcorn.get.'.md5($apiUrl.'items'.serialize(null));
    expect(Cache::get($cacheKey))->not->toBeNull();

    $logout = new Logout;
    $logout();

    expect(Cache::get($cacheKey))->toBeNull();
    expect(session('app-access-token'))->toBeNull();
});
