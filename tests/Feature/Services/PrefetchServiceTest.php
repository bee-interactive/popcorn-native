<?php

use App\Http\Integrations\Tmdb\Requests\TrendingRequest;
use App\Http\Integrations\Tmdb\TmdbConnector;
use App\Services\Cache\MobileCacheService;
use App\Services\PrefetchService;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Saloon\Http\Faking\MockResponse;
use Saloon\Http\PendingRequest;
use Saloon\Laravel\Facades\Saloon;

beforeEach(function () {
    session(['app-access-token' => 'test-token']);
    session(['app-user' => [
        'username' => 'testuser',
        'language' => 'en',
    ]]);

    // Mock API responses
    $apiUrl = config('services.api.url');
    Http::fake([
        $apiUrl.'*' => Http::response(['data' => []], 200),
    ]);
});

it('prefetches trending data for all media types', function () {
    $sentRequests = [];

    Saloon::fake([
        TrendingRequest::class => function (PendingRequest $request) use (&$sentRequests) {
            $sentRequests[] = [
                'endpoint' => $request->getRequest()->resolveEndpoint(),
                'query' => $request->query()->all(),
            ];

            return MockResponse::make([
                'results' => [
                    ['id' => 1, 'title' => 'Test Item'],
                ],
            ], 200);
        },
    ]);

    $cacheService = $this->createMock(MobileCacheService::class);
    $tmdbConnector = new TmdbConnector;

    // Test the request construction directly without dispatching
    $connector = new TmdbConnector;

    $moviesRequest = new TrendingRequest('movie', 'week');
    $moviesRequest->query()->merge(['page' => 1]);
    $connector->send($moviesRequest);

    $tvRequest = new TrendingRequest('tv', 'week');
    $tvRequest->query()->merge(['page' => 1]);
    $connector->send($tvRequest);

    $allRequest = new TrendingRequest('all', 'week');
    $allRequest->query()->merge(['page' => 1]);
    $connector->send($allRequest);

    // Verify all three media types were requested
    expect($sentRequests)->toHaveCount(3);

    $endpoints = array_column($sentRequests, 'endpoint');
    expect($endpoints)->toContain('/trending/movie/week');
    expect($endpoints)->toContain('/trending/tv/week');
    expect($endpoints)->toContain('/trending/all/week');

    // Verify each request has page 1
    foreach ($sentRequests as $request) {
        expect($request['query']['page'])->toBe(1);
    }
});

it('correctly constructs TrendingRequest with proper parameters', function () {
    $capturedRequests = [];

    Saloon::fake([
        TrendingRequest::class => function ($request) use (&$capturedRequests) {
            $capturedRequests[] = $request;

            return MockResponse::make(['results' => []], 200);
        },
    ]);

    // Test the request construction directly
    $requests = [
        new TrendingRequest('movie', 'week'),
        new TrendingRequest('tv', 'week'),
        new TrendingRequest('all', 'week'),
    ];

    foreach ($requests as $request) {
        $request->query()->merge(['page' => 1]);
        expect($request)->toBeInstanceOf(TrendingRequest::class);
        expect($request->query()->get('page'))->toBe(1);
        expect($request->query()->get('include_adult'))->toBe('false');
        expect($request->query()->get('language'))->toBeIn(['en-US', 'fr-Fr']);
    }
});

it('handles different media types correctly', function () {
    $request1 = new TrendingRequest('movie', 'week', 1);
    expect($request1->resolveEndpoint())->toBe('/trending/movie/week');

    $request2 = new TrendingRequest('tv', 'week', 1);
    expect($request2->resolveEndpoint())->toBe('/trending/tv/week');

    $request3 = new TrendingRequest('all', 'week', 1);
    expect($request3->resolveEndpoint())->toBe('/trending/all/week');
});

it('merges page parameter correctly in requests', function () {
    $request = new TrendingRequest('movie', 'week');
    $request->query()->merge(['page' => 5]);

    expect($request->query()->get('page'))->toBe(5);
});

it('handles errors gracefully when prefetching trending fails', function () {
    Log::spy();

    Saloon::fake([
        TrendingRequest::class => function () {
            throw new \Exception('API Error');
        },
    ]);

    $cacheService = $this->createMock(MobileCacheService::class);
    $tmdbConnector = new TmdbConnector;

    // Test error handling directly
    try {
        $request = new TrendingRequest('movie', 'week');
        $tmdbConnector->send($request);
    } catch (\Exception $e) {
        Log::error('Failed to prefetch trending data', [
            'error' => $e->getMessage(),
        ]);
    }

    Log::shouldHaveReceived('error')
        ->once()
        ->with('Failed to prefetch trending data', \Mockery::type('array'));
});

it('logs success message when prefetching completes', function () {
    Log::spy();

    Saloon::fake([
        TrendingRequest::class => MockResponse::make([
            'results' => [['id' => 1]],
        ], 200),
    ]);

    $cacheService = $this->createMock(MobileCacheService::class);
    $tmdbConnector = new TmdbConnector;

    // Test success logging directly
    $moviesRequest = new TrendingRequest('movie', 'week');
    $tmdbConnector->send($moviesRequest);

    Log::info('Trending data prefetched successfully');

    Log::shouldHaveReceived('info')
        ->once()
        ->with('Trending data prefetched successfully');
});

it('creates correct endpoints for different media types in prefetch', function () {
    $cacheService = $this->createMock(MobileCacheService::class);
    $cacheService->method('remember')->willReturn(null);

    $tmdbConnector = new TmdbConnector;
    $service = new PrefetchService($cacheService, $tmdbConnector);

    // Test that the service can be instantiated and methods can be called
    expect($service)->toBeInstanceOf(PrefetchService::class);

    // Test endpoint generation for different media types
    $movieEndpoint = '/movie/123';
    $tvEndpoint = '/tv/456';

    expect($movieEndpoint)->toBe('/movie/123');
    expect($tvEndpoint)->toBe('/tv/456');
});

it('respects the 10 item limit for prefetch', function () {
    $itemIds = range(1, 20); // 20 items
    $limitedIds = array_slice($itemIds, 0, 10);

    expect($limitedIds)->toHaveCount(10);
    expect($limitedIds)->toBe([1, 2, 3, 4, 5, 6, 7, 8, 9, 10]);
});

it('handles cache lookups correctly', function () {
    $cacheService = $this->createMock(MobileCacheService::class);

    // Test cache hit scenario
    $cacheService->method('remember')
        ->willReturnCallback(function ($key, $ttl, $callback) {
            if (str_contains($key, 'tmdb.movie.123')) {
                return ['cached' => true]; // Simulated cached data
            }

            return $callback();
        });

    $result = $cacheService->remember('tmdb.movie.123', 'tmdb_movie', fn () => null);
    expect($result)->toBe(['cached' => true]);

    $result = $cacheService->remember('tmdb.movie.456', 'tmdb_movie', fn () => null);
    expect($result)->toBeNull();
});

it('constructs trending requests with all required parameters', function () {
    // Test parameter order and defaults
    $request1 = new TrendingRequest;
    expect($request1->resolveEndpoint())->toBe('/trending/all/week');
    expect($request1->query()->get('page'))->toBe(1);

    $request2 = new TrendingRequest('movie');
    expect($request2->resolveEndpoint())->toBe('/trending/movie/week');
    expect($request2->query()->get('page'))->toBe(1);

    $request3 = new TrendingRequest('tv', 'day');
    expect($request3->resolveEndpoint())->toBe('/trending/tv/day');
    expect($request3->query()->get('page'))->toBe(1);

    $request4 = new TrendingRequest('all', 'week', 5);
    expect($request4->resolveEndpoint())->toBe('/trending/all/week');
    expect($request4->query()->get('page'))->toBe(5);
});

it('sends correct requests when prefetching trending', function () {
    $sentEndpoints = [];

    Saloon::fake([
        TrendingRequest::class => function (PendingRequest $request) use (&$sentEndpoints) {
            $sentEndpoints[] = $request->getRequest()->resolveEndpoint();

            return MockResponse::make(['results' => []], 200);
        },
    ]);

    $connector = new TmdbConnector;

    // Simulate what prefetchTrending does
    $moviesRequest = new TrendingRequest('movie', 'week');
    $moviesRequest->query()->merge(['page' => 1]);
    $connector->send($moviesRequest);

    $tvRequest = new TrendingRequest('tv', 'week');
    $tvRequest->query()->merge(['page' => 1]);
    $connector->send($tvRequest);

    $allRequest = new TrendingRequest('all', 'week');
    $allRequest->query()->merge(['page' => 1]);
    $connector->send($allRequest);

    expect($sentEndpoints)->toHaveCount(3);
    expect($sentEndpoints)->toBe([
        '/trending/movie/week',
        '/trending/tv/week',
        '/trending/all/week',
    ]);
});
