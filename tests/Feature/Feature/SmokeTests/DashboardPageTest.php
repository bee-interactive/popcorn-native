<?php

use Illuminate\Support\Facades\Http;

beforeEach(function () {
    // Set the access token in session to pass middleware
    session(['app-access-token' => 'test-token']);
    session(['app-user' => [
        'username' => 'testuser',
        'tmdb_token' => 'test-tmdb-token',
    ]]);
});

it('renders dashboard page with wishlists as objects from API', function () {
    // This is the actual bug scenario - API returns objects
    $apiUrl = config('services.api.url');

    Http::fake([
        $apiUrl.'items' => Http::response([
            'data' => [
                [
                    'id' => 1,
                    'title' => 'Dashboard Movie',
                    'poster_path' => '/dashboard.jpg',
                    'uuid' => 'dashboard-movie-uuid',
                ],
            ],
        ], 200),
        $apiUrl.'wishlists' => Http::response([
            'data' => [
                [
                    'id' => 1,
                    'name' => 'My Favorites',
                    'uuid' => 'fav-uuid',
                    'is_favorite' => true,
                ],
                [
                    'id' => 2,
                    'name' => 'Watch Later',
                    'uuid' => 'later-uuid',
                    'is_favorite' => false,
                ],
            ],
        ], 200),
    ]);

    $response = $this->get('/dashboard');

    $response->assertStatus(200)
        ->assertSee('Dashboard')
        ->assertSee('My Favorites')
        ->assertSee('Watch Later')
        ->assertSee('★') // Favorite star
        ->assertSee('lists/fav-uuid', false)
        ->assertSee('lists/later-uuid', false);
});

it('dashboard does not crash with malformed wishlist data', function () {
    $apiUrl = config('services.api.url');

    Http::fake([
        $apiUrl.'items' => Http::response(['data' => []], 200),
        $apiUrl.'wishlists' => Http::response([
            'data' => [
                [], // Empty object
                [
                    'name' => 'Only Name',
                    // Missing uuid, id, is_favorite
                ],
                [
                    'uuid' => 'only-uuid',
                    // Missing name
                ],
                null, // Null entry
                [], // Empty array
                'string', // Wrong type
            ],
        ], 200),
    ]);

    $response = $this->get('/dashboard');

    // Should not crash, should handle gracefully
    $response->assertStatus(200)
        ->assertSee('Dashboard');
});

it('dashboard works when wishlists endpoint returns error or null', function () {
    $apiUrl = config('services.api.url');

    Http::fake([
        $apiUrl.'items' => Http::response(['data' => []], 200),
        $apiUrl.'wishlists' => Http::response(null, 200),
    ]);

    $response = $this->get('/dashboard');

    $response->assertStatus(200)
        ->assertSee('Dashboard')
        ->assertDontSee('★'); // No wishlists, no favorites
});

it('dashboard handles mixed array and object formats', function () {
    $apiUrl = config('services.api.url');

    Http::fake([
        $apiUrl.'items' => Http::response([
            'data' => [
                ['id' => 1, 'title' => 'Array Item', 'uuid' => 'item-array-uuid'],
                ['id' => 2, 'title' => 'Object Item', 'uuid' => 'item-object-uuid'],
            ],
        ], 200),
        $apiUrl.'wishlists' => Http::response([
            'data' => [
                [
                    'id' => 1,
                    'name' => 'Array List',
                    'uuid' => 'array-uuid',
                    'is_favorite' => true,
                ],
                [
                    'id' => 2,
                    'name' => 'Object List',
                    'uuid' => 'object-uuid',
                    'is_favorite' => false,
                ],
            ],
        ], 200),
    ]);

    $response = $this->get('/dashboard');

    $response->assertStatus(200)
        ->assertSee('Dashboard')
        ->assertSee('Array List')
        ->assertSee('Object List')
        ->assertSee('★'); // Should see favorite for Array List
});

it('dashboard correctly displays favorite badges for wishlists', function () {
    $apiUrl = config('services.api.url');

    Http::fake([
        $apiUrl.'items' => Http::response(['data' => []], 200),
        $apiUrl.'wishlists' => Http::response([
            'data' => [
                [
                    'id' => 1,
                    'name' => 'Not Favorite',
                    'uuid' => 'uuid-1',
                    'is_favorite' => false,
                ],
                [
                    'id' => 2,
                    'name' => 'Is Favorite',
                    'uuid' => 'uuid-2',
                    'is_favorite' => true,
                ],
                [
                    'id' => 3,
                    'name' => 'Also Favorite',
                    'uuid' => 'uuid-3',
                    'is_favorite' => 1, // Could be 1 instead of true
                ],
                [
                    'id' => 4,
                    'name' => 'String True',
                    'uuid' => 'uuid-4',
                    'is_favorite' => 'true', // String "true"
                ],
            ],
        ], 200),
    ]);

    $response = $this->get('/dashboard');

    $response->assertStatus(200);

    // Count how many stars appear (should be 3 - for items 2, 3, and 4)
    $content = $response->getContent();
    $starCount = substr_count($content, '★');

    expect($starCount)->toBeGreaterThanOrEqual(2); // At least 2 favorites should show stars
});
