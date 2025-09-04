<?php

use App\Livewire\Wishlist\DeleteWishlist;
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

it('displays wishlist name in delete confirmation modal', function () {
    $apiUrl = config('services.api.url');

    Http::fake([
        $apiUrl.'wishlists/test-uuid' => Http::response([
            'data' => (object) [
                'uuid' => 'test-uuid',
                'name' => 'My Test List',
                'is_favorite' => false,
            ],
        ], 200),
    ]);

    Livewire::test(DeleteWishlist::class, ['uuid' => 'test-uuid'])
        ->assertSet('uuid', 'test-uuid')
        ->assertSet('name', 'My Test List')
        ->assertSee('My Test List')
        ->assertSee('Delete list')
        ->assertSee('Are you sure you want to delete this list?');
});

it('handles wishlist not found', function () {
    $apiUrl = config('services.api.url');

    Http::fake([
        $apiUrl.'wishlists/invalid-uuid' => Http::response(['data' => null], 404),
    ]);

    Livewire::test(DeleteWishlist::class, ['uuid' => 'invalid-uuid'])->assertStatus(404);
});

it('successfully deletes a wishlist', function () {
    $apiUrl = config('services.api.url');

    Http::fake([
        $apiUrl.'wishlists/test-uuid' => Http::sequence()
            ->push([
                'data' => (object) [
                    'uuid' => 'test-uuid',
                    'name' => 'List to Delete',
                    'is_favorite' => false,
                ],
            ], 200)
            ->push(['success' => true], 200),
    ]);

    Livewire::test(DeleteWishlist::class, ['uuid' => 'test-uuid'])
        ->assertSet('name', 'List to Delete')
        ->call('delete')
        ->assertDispatched('wishlist-deleted');
});

it('handles deletion errors gracefully', function () {
    $apiUrl = config('services.api.url');

    Http::fake([
        $apiUrl.'wishlists/test-uuid' => Http::sequence()
            ->push([
                'data' => (object) [
                    'uuid' => 'test-uuid',
                    'name' => 'List to Delete',
                    'is_favorite' => false,
                ],
            ], 200)
            ->whenEmpty(Http::response(['error' => 'Server error'], 500)),
    ]);

    Livewire::test(DeleteWishlist::class, ['uuid' => 'test-uuid'])
        ->assertSet('name', 'List to Delete')
        ->call('delete')
        ->assertNotDispatched('wishlist-deleted');
});

it('closes modal when cancel is clicked', function () {
    $apiUrl = config('services.api.url');

    Http::fake([
        $apiUrl.'wishlists/test-uuid' => Http::response([
            'data' => (object) [
                'uuid' => 'test-uuid',
                'name' => 'My List',
                'is_favorite' => false,
            ],
        ], 200),
    ]);

    Livewire::test(DeleteWishlist::class, ['uuid' => 'test-uuid'])
        ->assertSee('Cancel')
        ->assertSee('Delete list');
});
