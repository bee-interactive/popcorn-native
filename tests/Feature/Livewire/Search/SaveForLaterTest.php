<?php

use App\Livewire\Search\SaveForLater;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;

beforeEach(function () {
    // Set up session
    session(['app-access-token' => 'test-token']);
    session(['app-user' => [
        'username' => 'testuser',
        'language' => 'en',
    ]]);

    // Mock API responses
    $apiUrl = config('services.api.url');
    Http::fake([
        $apiUrl.'wishlists' => Http::response([
            'data' => [
                [
                    'id' => 1,
                    'name' => 'Movies to Watch',
                    'uuid' => 'uuid-123',
                    'is_favorite' => false,
                ],
                [
                    'id' => 2,
                    'name' => 'TV Shows',
                    'uuid' => 'uuid-456',
                    'is_favorite' => false,
                ],
            ],
        ], 200),
        $apiUrl.'items' => Http::response([
            'data' => ['id' => 1, 'name' => 'Test Movie'],
            'message' => 'Item saved successfully',
        ], 201),
    ]);
});

it('requires a wishlist to be selected before saving', function () {
    $result = [
        'id' => 123,
        'title' => 'Test Movie',
        'media_type' => 'movie',
        'overview' => 'A test movie',
        'poster_path' => '/test.jpg',
        'release_date' => '2024-01-01',
    ];

    Livewire::test(SaveForLater::class, ['result' => $result])
        ->assertSee('Choose a list and save this entry')
        ->assertSee('Select a list...')
        ->call('save')
        ->assertHasErrors(['wishlist' => 'required'])
        ->assertSee('Please select a list');
});

it('saves item successfully when wishlist is selected', function () {
    $result = [
        'id' => 123,
        'title' => 'Test Movie',
        'media_type' => 'movie',
        'overview' => 'A test movie',
        'poster_path' => '/test.jpg',
        'release_date' => '2024-01-01',
    ];

    Livewire::test(SaveForLater::class, ['result' => $result])
        ->set('wishlist', 'uuid-123')
        ->set('note', 'Great movie to watch')
        ->call('save')
        ->assertHasNoErrors()
        ->assertDispatched('data-updated');
});

it('requires wishlist selection to save', function () {
    $result = [
        'id' => 123,
        'title' => 'Test Movie',
        'media_type' => 'movie',
    ];

    // Try to save without selecting a wishlist
    Livewire::test(SaveForLater::class, ['result' => $result])
        ->call('save')
        ->assertHasErrors(['wishlist' => 'required'])
        ->assertNotDispatched('data-updated');
});

it('shows loading state while saving', function () {
    $result = [
        'id' => 123,
        'title' => 'Test Movie',
        'media_type' => 'movie',
    ];

    Livewire::test(SaveForLater::class, ['result' => $result])
        ->assertSee('Save')
        ->assertSee('Saving...');
});

it('handles TV shows correctly', function () {
    $result = [
        'id' => 456,
        'name' => 'Test TV Show', // TV shows use 'name' instead of 'title'
        'media_type' => 'tv',
        'overview' => 'A test TV show',
        'poster_path' => '/test-tv.jpg',
    ];

    Livewire::test(SaveForLater::class, ['result' => $result])
        ->assertSee('Save Test TV Show for later')
        ->set('wishlist', 'uuid-456')
        ->call('save')
        ->assertHasNoErrors();
});

it('handles persons correctly', function () {
    $result = [
        'id' => 789,
        'name' => 'Test Person',
        'media_type' => 'person',
        'profile_path' => '/person.jpg', // Persons use 'profile_path' instead of 'poster_path'
    ];

    Livewire::test(SaveForLater::class, ['result' => $result])
        ->assertSee('Save Test Person for later')
        ->set('wishlist', 'uuid-123')
        ->call('save')
        ->assertHasNoErrors();
});

it('validates wishlist as required', function () {
    $result = [
        'id' => 123,
        'title' => 'Test Movie',
        'media_type' => 'movie',
    ];

    Livewire::test(SaveForLater::class, ['result' => $result])
        ->set('wishlist', '') // Empty string
        ->call('save')
        ->assertHasErrors(['wishlist' => 'required']);
});

it('displays error message for required wishlist', function () {
    $result = [
        'id' => 123,
        'title' => 'Test Movie',
        'media_type' => 'movie',
    ];

    Livewire::test(SaveForLater::class, ['result' => $result])
        ->call('save')
        ->assertSee('Please select a list');
});
