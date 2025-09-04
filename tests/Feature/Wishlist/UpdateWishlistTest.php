<?php

use App\Livewire\Wishlist\UpdateWishlist;
use App\Livewire\Wishlist\WishlistDetail;
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
});

it('can load wishlist data in update modal', function () {
    $apiUrl = config('services.api.url');

    Http::fake([
        $apiUrl.'wishlists/wishlist-uuid' => Http::response([
            'data' => (object) [
                'uuid' => 'wishlist-uuid',
                'name' => 'My Movies',
                'is_favorite' => true,
                'items' => [],
            ],
        ], 200),
    ]);

    Livewire::test(UpdateWishlist::class, ['uuid' => 'wishlist-uuid'])
        ->assertSet('uuid', 'wishlist-uuid')
        ->assertSet('name', 'My Movies')
        ->assertSet('is_favorite', true);
});

it('can update wishlist name and favorite status', function () {
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
                    'name' => 'New Name',
                    'is_favorite' => true,
                    'items' => [],
                ],
            ]),
    ]);

    Livewire::test(UpdateWishlist::class, ['uuid' => 'wishlist-uuid'])
        ->set('name', 'New Name')
        ->set('is_favorite', true)
        ->call('update')
        ->assertDispatched('data-updated');
});

it('validates wishlist name is required', function () {
    $apiUrl = config('services.api.url');

    Http::fake([
        $apiUrl.'wishlists/wishlist-uuid' => Http::response([
            'data' => (object) [
                'uuid' => 'wishlist-uuid',
                'name' => 'My Movies',
                'is_favorite' => false,
                'items' => [],
            ],
        ], 200),
    ]);

    Livewire::test(UpdateWishlist::class, ['uuid' => 'wishlist-uuid'])
        ->set('name', '')
        ->call('update')
        ->assertHasErrors(['name' => 'required']);
});

it('validates wishlist name maximum length', function () {
    $apiUrl = config('services.api.url');

    Http::fake([
        $apiUrl.'wishlists/wishlist-uuid' => Http::response([
            'data' => (object) [
                'uuid' => 'wishlist-uuid',
                'name' => 'My Movies',
                'is_favorite' => false,
                'items' => [],
            ],
        ], 200),
    ]);

    $longName = str_repeat('a', 256);

    Livewire::test(UpdateWishlist::class, ['uuid' => 'wishlist-uuid'])
        ->set('name', $longName)
        ->call('update')
        ->assertHasErrors(['name' => 'max']);
});

it('dispatches data-updated event after successful update', function () {
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
                    'name' => 'New Name',
                    'is_favorite' => false,
                    'items' => [],
                ],
            ]),
    ]);

    Livewire::test(UpdateWishlist::class, ['uuid' => 'wishlist-uuid'])
        ->set('name', 'New Name')
        ->call('update')
        ->assertDispatched('data-updated');
});

it('refreshes wishlist detail after update', function () {
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

    $detailComponent = Livewire::test(WishlistDetail::class, ['uuid' => 'wishlist-uuid']);

    expect($detailComponent->viewData('wishlist')->name)->toBe('Old Name');

    $updateComponent = Livewire::test(UpdateWishlist::class, ['uuid' => 'wishlist-uuid'])
        ->set('name', 'Updated Name')
        ->set('is_favorite', true)
        ->call('update');

    $detailComponent->dispatch('data-updated');

    expect($detailComponent->viewData('wishlist')->name)->toBe('Updated Name');
    expect($detailComponent->viewData('wishlist')->is_favorite)->toBe(true);
});

it('shows edit button on wishlist detail page', function () {
    $apiUrl = config('services.api.url');

    Http::fake([
        $apiUrl.'wishlists/wishlist-uuid' => Http::response([
            'data' => (object) [
                'uuid' => 'wishlist-uuid',
                'name' => 'My Movies',
                'is_favorite' => false,
                'items' => [],
            ],
        ], 200),
    ]);

    Livewire::test(WishlistDetail::class, ['uuid' => 'wishlist-uuid'])
        ->assertSee('Edit')
        ->assertSee('wishlist.update-wishlist');
});

it('handles is_favorite as boolean properly', function () {
    $apiUrl = config('services.api.url');

    Http::fake([
        $apiUrl.'wishlists/wishlist-uuid' => Http::response([
            'data' => (object) [
                'uuid' => 'wishlist-uuid',
                'name' => 'My Movies',
                'is_favorite' => 1,
                'items' => [],
            ],
        ], 200),
    ]);

    Livewire::test(UpdateWishlist::class, ['uuid' => 'wishlist-uuid'])
        ->assertSet('is_favorite', true);
});

it('handles missing is_favorite field gracefully', function () {
    $apiUrl = config('services.api.url');

    Http::fake([
        $apiUrl.'wishlists/wishlist-uuid' => Http::response([
            'data' => (object) [
                'uuid' => 'wishlist-uuid',
                'name' => 'My Movies',
                'items' => [],
            ],
        ], 200),
    ]);

    Livewire::test(UpdateWishlist::class, ['uuid' => 'wishlist-uuid'])
        ->assertSet('is_favorite', false);
});
