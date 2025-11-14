<?php

use App\Livewire\Settings\Profile;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function () {
    session([
        'app-access-token' => 'test-token',
        'app-user' => [
            'uuid' => 'user-uuid-123',
            'name' => 'John Doe',
            'username' => 'johndoe',
            'email' => 'john@example.com',
            'language' => 'en',
            'description' => 'Test description',
            'public_profile' => false,
            'profile_picture' => 'https://example.com/avatar.jpg',
            'tmdb_token' => 'tmdb-token-123',
        ],
    ]);

    config(['services.api.url' => 'https://api.test/']);
});

test('mounts with user data from session', function () {
    Livewire::test(Profile::class)
        ->assertSet('uuid', 'user-uuid-123')
        ->assertSet('name', 'John Doe')
        ->assertSet('username', 'johndoe')
        ->assertSet('email', 'john@example.com')
        ->assertSet('language', 'en')
        ->assertSet('description', 'Test description')
        ->assertSet('public_profile', false)
        ->assertSet('profile_picture', 'https://example.com/avatar.jpg')
        ->assertSet('tmdb_token', 'tmdb-token-123');
});

test('updates profile successfully', function () {
    Http::fake([
        'https://api.test/users/user-uuid-123' => Http::response([
            'data' => (object) [
                'uuid' => 'user-uuid-123',
                'name' => 'Jane Smith',
                'username' => 'janesmith',
                'email' => 'jane@example.com',
                'language' => 'fr',
                'description' => 'Updated description',
                'public_profile' => true,
                'profile_picture' => 'https://example.com/new-avatar.jpg',
            ],
        ], 200),
    ]);

    Livewire::test(Profile::class)
        ->set('name', 'Jane Smith')
        ->set('username', 'janesmith')
        ->set('email', 'jane@example.com')
        ->set('language', 'fr')
        ->set('description', 'Updated description')
        ->set('public_profile', true)
        ->call('updateProfileInformation')
        ->assertHasNoErrors()
        ->assertDispatched('profile-updated');

    expect(session('app-user')['name'])->toBe('Jane Smith');
    expect(session('app-user')['username'])->toBe('janesmith');
    expect(session('app-user')['email'])->toBe('jane@example.com');
    expect(session('app-user')['language'])->toBe('fr');
    expect(session('app-user')['description'])->toBe('Updated description');
});

test('validates name is required', function () {
    Livewire::test(Profile::class)
        ->set('name', '')
        ->call('updateProfileInformation')
        ->assertHasErrors(['name']);
});

test('validates name maximum length', function () {
    Livewire::test(Profile::class)
        ->set('name', str_repeat('a', 256))
        ->call('updateProfileInformation')
        ->assertHasErrors(['name']);
});

test('validates username is required', function () {
    Livewire::test(Profile::class)
        ->set('username', '')
        ->call('updateProfileInformation')
        ->assertHasErrors(['username']);
});

test('validates username maximum length', function () {
    Livewire::test(Profile::class)
        ->set('username', str_repeat('a', 256))
        ->call('updateProfileInformation')
        ->assertHasErrors(['username']);
});

test('validates email is required', function () {
    Livewire::test(Profile::class)
        ->set('email', '')
        ->call('updateProfileInformation')
        ->assertHasErrors(['email']);
});

test('validates email format', function () {
    Livewire::test(Profile::class)
        ->set('email', 'invalid-email')
        ->call('updateProfileInformation')
        ->assertHasErrors(['email']);
});

test('validates email maximum length', function () {
    Livewire::test(Profile::class)
        ->set('email', str_repeat('a', 250).'@test.com')
        ->call('updateProfileInformation')
        ->assertHasErrors(['email']);
});

test('validates email must be lowercase', function () {
    Livewire::test(Profile::class)
        ->set('email', 'JOHN@EXAMPLE.COM')
        ->call('updateProfileInformation')
        ->assertHasErrors(['email']);
});

test('validates language is in allowed list', function () {
    Livewire::test(Profile::class)
        ->set('language', 'de')
        ->call('updateProfileInformation')
        ->assertHasErrors(['language']);
});

test('validates language accepts en', function () {
    Http::fake([
        'https://api.test/users/user-uuid-123' => Http::response([
            'data' => (object) [
                'uuid' => 'user-uuid-123',
                'name' => 'John Doe',
                'username' => 'johndoe',
                'email' => 'john@example.com',
                'language' => 'en',
                'description' => 'Test',
                'public_profile' => false,
                'profile_picture' => '',
            ],
        ], 200),
    ]);

    Livewire::test(Profile::class)
        ->set('language', 'en')
        ->call('updateProfileInformation')
        ->assertHasNoErrors();
});

test('validates language accepts fr', function () {
    Http::fake([
        'https://api.test/users/user-uuid-123' => Http::response([
            'data' => (object) [
                'uuid' => 'user-uuid-123',
                'name' => 'John Doe',
                'username' => 'johndoe',
                'email' => 'john@example.com',
                'language' => 'fr',
                'description' => 'Test',
                'public_profile' => false,
                'profile_picture' => '',
            ],
        ], 200),
    ]);

    Livewire::test(Profile::class)
        ->set('language', 'fr')
        ->call('updateProfileInformation')
        ->assertHasNoErrors();
});

test('validates description maximum length', function () {
    Livewire::test(Profile::class)
        ->set('description', str_repeat('a', 501))
        ->call('updateProfileInformation')
        ->assertHasErrors(['description']);
});

test('validates description is optional', function () {
    Http::fake([
        'https://api.test/users/user-uuid-123' => Http::response([
            'data' => (object) [
                'uuid' => 'user-uuid-123',
                'name' => 'John Doe',
                'username' => 'johndoe',
                'email' => 'john@example.com',
                'language' => 'en',
                'description' => null,
                'public_profile' => false,
                'profile_picture' => '',
            ],
        ], 200),
    ]);

    Livewire::test(Profile::class)
        ->set('description', '')
        ->call('updateProfileInformation')
        ->assertHasNoErrors();
});

test('validates public_profile is boolean', function () {
    Http::fake([
        'https://api.test/users/user-uuid-123' => Http::response([
            'data' => (object) [
                'uuid' => 'user-uuid-123',
                'name' => 'John Doe',
                'username' => 'johndoe',
                'email' => 'john@example.com',
                'language' => 'en',
                'description' => 'Test',
                'public_profile' => true,
                'profile_picture' => '',
            ],
        ], 200),
    ]);

    Livewire::test(Profile::class)
        ->set('public_profile', true)
        ->call('updateProfileInformation')
        ->assertHasNoErrors();
});

test('updates session with new user data', function () {
    Http::fake([
        'https://api.test/users/user-uuid-123' => Http::response([
            'data' => (object) [
                'uuid' => 'user-uuid-123',
                'name' => 'Updated Name',
                'username' => 'updateduser',
                'email' => 'updated@example.com',
                'language' => 'fr',
                'description' => 'New description',
                'public_profile' => true,
                'profile_picture' => 'https://example.com/updated.jpg',
            ],
        ], 200),
    ]);

    Livewire::test(Profile::class)
        ->set('name', 'Updated Name')
        ->set('username', 'updateduser')
        ->set('email', 'updated@example.com')
        ->set('language', 'fr')
        ->set('description', 'New description')
        ->set('public_profile', true)
        ->call('updateProfileInformation');

    $sessionUser = session('app-user');

    expect($sessionUser['uuid'])->toBe('user-uuid-123');
    expect($sessionUser['name'])->toBe('Updated Name');
    expect($sessionUser['username'])->toBe('updateduser');
    expect($sessionUser['email'])->toBe('updated@example.com');
    expect($sessionUser['language'])->toBe('fr');
    expect($sessionUser['description'])->toBe('New description');
    expect($sessionUser['public_profile'])->toBeTrue();
    expect($sessionUser['profile_picture'])->toBe('https://example.com/updated.jpg');
});

test('sets locale cookie when language is updated', function () {
    Http::fake([
        'https://api.test/users/user-uuid-123' => Http::response([
            'data' => (object) [
                'uuid' => 'user-uuid-123',
                'name' => 'John Doe',
                'username' => 'johndoe',
                'email' => 'john@example.com',
                'language' => 'fr',
                'description' => 'Test',
                'public_profile' => false,
                'profile_picture' => '',
            ],
        ], 200),
    ]);

    Livewire::test(Profile::class)
        ->set('language', 'fr')
        ->call('updateProfileInformation');

    $this->assertNotNull(cookie()->queued('locale'));
});

test('sends correct data to API', function () {
    Http::fake([
        'https://api.test/users/user-uuid-123' => Http::response([
            'data' => (object) [
                'uuid' => 'user-uuid-123',
                'name' => 'Test User',
                'username' => 'testuser',
                'email' => 'test@example.com',
                'language' => 'en',
                'description' => 'Testing',
                'public_profile' => true,
                'profile_picture' => '',
            ],
        ], 200),
    ]);

    Livewire::test(Profile::class)
        ->set('name', 'Test User')
        ->set('username', 'testuser')
        ->set('email', 'test@example.com')
        ->set('language', 'en')
        ->set('description', 'Testing')
        ->set('public_profile', true)
        ->call('updateProfileInformation');

    Http::assertSent(function ($request) {
        return $request->url() === 'https://api.test/users/user-uuid-123'
            && $request->method() === 'PATCH'
            && $request['data']['name'] === 'Test User'
            && $request['data']['username'] === 'testuser'
            && $request['data']['email'] === 'test@example.com'
            && $request['data']['language'] === 'en'
            && $request['data']['description'] === 'Testing'
            && $request['data']['public_profile'] === true;
    });
});

test('handles API errors gracefully', function () {
    Http::fake([
        'https://api.test/users/user-uuid-123' => Http::response(['error' => 'Server error'], 500),
    ]);

    Livewire::test(Profile::class)
        ->set('name', 'Test User')
        ->call('updateProfileInformation')
        ->assertNotDispatched('profile-updated');
});

test('preserves tmdb_token in session update', function () {
    Http::fake([
        'https://api.test/users/user-uuid-123' => Http::response([
            'data' => (object) [
                'uuid' => 'user-uuid-123',
                'name' => 'Updated Name',
                'username' => 'updateduser',
                'email' => 'updated@example.com',
                'language' => 'en',
                'description' => 'Test',
                'public_profile' => false,
                'profile_picture' => '',
            ],
        ], 200),
    ]);

    Livewire::test(Profile::class)
        ->set('name', 'Updated Name')
        ->call('updateProfileInformation');

    expect(session('app-user')['tmdb_token'])->toBe('tmdb-token-123');
});

test('multiple field validation errors', function () {
    Livewire::test(Profile::class)
        ->set('name', '')
        ->set('username', '')
        ->set('email', 'invalid')
        ->set('language', 'invalid')
        ->call('updateProfileInformation')
        ->assertHasErrors(['name', 'username', 'email', 'language']);
});

test('accepts maximum valid description length', function () {
    Http::fake([
        'https://api.test/users/user-uuid-123' => Http::response([
            'data' => (object) [
                'uuid' => 'user-uuid-123',
                'name' => 'John Doe',
                'username' => 'johndoe',
                'email' => 'john@example.com',
                'language' => 'en',
                'description' => str_repeat('a', 500),
                'public_profile' => false,
                'profile_picture' => '',
            ],
        ], 200),
    ]);

    Livewire::test(Profile::class)
        ->set('description', str_repeat('a', 500))
        ->call('updateProfileInformation')
        ->assertHasNoErrors();
});

test('accepts maximum valid name length', function () {
    Http::fake([
        'https://api.test/users/user-uuid-123' => Http::response([
            'data' => (object) [
                'uuid' => 'user-uuid-123',
                'name' => str_repeat('a', 255),
                'username' => 'johndoe',
                'email' => 'john@example.com',
                'language' => 'en',
                'description' => 'Test',
                'public_profile' => false,
                'profile_picture' => '',
            ],
        ], 200),
    ]);

    Livewire::test(Profile::class)
        ->set('name', str_repeat('a', 255))
        ->call('updateProfileInformation')
        ->assertHasNoErrors();
});

test('accepts maximum valid username length', function () {
    Http::fake([
        'https://api.test/users/user-uuid-123' => Http::response([
            'data' => (object) [
                'uuid' => 'user-uuid-123',
                'name' => 'John Doe',
                'username' => str_repeat('a', 255),
                'email' => 'john@example.com',
                'language' => 'en',
                'description' => 'Test',
                'public_profile' => false,
                'profile_picture' => '',
            ],
        ], 200),
    ]);

    Livewire::test(Profile::class)
        ->set('username', str_repeat('a', 255))
        ->call('updateProfileInformation')
        ->assertHasNoErrors();
});
