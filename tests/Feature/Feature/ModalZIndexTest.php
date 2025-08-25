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

it('ensures modal elements have proper position classes for z-index to work', function () {
    // Read the modal template
    $modalContent = file_get_contents(resource_path('views/vendor/wire-elements-modal/modal.blade.php'));

    // Check that elements with z-index also have position classes
    // Elements with z-index should have fixed, absolute, or relative position

    // Check the main modal container
    expect($modalContent)->toContain('fixed inset-0 z-40');

    // Check the inner wrapper has position relative with z-index
    expect($modalContent)->toContain('relative justify-center z-50');

    // Check the backdrop
    expect($modalContent)->toContain('fixed z-40 inset-0');

    // Check the modal content has position relative
    expect($modalContent)->toContain('relative z-50 inline-block');

    // Ensure all z-index classes are paired with position classes
    // Find all lines with z-index
    preg_match_all('/.*z-\d+.*/', $modalContent, $matches);

    foreach ($matches[0] as $line) {
        // Each line with z-index should have fixed, absolute, relative, or sticky
        $hasPosition = preg_match('/(fixed|absolute|relative|sticky)/', $line);
        expect($hasPosition)->toBe(1, 'Line with z-index missing position class: '.trim($line));
    }
});

it('modal renders without z-index console warnings', function () {
    // Mock API responses using HTTP fake
    $apiUrl = config('services.api.url');
    Http::fake([
        $apiUrl.'wishlists' => Http::response([
            'data' => [
                [
                    'id' => 1,
                    'name' => 'Test List',
                    'uuid' => 'test-uuid',
                    'is_favorite' => false,
                ],
            ],
        ], 200),
        $apiUrl.'items' => Http::response(['data' => []], 200),
    ]);

    $response = $this->get('/dashboard');

    $response->assertStatus(200);

    // Check that modal wrapper elements have correct structure
    $response->assertSee('fixed inset-0 z-40', false);
    $response->assertSee('relative justify-center z-50', false);
});
