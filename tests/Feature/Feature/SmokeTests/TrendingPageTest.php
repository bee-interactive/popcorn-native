<?php

use App\Http\Integrations\Tmdb\TmdbConnector;
use Illuminate\Support\Facades\Http;
use Saloon\Http\Faking\MockResponse;
use Saloon\Laravel\Facades\Saloon;

beforeEach(function () {
    // Set the access token in session to pass middleware
    session(['app-access-token' => 'test-token']);
    session(['app-user' => [
        'username' => 'testuser',
        'tmdb_token' => 'test-tmdb-token',
    ]]);
});

it('renders trending page with UserWishlists component using object data', function () {
    $apiUrl = config('services.api.url');

    Http::fake([
        $apiUrl.'wishlists' => Http::response([
            'data' => [
                [
                    'id' => 1,
                    'name' => 'Watchlist',
                    'uuid' => 'real-uuid-123',
                    'is_favorite' => true,
                ],
            ],
        ], 200),
        $apiUrl.'items' => Http::response(['data' => []], 200),
    ]);

    // Mock TMDB API response
    Saloon::fake([
        TmdbConnector::class => MockResponse::make([
            'results' => [
                [
                    'id' => 1,
                    'title' => 'Test Movie',
                    'overview' => 'Test overview',
                    'poster_path' => '/test.jpg',
                    'media_type' => 'movie',
                ],
            ],
        ], 200),
    ]);

    $response = $this->get('/trending');

    $response->assertStatus(200)
        ->assertSee('Trending')
        ->assertSee('Test Movie')
        ->assertSee('Watchlist') // Should see wishlist in sidebar
        ->assertSee('lists/real-uuid-123', false) // Link to wishlist
        ->assertSee('★'); // Favorite badge
});

it('handles mixed data types in trending page components', function () {
    $apiUrl = config('services.api.url');

    Http::fake([
        $apiUrl.'wishlists' => Http::response([
            'data' => [
                ['id' => 1, 'name' => 'Array List', 'uuid' => 'array-uuid', 'is_favorite' => false],
                ['id' => 2, 'name' => 'Object List', 'uuid' => 'object-uuid', 'is_favorite' => true],
            ],
        ], 200),
        $apiUrl.'items' => Http::response(['data' => []], 200),
    ]);

    Saloon::fake([
        TmdbConnector::class => MockResponse::make(['results' => []], 200),
    ]);

    $response = $this->get('/trending');

    $response->assertStatus(200)
        ->assertSee('Array List')
        ->assertSee('Object List');
});

it('does not crash when wishlists have missing fields', function () {
    $apiUrl = config('services.api.url');

    Http::fake([
        $apiUrl.'wishlists' => Http::response([
            'data' => [
                ['name' => 'No UUID'],
                ['uuid' => 'no-name'],
                [],
            ],
        ], 200),
        $apiUrl.'items' => Http::response(['data' => []], 200),
    ]);

    Saloon::fake([
        TmdbConnector::class => MockResponse::make(['results' => []], 200),
    ]);

    $response = $this->get('/trending');

    $response->assertStatus(200);
});

it('renders trending page when wishlists API returns unexpected format', function () {
    $apiUrl = config('services.api.url');

    Http::fake([
        $apiUrl.'wishlists' => Http::response(null, 200),
        $apiUrl.'items' => Http::response(['data' => []], 200),
    ]);

    Saloon::fake([
        TmdbConnector::class => MockResponse::make(['results' => []], 200),
    ]);

    $response = $this->get('/trending');

    $response->assertStatus(200)
        ->assertSee('Trending');
});
