<?php

use App\Http\Integrations\Tmdb\TmdbConnector;
use App\Livewire\Search\SearchBar;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Livewire\Livewire;
use Saloon\Http\Faking\MockResponse;
use Saloon\Laravel\Facades\Saloon;

uses(RefreshDatabase::class);

beforeEach(function () {
    // Set the access token in session to pass middleware
    session(['app-access-token' => 'test-token']);

    // Mock API responses using HTTP fake
    $apiUrl = config('services.api.url');
    Http::fake([
        $apiUrl.'items' => Http::response(['data' => []], 200),
    ]);

    // Create necessary tables for tests if they don't exist
    if (! Schema::hasTable('search_history')) {
        Schema::create('search_history', function ($table) {
            $table->id();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->string('query');
            $table->json('results')->nullable();
            $table->integer('results_count')->default(0);
            $table->timestamps();

            $table->index(['user_id', 'created_at']);
            $table->index('query');
        });
    }

    if (! Schema::hasTable('offline_cache')) {
        Schema::create('offline_cache', function ($table) {
            $table->id();
            $table->string('key')->unique();
            $table->string('type', 50);
            $table->longText('data');
            $table->timestamp('expires_at');
            $table->timestamps();

            $table->index(['type', 'expires_at']);
            $table->index('expires_at');
        });
    }

    if (! Schema::hasTable('cache_analytics')) {
        Schema::create('cache_analytics', function ($table) {
            $table->id();
            $table->string('key')->unique();
            $table->unsignedInteger('access_count')->default(0);
            $table->timestamp('last_accessed_at')->nullable();
            $table->timestamps();

            $table->index(['last_accessed_at', 'access_count']);
        });
    }
});

it('can handle search updates without cache service error', function () {
    // The main purpose of this test is to ensure no "Call to a member function remember() on null" error
    // occurs when the component is updated (which was the original bug)

    // Mock the TMDB API response
    Saloon::fake([
        '*' => MockResponse::make([
            'results' => [
                [
                    'id' => 1,
                    'title' => 'Test Movie',
                    'poster_path' => '/test.jpg',
                    'media_type' => 'movie',
                ],
            ],
        ], 200),
    ]);

    // Mount the component
    $component = Livewire::test(SearchBar::class, ['layout' => 'maximal'])
        ->assertStatus(200);

    // Simulate user typing in search and calling the search method
    // This is what would trigger the bug if getCacheService() returned null
    $component->set('query', 'test movie search');

    // The key test: This should NOT throw "Call to a member function remember() on null"
    $component->call('search', app(TmdbConnector::class));

    // If we get here without error, the bug is fixed
    $component->assertSet('isSearching', false);

    // The fact that we can set and search multiple times proves the fix works
    $component->set('query', 'another test')
        ->call('search', app(TmdbConnector::class))
        ->assertSet('isSearching', false);
});

it('handles search with less than 2 characters', function () {
    $component = Livewire::test(SearchBar::class, ['layout' => 'maximal'])
        ->assertStatus(200);

    // Single character search should clear results
    $component->set('query', 'a')
        ->call('search', app(TmdbConnector::class))
        ->assertSet('results', [])
        ->assertSet('isSearching', false);
});

it('handles failed API responses gracefully', function () {
    // Mock a failed API response
    Saloon::fake([
        TmdbConnector::class => MockResponse::make([], 500),
    ]);

    $component = Livewire::test(SearchBar::class, ['layout' => 'maximal'])
        ->assertStatus(200);

    // Search should return empty results on API failure
    $component->set('query', 'test movie')
        ->call('search', app(TmdbConnector::class))
        ->assertSet('results', [])
        ->assertSet('isSearching', false);
});

it('can open save modal for a result', function () {
    $result = [
        'id' => 123,
        'title' => 'Test Movie',
        'media_type' => 'movie',
    ];

    Livewire::test(SearchBar::class, ['layout' => 'maximal'])
        ->call('save', $result)
        ->assertDispatched('openModal', App\Livewire\Search\SaveForLater::class, ['result' => $result]);
});

it('loads search suggestions on mount', function () {
    // Add some search history
    \DB::table('search_history')->insert([
        ['query' => 'popular search 1', 'results_count' => 5, 'created_at' => now(), 'updated_at' => now()],
        ['query' => 'popular search 1', 'results_count' => 5, 'created_at' => now(), 'updated_at' => now()],
        ['query' => 'popular search 2', 'results_count' => 3, 'created_at' => now(), 'updated_at' => now()],
    ]);

    Cache::flush(); // Clear cache to force fresh load

    Livewire::test(SearchBar::class, ['layout' => 'maximal'])
        ->assertDispatched('search-suggestions-loaded');
});

it('does not throw error when cache service is accessed after hydration', function () {
    // This is a focused test for the specific bug: getCacheService() returning null

    // Mock any API response
    Saloon::fake([
        '*' => MockResponse::make(['results' => []], 200),
    ]);

    $component = Livewire::test(SearchBar::class, ['layout' => 'maximal']);

    // Simulate multiple Livewire update cycles
    // Each one would fail with the original bug
    for ($i = 0; $i < 3; $i++) {
        $component->set('query', "search term $i")
            ->call('search', app(TmdbConnector::class));
    }

    // If we reach here without "Call to a member function remember() on null", the bug is fixed
    expect(true)->toBeTrue();
});

it('persists correctly across multiple Livewire update cycles', function () {
    // This test specifically checks the bug fix - that cache service works after component hydration
    // The main goal is to ensure no "Call to a member function remember() on null" error

    $component = Livewire::test(SearchBar::class, ['layout' => 'maximal']);

    // Mock response for searches
    Saloon::fake([
        TmdbConnector::class => MockResponse::make([
            'results' => [
                ['id' => 1, 'title' => 'Test Result', 'media_type' => 'movie'],
            ],
        ], 200),
    ]);

    // First search - initial state
    $component->set('query', 'test search')
        ->assertSet('query', 'test search');

    // Call search method - this would fail with the bug
    try {
        $component->call('search', app(TmdbConnector::class));
        // If we get here, the bug is fixed
        expect(true)->toBeTrue();
    } catch (\Error $e) {
        if (str_contains($e->getMessage(), 'Call to a member function remember() on null')) {
            $this->fail('Bug not fixed: '.$e->getMessage());
        }
        throw $e;
    }

    // Second update cycle - would also fail with the bug
    $component->set('query', 'another search')
        ->call('search', app(TmdbConnector::class));

    // Third cycle - ensure it still works
    $component->set('query', 'final search')
        ->call('search', app(TmdbConnector::class));

    // If we've made it this far without errors, the bug is fixed
    expect($component->get('query'))->toBe('final search');
});
