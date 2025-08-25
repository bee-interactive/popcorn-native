<?php

use App\Livewire\Wishlist\UserWishlists;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;

beforeEach(function () {
    // Set the access token in session to pass middleware
    session(['app-access-token' => 'test-token']);
    session(['app-user' => [
        'username' => 'testuser',
        'tmdb_token' => 'test-tmdb-token',
    ]]);
});

it('handles wishlists data as objects (stdClass) from API', function () {
    $apiUrl = config('services.api.url');

    Http::fake([
        $apiUrl.'wishlists' => Http::response([
            'data' => [
                [
                    'id' => 1,
                    'name' => 'My Movies',
                    'uuid' => 'uuid-123',
                    'is_favorite' => true,
                ],
                [
                    'id' => 2,
                    'name' => 'To Watch',
                    'uuid' => 'uuid-456',
                    'is_favorite' => false,
                ],
            ],
        ], 200),
    ]);

    Livewire::test(UserWishlists::class)
        ->assertStatus(200)
        ->assertSee('My Movies')
        ->assertSee('To Watch')
        ->assertSee('★') // Favorite badge
        ->assertSee('lists/uuid-123', false)
        ->assertSee('lists/uuid-456', false);
});

it('handles wishlists data as arrays (for backward compatibility)', function () {
    $apiUrl = config('services.api.url');

    Http::fake([
        $apiUrl.'wishlists' => Http::response([
            'data' => [
                [
                    'id' => 1,
                    'name' => 'Array List 1',
                    'uuid' => 'array-uuid-1',
                    'is_favorite' => false,
                ],
                [
                    'id' => 2,
                    'name' => 'Array List 2',
                    'uuid' => 'array-uuid-2',
                    'is_favorite' => true,
                ],
            ],
        ], 200),
    ]);

    Livewire::test(UserWishlists::class)
        ->assertStatus(200)
        ->assertSee('Array List 1')
        ->assertSee('Array List 2')
        ->assertSee('★') // Favorite badge for second list
        ->assertSee('lists/array-uuid-1', false)
        ->assertSee('lists/array-uuid-2', false);
});

it('handles empty wishlists gracefully', function () {
    $apiUrl = config('services.api.url');

    Http::fake([
        $apiUrl.'wishlists' => Http::response(['data' => []], 200),
    ]);

    Livewire::test(UserWishlists::class)
        ->assertStatus(200)
        ->assertDontSee('★');
});

it('handles missing data key gracefully', function () {
    $apiUrl = config('services.api.url');

    Http::fake([
        $apiUrl.'wishlists' => Http::response([], 200),
    ]);

    Livewire::test(UserWishlists::class)
        ->assertStatus(200)
        ->assertDontSee('★');
});

it('handles wishlists without is_favorite field', function () {
    $apiUrl = config('services.api.url');

    Http::fake([
        $apiUrl.'wishlists' => Http::response([
            'data' => [
                [
                    'id' => 1,
                    'name' => 'No Favorite Field',
                    'uuid' => 'no-fav-uuid',
                    // is_favorite is missing
                ],
            ],
        ], 200),
    ]);

    Livewire::test(UserWishlists::class)
        ->assertStatus(200)
        ->assertSee('No Favorite Field')
        ->assertDontSee('★'); // Should not show star when is_favorite is missing
});
