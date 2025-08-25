<?php

use App\Helpers\Popcorn;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    Cache::flush();

    session(['app-access-token' => 'test-token']);
    session(['app-user' => [
        'username' => 'testuser',
        'tmdb_token' => 'test-tmdb-token',
    ]]);
});

it('invalidates items cache when a new item is added', function () {
    $apiUrl = config('services.api.url');

    Http::fake([
        $apiUrl.'items' => Http::response(['data' => [
            ['id' => 1, 'name' => 'Original Item'],
        ]], 200),
    ]);

    $items = Popcorn::get('items');
    expect($items->get('data'))->toHaveCount(1);

    $cacheKey = 'popcorn.get.'.md5($apiUrl.'items'.serialize(null));
    $cached = Cache::get($cacheKey);
    expect($cached)->not->toBeNull();

    Http::fake([
        $apiUrl.'items' => Http::response(['message' => 'Item created'], 201),
    ]);

    Popcorn::post('items', ['data' => ['name' => 'New Item']]);

    $cached = Cache::get($cacheKey);
    expect($cached)->toBeNull();
});

it('invalidates items cache when an item is deleted', function () {
    $apiUrl = config('services.api.url');

    Http::fake([
        $apiUrl.'items' => Http::response(['data' => [
            ['id' => 1, 'name' => 'Item 1', 'uuid' => 'uuid-1'],
            ['id' => 2, 'name' => 'Item 2', 'uuid' => 'uuid-2'],
        ]], 200),
    ]);

    $items = Popcorn::get('items');
    expect($items->get('data'))->toHaveCount(2);

    $cacheKey = 'popcorn.get.'.md5($apiUrl.'items'.serialize(null));
    $cached = Cache::get($cacheKey);
    expect($cached)->not->toBeNull();

    Http::fake([
        $apiUrl.'items/uuid-1' => Http::response(['message' => 'Item deleted'], 200),
    ]);

    Popcorn::delete('items/uuid-1');

    $cached = Cache::get($cacheKey);
    expect($cached)->toBeNull();
});

it('invalidates items cache when an item is updated', function () {
    $apiUrl = config('services.api.url');

    Http::fake([
        $apiUrl.'items' => Http::response(['data' => [
            ['id' => 1, 'name' => 'Original Name', 'uuid' => 'uuid-1'],
        ]], 200),
    ]);

    $items = Popcorn::get('items');
    $data = $items->get('data');
    expect($data[0]->name)->toBe('Original Name');

    $cacheKey = 'popcorn.get.'.md5($apiUrl.'items'.serialize(null));
    $cached = Cache::get($cacheKey);
    expect($cached)->not->toBeNull();

    Http::fake([
        $apiUrl.'items/uuid-1' => Http::response(['message' => 'Item updated'], 200),
    ]);

    Popcorn::patch('items/uuid-1', ['name' => 'Updated Name']);

    $cached = Cache::get($cacheKey);
    expect($cached)->toBeNull();
});

it('invalidates wishlists cache when a wishlist is modified', function () {
    $apiUrl = config('services.api.url');

    Http::fake([
        $apiUrl.'wishlists' => Http::response(['data' => [
            ['id' => 1, 'name' => 'My List'],
        ]], 200),
    ]);

    $wishlists = Popcorn::get('wishlists');
    expect($wishlists->get('data'))->toHaveCount(1);

    $cacheKey = 'popcorn.get.'.md5($apiUrl.'wishlists'.serialize(null));
    $cached = Cache::get($cacheKey);
    expect($cached)->not->toBeNull();

    Http::fake([
        $apiUrl.'wishlists/1' => Http::response(['message' => 'Wishlist updated'], 200),
    ]);

    Popcorn::patch('wishlists/1', ['name' => 'Updated List']);

    $cached = Cache::get($cacheKey);
    expect($cached)->toBeNull();
});

it('invalidates wishlist caches when an item is deleted', function () {
    $apiUrl = config('services.api.url');

    Http::fake([
        $apiUrl.'wishlists' => Http::response(['data' => [
            ['id' => 1, 'uuid' => 'list-1', 'name' => 'My List'],
            ['id' => 2, 'uuid' => 'list-2', 'name' => 'Another List'],
        ]], 200),
        $apiUrl.'wishlists/list-1' => Http::response(['data' => [
            'id' => 1,
            'uuid' => 'list-1',
            'name' => 'My List',
            'items' => [
                ['id' => 1, 'name' => 'Item 1', 'uuid' => 'uuid-1'],
                ['id' => 2, 'name' => 'Item 2', 'uuid' => 'uuid-2'],
            ],
        ]], 200),
    ]);

    Popcorn::get('wishlists');

    $wishlist = Popcorn::get('wishlists/list-1');
    expect($wishlist->get('data')->items)->toHaveCount(2);

    $wishlistCacheKey = 'popcorn.get.'.md5($apiUrl.'wishlists/list-1'.serialize(null));
    $cached = Cache::get($wishlistCacheKey);
    expect($cached)->not->toBeNull();

    Http::fake([
        $apiUrl.'items/uuid-1' => Http::response(['message' => 'Item deleted'], 200),
    ]);

    Popcorn::delete('items/uuid-1');

    $cached = Cache::get($wishlistCacheKey);
    expect($cached)->toBeNull();
});

it('does not use cache when useCache parameter is false', function () {
    $apiUrl = config('services.api.url');
    $callCount = 0;

    Http::fake(function () use (&$callCount) {
        $callCount++;

        return Http::response(['data' => ['count' => $callCount]], 200);
    });

    $result1 = Popcorn::get('items', null, null, true);
    expect($result1->get('data')->count)->toBe(1);

    $result2 = Popcorn::get('items', null, null, true);
    expect($result2->get('data')->count)->toBe(1);

    $result3 = Popcorn::get('items', null, null, false);
    expect($result3->get('data')->count)->toBe(2);

    $result4 = Popcorn::get('items', null, null, false);
    expect($result4->get('data')->count)->toBe(3);
});
