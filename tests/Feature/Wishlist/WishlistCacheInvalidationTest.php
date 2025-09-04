<?php

use App\Helpers\Popcorn;
use App\Livewire\Wishlist\CreateWishlist;
use App\Livewire\Wishlist\DeleteWishlist;
use App\Livewire\Wishlist\UpdateWishlist;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;

beforeEach(function () {
    session(['app-access-token' => 'test-token']);
    session(['app-user' => [
        'uuid' => 'user-123',
        'name' => 'Test User',
        'username' => 'testuser',
        'email' => 'test@example.com',
        'language' => 'en',
        'profile_picture' => null,
        'description' => null,
        'public_profile' => true,
        'tmdb_token' => null,
    ]]);

    Cache::flush();
});

it('invalidates wishlist cache when creating a new wishlist', function () {
    $apiUrl = config('services.api.url');

    Http::fake([
        $apiUrl.'wishlists' => Http::sequence()
            ->push(['data' => [
                ['uuid' => 'wishlist-1', 'name' => 'Existing List'],
            ]], 200)
            ->push([
                'data' => (object) [
                    'uuid' => 'new-wishlist',
                    'name' => 'New List',
                    'is_favorite' => false,
                ],
            ], 201)
            ->push(['data' => [
                ['uuid' => 'wishlist-1', 'name' => 'Existing List'],
                ['uuid' => 'new-wishlist', 'name' => 'New List'],
            ]], 200),
    ]);

    Popcorn::get('wishlists');

    $cacheKey = 'popcorn.get.'.md5($apiUrl.'wishlists'.serialize(null));
    expect(Cache::get($cacheKey))->not->toBeNull();

    Livewire::test(CreateWishlist::class)
        ->set('name', 'New List')
        ->set('is_favorite', false)
        ->call('save');

    expect(Cache::get($cacheKey))->toBeNull();
});

it('invalidates specific wishlist cache when updating a wishlist', function () {
    $apiUrl = config('services.api.url');

    Http::fake([
        $apiUrl.'wishlists/wishlist-uuid' => Http::sequence()
            ->push([
                'data' => (object) [
                    'uuid' => 'wishlist-uuid',
                    'name' => 'Old Name',
                    'is_favorite' => false,
                    'items' => [],
                ],
            ])
            ->push([
                'data' => (object) [
                    'uuid' => 'wishlist-uuid',
                    'name' => 'Old Name',
                    'is_favorite' => false,
                    'items' => [],
                ],
            ])
            ->push([
                'data' => (object) [
                    'uuid' => 'wishlist-uuid',
                    'name' => 'Updated Name',
                    'is_favorite' => true,
                    'items' => [],
                ],
            ])
            ->push([
                'data' => (object) [
                    'uuid' => 'wishlist-uuid',
                    'name' => 'Updated Name',
                    'is_favorite' => true,
                    'items' => [],
                ],
            ]),
    ]);

    Popcorn::get('wishlists/wishlist-uuid');

    $detailCacheKey = 'popcorn.get.'.md5($apiUrl.'wishlists/wishlist-uuid'.serialize(null));
    expect(Cache::get($detailCacheKey))->not->toBeNull();

    Livewire::test(UpdateWishlist::class, ['uuid' => 'wishlist-uuid'])
        ->set('name', 'Updated Name')
        ->set('is_favorite', true)
        ->call('update');

    expect(Cache::get($detailCacheKey))->toBeNull();
});

it('invalidates all wishlist caches when creating a wishlist', function () {
    $apiUrl = config('services.api.url');

    Http::fake([
        $apiUrl.'wishlists' => Http::response(['data' => [
            ['uuid' => 'wishlist-1', 'name' => 'List 1'],
        ]], 200),
        $apiUrl.'wishlists/wishlist-1' => Http::response([
            'data' => (object) [
                'uuid' => 'wishlist-1',
                'name' => 'List 1',
                'is_favorite' => false,
                'items' => [],
            ],
        ], 200),
    ]);

    Popcorn::get('wishlists');
    Popcorn::get('wishlists/wishlist-1');

    $listCacheKey = 'popcorn.get.'.md5($apiUrl.'wishlists'.serialize(null));
    $detailCacheKey = 'popcorn.get.'.md5($apiUrl.'wishlists/wishlist-1'.serialize(null));

    expect(Cache::get($listCacheKey))->not->toBeNull();
    expect(Cache::get($detailCacheKey))->not->toBeNull();

    Http::fake([
        $apiUrl.'wishlists' => Http::response([
            'data' => (object) [
                'uuid' => 'new-wishlist',
                'name' => 'New List',
                'is_favorite' => false,
            ],
        ], 201),
    ]);

    Livewire::test(CreateWishlist::class)
        ->set('name', 'New List')
        ->call('save');

    expect(Cache::get($listCacheKey))->toBeNull();
});

it('invalidates wishlist items cache when updating a wishlist', function () {
    $apiUrl = config('services.api.url');

    Http::fake([
        $apiUrl.'wishlists/wishlist-uuid/items' => Http::response([
            'data' => [
                ['uuid' => 'item-1', 'title' => 'Item 1'],
            ],
        ], 200),
        $apiUrl.'wishlists/wishlist-uuid' => Http::sequence()
            ->push([
                'data' => (object) [
                    'uuid' => 'wishlist-uuid',
                    'name' => 'My List',
                    'is_favorite' => false,
                    'items' => [],
                ],
            ])
            ->push([
                'data' => (object) [
                    'uuid' => 'wishlist-uuid',
                    'name' => 'Updated List',
                    'is_favorite' => false,
                    'items' => [],
                ],
            ]),
    ]);

    Popcorn::get('wishlists/wishlist-uuid/items');

    $itemsCacheKey = 'popcorn.get.'.md5($apiUrl.'wishlists/wishlist-uuid/items'.serialize(null));
    expect(Cache::get($itemsCacheKey))->not->toBeNull();

    Livewire::test(UpdateWishlist::class, ['uuid' => 'wishlist-uuid'])
        ->set('name', 'Updated List')
        ->call('update');

    expect(Cache::get($itemsCacheKey))->toBeNull();
});

it('invalidates all wishlist caches when deleting a wishlist', function () {
    $apiUrl = config('services.api.url');

    Http::fake([
        $apiUrl.'wishlists' => Http::response(['data' => [
            ['uuid' => 'wishlist-uuid', 'name' => 'List to Delete'],
            ['uuid' => 'other-wishlist', 'name' => 'Other List'],
        ]], 200),
        $apiUrl.'wishlists/wishlist-uuid' => Http::sequence()
            ->push([
                'data' => (object) [
                    'uuid' => 'wishlist-uuid',
                    'name' => 'List to Delete',
                    'is_favorite' => false,
                ],
            ])
            ->push([
                'data' => (object) [
                    'uuid' => 'wishlist-uuid',
                    'name' => 'List to Delete',
                    'is_favorite' => false,
                ],
            ]),
    ]);

    Popcorn::get('wishlists');
    Popcorn::get('wishlists/wishlist-uuid');

    $listCacheKey = 'popcorn.get.'.md5($apiUrl.'wishlists'.serialize(null));
    $detailCacheKey = 'popcorn.get.'.md5($apiUrl.'wishlists/wishlist-uuid'.serialize(null));

    expect(Cache::get($listCacheKey))->not->toBeNull();
    expect(Cache::get($detailCacheKey))->not->toBeNull();

    Http::fake([
        $apiUrl.'wishlists/wishlist-uuid' => Http::response(['success' => true], 200),
    ]);

    Livewire::test(DeleteWishlist::class, ['uuid' => 'wishlist-uuid'])
        ->call('delete')
        ->assertDispatched('wishlist-deleted');

    expect(Cache::get($listCacheKey))->toBeNull();
    expect(Cache::get($detailCacheKey))->toBeNull();
});

it('invalidates items cache when deleting a wishlist', function () {
    $apiUrl = config('services.api.url');

    Http::fake([
        $apiUrl.'items' => Http::response(['data' => [
            ['uuid' => 'item-1', 'title' => 'Item 1', 'wishlists' => ['wishlist-uuid']],
            ['uuid' => 'item-2', 'title' => 'Item 2', 'wishlists' => []],
        ]], 200),
        $apiUrl.'items/item-1' => Http::response([
            'data' => (object) [
                'uuid' => 'item-1',
                'title' => 'Item 1',
                'wishlists' => ['wishlist-uuid'],
            ],
        ], 200),
        $apiUrl.'wishlists/wishlist-uuid' => Http::sequence()
            ->push([
                'data' => (object) [
                    'uuid' => 'wishlist-uuid',
                    'name' => 'List with Items',
                    'is_favorite' => false,
                ],
            ])
            ->push([
                'data' => (object) [
                    'uuid' => 'wishlist-uuid',
                    'name' => 'List with Items',
                    'is_favorite' => false,
                ],
            ]),
    ]);

    Popcorn::get('items');
    Popcorn::get('items/item-1');

    $itemsListCacheKey = 'popcorn.get.'.md5($apiUrl.'items'.serialize(null));
    $itemDetailCacheKey = 'popcorn.get.'.md5($apiUrl.'items/item-1'.serialize(null));

    expect(Cache::get($itemsListCacheKey))->not->toBeNull();
    expect(Cache::get($itemDetailCacheKey))->not->toBeNull();

    Http::fake([
        $apiUrl.'wishlists/wishlist-uuid' => Http::response(['success' => true], 200),
    ]);

    Livewire::test(DeleteWishlist::class, ['uuid' => 'wishlist-uuid'])
        ->call('delete');

    expect(Cache::get($itemsListCacheKey))->toBeNull();
    expect(Cache::get($itemDetailCacheKey))->toBeNull();
});
