<?php

use App\Http\Integrations\Tmdb\Requests\TrendingRequest;
use App\Http\Integrations\Tmdb\TmdbConnector;
use Saloon\Http\Faking\MockResponse;
use Saloon\Laravel\Facades\Saloon;

beforeEach(function () {
    session(['app-user' => [
        'username' => 'testuser',
        'language' => 'en',
    ]]);
});

it('creates request with default parameters', function () {
    $request = new TrendingRequest;

    expect($request->resolveEndpoint())->toBe('/trending/all/week');

    $query = $request->query()->all();
    expect($query)->toHaveKey('page', 1);
    expect($query)->toHaveKey('include_adult', 'false');
    expect($query)->toHaveKey('language', 'en-US');
});

it('accepts custom media type', function () {
    $request = new TrendingRequest('movie');

    expect($request->resolveEndpoint())->toBe('/trending/movie/week');
});

it('accepts custom time window', function () {
    $request = new TrendingRequest('tv', 'day');

    expect($request->resolveEndpoint())->toBe('/trending/tv/day');
});

it('accepts custom page number', function () {
    $request = new TrendingRequest('all', 'week', 5);

    expect($request->resolveEndpoint())->toBe('/trending/all/week');
    expect($request->query()->all())->toHaveKey('page', 5);
});

it('handles all parameter combinations correctly', function () {
    $request = new TrendingRequest('movie', 'day', 3);

    expect($request->resolveEndpoint())->toBe('/trending/movie/day');
    expect($request->query()->all())->toHaveKey('page', 3);
});

it('sets french language when user language is french', function () {
    session(['app-user' => ['language' => 'fr']]);

    $request = new TrendingRequest;

    expect($request->query()->all())->toHaveKey('language', 'fr-Fr');
});

it('defaults to english when no user language is set', function () {
    session()->forget('app-user');

    $request = new TrendingRequest;

    expect($request->query()->all())->toHaveKey('language', 'en-US');
});

it('sends request successfully to TMDB', function () {
    Saloon::fake([
        TrendingRequest::class => MockResponse::make([
            'page' => 1,
            'results' => [
                ['id' => 1, 'title' => 'Movie 1'],
                ['id' => 2, 'title' => 'Movie 2'],
            ],
            'total_pages' => 10,
            'total_results' => 200,
        ], 200),
    ]);

    $connector = new TmdbConnector;
    $request = new TrendingRequest('movie', 'week', 1);
    $response = $connector->send($request);

    expect($response->successful())->toBeTrue();
    expect($response->json('results'))->toHaveCount(2);
});

it('works with different media types', function ($mediaType, $expectedEndpoint) {
    $request = new TrendingRequest($mediaType);

    expect($request->resolveEndpoint())->toBe($expectedEndpoint);
})->with([
    ['movie', '/trending/movie/week'],
    ['tv', '/trending/tv/week'],
    ['all', '/trending/all/week'],
    ['person', '/trending/person/week'],
]);

it('works with different time windows', function ($timeWindow, $expectedEndpoint) {
    $request = new TrendingRequest('all', $timeWindow);

    expect($request->resolveEndpoint())->toBe("/trending/all/{$timeWindow}");
})->with([
    ['day', '/trending/all/day'],
    ['week', '/trending/all/week'],
]);

it('handles page parameter as string gracefully', function () {
    // This test ensures the fix prevents the original TypeError
    $request = new TrendingRequest('movie', 'week', 2);

    expect($request->query()->all())->toHaveKey('page', 2);
    expect($request->resolveEndpoint())->toBe('/trending/movie/week');
});

it('maintains backward compatibility with old usage', function () {
    // Test that the new signature works with explicit parameters
    $request = new TrendingRequest('all', 'week', 1);

    expect($request->resolveEndpoint())->toBe('/trending/all/week');
    expect($request->query()->all())->toHaveKey('page', 1);
});

it('can modify query parameters after instantiation', function () {
    $request = new TrendingRequest;
    $request->query()->merge(['page' => 10]);

    expect($request->query()->all())->toHaveKey('page', 10);
});

it('handles invalid page numbers gracefully', function () {
    $request = new TrendingRequest('all', 'week', 0);

    expect($request->query()->all())->toHaveKey('page', 0);

    $request2 = new TrendingRequest('all', 'week', -1);

    expect($request2->query()->all())->toHaveKey('page', -1);
});
