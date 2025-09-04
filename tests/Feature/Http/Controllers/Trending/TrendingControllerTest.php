<?php

use App\Http\Integrations\Tmdb\Requests\TrendingRequest;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Saloon\Http\Faking\MockResponse;
use Saloon\Laravel\Facades\Saloon;

beforeEach(function () {
    // Clear cache before each test
    Cache::flush();

    // Set the access token in session to pass middleware
    session(['app-access-token' => 'test-token']);
    session(['app-user' => [
        'username' => 'testuser',
        'tmdb_token' => 'test-tmdb-token',
        'language' => 'en',
    ]]);

    // Mock API responses
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

it('fetches multiple pages of trending data', function () {
    $pageCount = 0;

    Saloon::fake([
        TrendingRequest::class => function () use (&$pageCount) {
            $pageCount++;
            $pageResults = [];
            for ($i = 1; $i <= 20; $i++) {
                $itemId = ($pageCount - 1) * 20 + $i;
                $pageResults[] = [
                    'id' => $itemId,
                    'title' => "Movie {$itemId}",
                    'poster_path' => "/movie{$itemId}.jpg",
                    'media_type' => 'movie',
                ];
            }

            return MockResponse::make([
                'page' => $pageCount,
                'results' => $pageResults,
                'total_pages' => 10,
                'total_results' => 200,
            ], 200);
        },
    ]);

    $response = $this->get('/trending');

    $response->assertStatus(200);

    // Should have all 120 items (6 pages × 20 items)
    $response->assertSee('Movie 1');
    $response->assertSee('Movie 120');
});

it('uses correct parameters for each page request', function () {
    $sentRequests = [];

    Saloon::fake([
        TrendingRequest::class => function (\Saloon\Http\PendingRequest $pendingRequest) use (&$sentRequests) {
            $request = $pendingRequest->getRequest();
            $sentRequests[] = [
                'endpoint' => $request->resolveEndpoint(),
                'page' => $pendingRequest->query()->get('page'),
            ];

            return MockResponse::make([
                'results' => [
                    ['id' => 1, 'title' => 'Test Movie'],
                ],
            ], 200);
        },
    ]);

    $this->get('/trending');

    // Verify 6 requests were made
    expect($sentRequests)->toHaveCount(6);

    // Verify each request has the correct page number and endpoint
    foreach ($sentRequests as $index => $request) {
        expect($request['page'])->toBe($index + 1);
        expect($request['endpoint'])->toBe('/trending/all/week');
    }
});

it('caches results per user', function () {
    $callCount = 0;

    Saloon::fake([
        TrendingRequest::class => function () use (&$callCount) {
            $callCount++;

            return MockResponse::make([
                'results' => [
                    ['id' => 1, 'title' => 'Cached Movie'],
                ],
            ], 200);
        },
    ]);

    // First request should hit the API
    $response1 = $this->get('/trending');
    $response1->assertStatus(200);
    $response1->assertSee('Cached Movie');
    expect($callCount)->toBe(6); // 6 pages

    // Second request should use cache
    $response2 = $this->get('/trending');
    $response2->assertStatus(200);
    $response2->assertSee('Cached Movie');
    expect($callCount)->toBe(6); // Still 6, no new API calls
});

it('caches results separately for different users', function () {
    $callCount = 0;

    Saloon::fake([
        TrendingRequest::class => function () use (&$callCount) {
            $callCount++;

            return MockResponse::make([
                'results' => [
                    ['id' => $callCount, 'title' => "Movie {$callCount}"],
                ],
            ], 200);
        },
    ]);

    // First user request
    session(['app-user' => ['username' => 'user1', 'tmdb_token' => 'test-tmdb-token', 'language' => 'en']]);
    $response1 = $this->get('/trending');
    $response1->assertStatus(200);
    expect($callCount)->toBe(6);

    // Second user request should not use first user's cache
    session(['app-user' => ['username' => 'user2', 'tmdb_token' => 'test-tmdb-token', 'language' => 'en']]);
    $response2 = $this->get('/trending');
    $response2->assertStatus(200);
    expect($callCount)->toBe(12); // 6 more API calls
});

it('handles API failures gracefully', function () {
    Saloon::fake([
        TrendingRequest::class => MockResponse::make([], 500),
    ]);

    $response = $this->get('/trending');

    $response->assertStatus(200);
    $response->assertViewHas('results', []); // Empty results on failure
});

it('handles partial API failures', function () {
    $callCount = 0;

    Saloon::fake([
        TrendingRequest::class => function () use (&$callCount) {
            $callCount++;

            // Third page fails
            if ($callCount === 3) {
                return MockResponse::make([], 500);
            }

            return MockResponse::make([
                'results' => [['id' => $callCount, 'title' => "Movie {$callCount}"]],
            ], 200);
        },
    ]);

    $response = $this->get('/trending');

    $response->assertStatus(200);
    // Should return empty array due to failure on page 3
    $response->assertViewHas('results', []);
});

it('merges results from all pages correctly', function () {
    $pageCount = 0;

    Saloon::fake([
        TrendingRequest::class => function () use (&$pageCount) {
            $pageCount++;

            return MockResponse::make([
                'results' => [
                    ['id' => $pageCount, 'title' => "Page {$pageCount} Movie"],
                ],
            ], 200);
        },
    ]);

    $response = $this->get('/trending');

    $response->assertStatus(200);

    // Verify all pages are merged
    for ($page = 1; $page <= 6; $page++) {
        $response->assertSee("Page {$page} Movie");
    }
});

it('respects cache TTL of 2 hours', function () {
    Saloon::fake([
        TrendingRequest::class => MockResponse::make([
            'results' => [
                ['id' => 1, 'title' => 'Original Movie'],
            ],
        ], 200),
    ]);

    // First request
    $this->get('/trending')->assertSee('Original Movie');

    // Fast-forward time by 2 hours and 1 second
    $this->travelTo(now()->addHours(2)->addSecond());

    // Update mock response
    Saloon::fake([
        TrendingRequest::class => MockResponse::make([
            'results' => [
                ['id' => 2, 'title' => 'New Movie'],
            ],
        ], 200),
    ]);

    // Should fetch new data after cache expires
    $response = $this->get('/trending');
    $response->assertSee('New Movie');
    $response->assertDontSee('Original Movie');
});

it('passes correct media type and time window in requests', function () {
    $capturedRequests = [];

    Saloon::fake([
        TrendingRequest::class => function (\Saloon\Http\PendingRequest $pendingRequest) use (&$capturedRequests) {
            $request = $pendingRequest->getRequest();
            $capturedRequests[] = [
                'endpoint' => $request->resolveEndpoint(),
                'query' => $pendingRequest->query()->all(),
            ];

            return MockResponse::make(['results' => []], 200);
        },
    ]);

    $this->get('/trending');

    // Verify all requests use 'all' media type and 'week' time window
    foreach ($capturedRequests as $request) {
        expect($request['endpoint'])->toBe('/trending/all/week');
    }
});

it('handles empty results gracefully', function () {
    Saloon::fake([
        TrendingRequest::class => MockResponse::make([
            'results' => [],
        ], 200),
    ]);

    $response = $this->get('/trending');

    $response->assertStatus(200);
    $response->assertViewHas('results', []);
});
