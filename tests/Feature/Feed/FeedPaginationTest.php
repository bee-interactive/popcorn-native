<?php

use Illuminate\Support\Facades\Http;

beforeEach(function () {
    // Mock API responses using HTTP fake
    $apiUrl = config('services.api.url');

    Http::fake([
        $apiUrl.'wishlists' => Http::response(['data' => []], 200),
    ]);

    // Set the access token in session to pass the middleware
    session(['app-access-token' => 'test-token']);
});

it('displays paginated feed with 5 users per page', function () {
    // Create mock data with multiple users
    $mockData = [
        'data' => [
            [
                'date' => '2025-01-20',
                'users' => array_map(fn ($i) => [
                    'user' => [
                        'name' => "User $i",
                        'username' => "user$i",
                        'profile_picture' => null,
                    ],
                    'items' => [
                        ['poster_path' => '/path1.jpg'],
                        ['poster_path' => '/path2.jpg'],
                    ],
                ], range(1, 12)), // 12 users total
            ],
        ],
    ];

    $apiUrl = config('services.api.url');
    Http::fake([
        $apiUrl.'users-feed?page=1' => Http::response($mockData, 200),
    ]);

    $response = $this->withSession(['app-access-token' => 'test-token'])->get('/feed');

    $response->assertStatus(200);
    $response->assertViewHas('perPage', 5);
    $response->assertViewHas('currentPage', 1);
    $response->assertViewHas('totalPages', 3); // 12 users / 5 per page = 3 pages

    // Check that only 5 users are displayed
    $viewData = $response->viewData('items');
    $totalUsersInView = 0;
    foreach ($viewData as $dateGroup) {
        $totalUsersInView += count($dateGroup->users);
    }
    expect($totalUsersInView)->toBe(5);
});

it('navigates to different pages correctly', function () {
    // Create mock data
    $mockData = [
        'data' => [
            [
                'date' => '2025-01-20',
                'users' => array_map(fn ($i) => [
                    'user' => [
                        'name' => "User $i",
                        'username' => "user$i",
                        'profile_picture' => null,
                    ],
                    'items' => [],
                ], range(1, 12)),
            ],
        ],
    ];

    $apiUrl = config('services.api.url');
    Http::fake([
        $apiUrl.'users-feed?page=2' => Http::response($mockData, 200),
    ]);

    $response = $this->withSession(['app-access-token' => 'test-token'])->get('/feed?page=2');

    $response->assertStatus(200);
    $response->assertViewHas('currentPage', 2);
    $response->assertViewHas('totalPages', 3);

    // Check that users 6-10 are displayed (page 2)
    $viewData = $response->viewData('items');
    $totalUsersInView = 0;
    foreach ($viewData as $dateGroup) {
        $totalUsersInView += count($dateGroup->users);
    }
    expect($totalUsersInView)->toBe(5);
});

it('shows previous and next buttons correctly', function () {
    $mockData = [
        'data' => [
            [
                'date' => '2025-01-20',
                'users' => array_map(fn ($i) => [
                    'user' => [
                        'name' => "User $i",
                        'username' => "user$i",
                        'profile_picture' => null,
                    ],
                    'items' => [],
                ], range(1, 12)),
            ],
        ],
    ];

    $apiUrl = config('services.api.url');

    // Test first page - no previous button
    Http::fake([
        $apiUrl.'users-feed?page=1' => Http::response($mockData, 200),
    ]);

    $response = $this->withSession(['app-access-token' => 'test-token'])->get('/feed');
    // Check pagination structure on first page
    $response->assertStatus(200);
    $response->assertViewHas('currentPage', 1);
    $response->assertViewHas('totalPages', 3);

    // Test middle page - both buttons
    Http::fake([
        $apiUrl.'users-feed?page=2' => Http::response($mockData, 200),
    ]);

    $response = $this->withSession(['app-access-token' => 'test-token'])->get('/feed?page=2');
    // Check pagination structure on middle page
    $response->assertStatus(200);
    $response->assertViewHas('currentPage', 2);
    $response->assertViewHas('totalPages', 3);

    // Test last page - no next button
    Http::fake([
        $apiUrl.'users-feed?page=3' => Http::response($mockData, 200),
    ]);

    $response = $this->withSession(['app-access-token' => 'test-token'])->get('/feed?page=3');
    // Check pagination structure on last page
    $response->assertStatus(200);
    $response->assertViewHas('currentPage', 3);
    $response->assertViewHas('totalPages', 3);
});

it('handles empty feed data gracefully', function () {
    $apiUrl = config('services.api.url');
    Http::fake([
        $apiUrl.'users-feed?page=1' => Http::response(['data' => []], 200),
    ]);

    $response = $this->withSession(['app-access-token' => 'test-token'])->get('/feed');

    $response->assertStatus(200);
    $response->assertSee('No activity to show yet.');
    $response->assertViewHas('totalPages', 0);
});

it('preserves date grouping across pages', function () {
    $mockData = [
        'data' => [
            [
                'date' => '2025-01-20',
                'users' => array_map(fn ($i) => [
                    'user' => [
                        'name' => "User $i",
                        'username' => "user$i",
                        'profile_picture' => null,
                    ],
                    'items' => [],
                ], range(1, 3)),
            ],
            [
                'date' => '2025-01-19',
                'users' => array_map(fn ($i) => [
                    'user' => [
                        'name' => "User $i",
                        'username' => "user$i",
                        'profile_picture' => null,
                    ],
                    'items' => [],
                ], range(4, 8)),
            ],
        ],
    ];

    $apiUrl = config('services.api.url');
    Http::fake([
        $apiUrl.'users-feed?page=1' => Http::response($mockData, 200),
    ]);

    $response = $this->withSession(['app-access-token' => 'test-token'])->get('/feed');

    $response->assertStatus(200);
    $viewData = $response->viewData('items');

    // First page should have users from both dates
    expect(count($viewData))->toBe(2); // Two date groups
    expect(count($viewData[0]->users))->toBe(3); // 3 users from first date
    expect(count($viewData[1]->users))->toBe(2); // 2 users from second date (to make 5 total)
});
