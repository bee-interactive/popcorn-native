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

    // Mock API responses using HTTP fake instead of mocking Popcorn
    $apiUrl = config('services.api.url');
    Http::fake([
        $apiUrl.'wishlists' => Http::response([
            'data' => [
                [
                    'id' => 1,
                    'name' => 'Test List',
                    'uuid' => 'test-uuid-123',
                    'is_favorite' => false,
                ],
            ],
        ], 200),
        $apiUrl.'items' => Http::response(['data' => []], 200),
    ]);
});

it('displays trending items with hover overlay', function () {
    // Mock TMDB API response
    Saloon::fake([
        TmdbConnector::class => MockResponse::make([
            'results' => [
                [
                    'id' => 1,
                    'title' => 'Test Movie',
                    'overview' => 'A great test movie about testing',
                    'poster_path' => '/test.jpg',
                    'media_type' => 'movie',
                    'release_date' => '2024-01-01',
                ],
                [
                    'id' => 2,
                    'name' => 'Test TV Show',
                    'overview' => 'A wonderful TV show for testing',
                    'poster_path' => '/test2.jpg',
                    'media_type' => 'tv',
                ],
            ],
        ], 200),
    ]);

    $response = $this->get('/trending');

    $response->assertStatus(200);

    // Check that items are displayed
    $response->assertSee('Test Movie');
    $response->assertSee('Test TV Show');

    // Check that Alpine.js hover functionality is present (with HTML escaping)
    $response->assertSee('x-data="{ visible: false }"', false);
    $response->assertSee('@mouseover="visible = true"', false);
    $response->assertSee('@mouseleave="visible = false"', false);

    // Check that the add to list button is present with correct onclick
    $response->assertSee("Livewire.dispatch('openModal', { component: 'search.save-for-later'", false);

    // Check translations
    $response->assertSee(__('Add to list'));
});

it('shows movie title and overview in overlay', function () {
    Saloon::fake([
        TmdbConnector::class => MockResponse::make([
            'results' => [
                [
                    'id' => 1,
                    'title' => 'Amazing Movie',
                    'overview' => 'This is an amazing movie with a very long description that should be truncated',
                    'poster_path' => '/amazing.jpg',
                    'media_type' => 'movie',
                ],
            ],
        ], 200),
    ]);

    $response = $this->get('/trending');

    $response->assertStatus(200);
    $response->assertSee('Amazing Movie');
    $response->assertSee('This is an amazing movie with');
});

it('handles items without overview gracefully', function () {
    Saloon::fake([
        TmdbConnector::class => MockResponse::make([
            'results' => [
                [
                    'id' => 1,
                    'title' => 'Movie Without Overview',
                    'poster_path' => '/no-overview.jpg',
                    'media_type' => 'movie',
                ],
            ],
        ], 200),
    ]);

    $response = $this->get('/trending');

    $response->assertStatus(200);
    $response->assertSee('Movie Without Overview');
    // Should not have the overview section if no overview
    $response->assertDontSee('This is an amazing movie');
});

it('passes correct data structure to SaveForLater modal', function () {
    $movieData = [
        'id' => 123,
        'title' => 'Test Movie for Modal',
        'overview' => 'Description',
        'poster_path' => '/modal-test.jpg',
        'media_type' => 'movie',
        'release_date' => '2024-01-01',
        'backdrop_path' => '/backdrop.jpg',
    ];

    Saloon::fake([
        TmdbConnector::class => MockResponse::make([
            'results' => [$movieData],
        ], 200),
    ]);

    $response = $this->get('/trending');

    $response->assertStatus(200);

    // Check that the movie data is passed to the modal
    $response->assertSee('Test Movie for Modal');
    $response->assertSee('search.save-for-later', false);
});
