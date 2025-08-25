<?php

use App\Livewire\Wishlist\WishlistDetail;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;

beforeEach(function () {
    session(['app-access-token' => 'test-token']);
    session(['app-user' => [
        'username' => 'testuser',
        'tmdb_token' => 'test-tmdb-token',
    ]]);
});

it('refreshes wishlist items when data-updated event is dispatched', function () {
    $apiUrl = config('services.api.url');

    Http::fake([
        $apiUrl.'wishlists/test-uuid' => Http::sequence()
            ->push(['data' => [
                'id' => 1,
                'uuid' => 'test-uuid',
                'name' => 'My List',
                'items' => [
                    ['id' => 1, 'name' => 'Item 1', 'uuid' => 'item-1'],
                    ['id' => 2, 'name' => 'Item 2', 'uuid' => 'item-2'],
                ],
            ]], 200)
            ->push(['data' => [
                'id' => 1,
                'uuid' => 'test-uuid',
                'name' => 'My List',
                'items' => [
                    ['id' => 2, 'name' => 'Item 2', 'uuid' => 'item-2'],
                ],
            ]], 200),
    ]);

    $component = Livewire::test(WishlistDetail::class, ['uuid' => 'test-uuid']);

    expect($component->get('wishlist')->items)->toHaveCount(2);

    $component->dispatch('data-updated');

    expect($component->get('wishlist')->items)->toHaveCount(1);
    expect($component->get('wishlist')->items[0]->name)->toBe('Item 2');
});

it('shows empty message when wishlist has no items', function () {
    $apiUrl = config('services.api.url');

    Http::fake([
        $apiUrl.'wishlists/empty-uuid' => Http::response(['data' => [
            'id' => 1,
            'uuid' => 'empty-uuid',
            'name' => 'Empty List',
            'items' => [],
        ]], 200),
    ]);

    Livewire::test(WishlistDetail::class, ['uuid' => 'empty-uuid'])
        ->assertSee('No items yet');
});

it('handles null wishlist data gracefully', function () {
    $apiUrl = config('services.api.url');

    Http::fake([
        $apiUrl.'wishlists/invalid-uuid' => Http::response(['data' => null], 200),
    ]);

    Livewire::test(WishlistDetail::class, ['uuid' => 'invalid-uuid'])
        ->assertStatus(404);
});
