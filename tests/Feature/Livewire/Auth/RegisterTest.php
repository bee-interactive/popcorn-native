<?php

use App\Livewire\Auth\Register;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;

beforeEach(function () {
    config(['services.api.url' => 'https://api.test/']);
});

test('registration creates user and logs in successfully', function () {
    Http::fake([
        'https://api.test/auth/register' => Http::response(['success' => true], 201),
        'https://api.test/auth/login' => Http::response([
            'success' => ['token' => 'test-access-token-123'],
        ], 200),
        'https://api.test/users/me' => Http::response([
            'data' => (object) [
                'uuid' => 'user-uuid-123',
                'name' => 'Test User',
                'username' => 'testuser',
                'email' => 'test@example.com',
                'language' => 'en',
                'description' => 'Test bio',
                'public_profile' => true,
                'tmdb_token' => null,
                'profile_picture' => null,
            ],
        ], 200),
    ]);

    Livewire::test(Register::class)
        ->set('name', 'Test User')
        ->set('username', 'testuser')
        ->set('email', 'test@example.com')
        ->set('password', 'password123')
        ->set('password_confirmation', 'password123')
        ->call('register')
        ->assertRedirect('/');

    expect(session('app-access-token'))->toBe('test-access-token-123');
    expect(session('app-user')['uuid'])->toBe('user-uuid-123');
    expect(session('app-user')['name'])->toBe('Test User');
    expect(session('app-user')['username'])->toBe('testuser');
    expect(session('app-user')['email'])->toBe('test@example.com');
});

test('registration validates required fields', function () {
    Livewire::test(Register::class)
        ->set('name', '')
        ->set('username', '')
        ->set('email', '')
        ->set('password', '')
        ->call('register')
        ->assertHasErrors(['name', 'username', 'email', 'password']);
});

test('registration validates email format', function () {
    Livewire::test(Register::class)
        ->set('name', 'Test User')
        ->set('username', 'testuser')
        ->set('email', 'invalid-email')
        ->set('password', 'password123')
        ->set('password_confirmation', 'password123')
        ->call('register')
        ->assertHasErrors(['email']);
});

test('registration validates password confirmation', function () {
    Livewire::test(Register::class)
        ->set('name', 'Test User')
        ->set('username', 'testuser')
        ->set('email', 'test@example.com')
        ->set('password', 'password123')
        ->set('password_confirmation', 'different-password')
        ->call('register')
        ->assertHasErrors(['password']);
});

test('registration handles API validation errors (422)', function () {
    Http::fake([
        'https://api.test/auth/register' => Http::response([
            'message' => 'The given data was invalid.',
            'errors' => [
                'email' => ['The email has already been taken.'],
                'username' => ['The username has already been taken.'],
            ],
        ], 422),
    ]);

    Livewire::test(Register::class)
        ->set('name', 'Test User')
        ->set('username', 'existing')
        ->set('email', 'existing@example.com')
        ->set('password', 'password123')
        ->set('password_confirmation', 'password123')
        ->call('register')
        ->assertHasErrors(['email', 'username'])
        ->assertSet('message', 'The given data was invalid.');
});

test('registration handles API validation errors with string messages', function () {
    Http::fake([
        'https://api.test/auth/register' => Http::response([
            'message' => 'Validation failed',
            'errors' => [
                'email' => 'Email already exists',
            ],
        ], 422),
    ]);

    Livewire::test(Register::class)
        ->set('name', 'Test User')
        ->set('username', 'testuser')
        ->set('email', 'existing@example.com')
        ->set('password', 'password123')
        ->set('password_confirmation', 'password123')
        ->call('register')
        ->assertHasErrors(['email'])
        ->assertSet('message', 'Validation failed');
});

test('registration handles API server errors (500)', function () {
    Http::fake([
        'https://api.test/auth/register' => Http::response([
            'message' => 'Internal server error',
        ], 500),
    ]);

    Livewire::test(Register::class)
        ->set('name', 'Test User')
        ->set('username', 'testuser')
        ->set('email', 'test@example.com')
        ->set('password', 'password123')
        ->set('password_confirmation', 'password123')
        ->call('register')
        ->assertSet('message', 'Internal server error')
        ->assertNoRedirect();
});

test('registration handles API server errors without message', function () {
    Http::fake([
        'https://api.test/auth/register' => Http::response(null, 503),
    ]);

    Livewire::test(Register::class)
        ->set('name', 'Test User')
        ->set('username', 'testuser')
        ->set('email', 'test@example.com')
        ->set('password', 'password123')
        ->set('password_confirmation', 'password123')
        ->call('register')
        ->assertSet('message', __('Registration failed. Please try again.'))
        ->assertNoRedirect();
});

test('registration succeeds but login fails', function () {
    Http::fake([
        'https://api.test/auth/register' => Http::response(['success' => true], 201),
        'https://api.test/auth/login' => Http::response([
            'message' => 'Invalid credentials',
        ], 401),
    ]);

    Livewire::test(Register::class)
        ->set('name', 'Test User')
        ->set('username', 'testuser')
        ->set('email', 'test@example.com')
        ->set('password', 'password123')
        ->set('password_confirmation', 'password123')
        ->call('register')
        ->assertRedirect('/login')
        ->assertSet('message', __('Account created but login failed. Please try logging in manually.'));

    expect(session('app-access-token'))->toBeNull();
});

test('registration and login succeed but user fetch fails', function () {
    Http::fake([
        'https://api.test/auth/register' => Http::response(['success' => true], 201),
        'https://api.test/auth/login' => Http::response([
            'success' => ['token' => 'test-token'],
        ], 200),
        'https://api.test/users/me' => Http::response(null, 500),
    ]);

    Livewire::test(Register::class)
        ->set('name', 'Test User')
        ->set('username', 'testuser')
        ->set('email', 'test@example.com')
        ->set('password', 'password123')
        ->set('password_confirmation', 'password123')
        ->call('register')
        ->assertRedirect('/login')
        ->assertHasNoErrors();

    // Session should not have token or user
    expect(session('app-access-token'))->toBeNull();
    expect(session('app-user'))->toBeNull();
});

test('email validation rule includes lowercase transformation', function () {
    // Laravel's 'lowercase' validation rule automatically converts email to lowercase
    // This test verifies the rule exists in the validation
    $component = new \App\Livewire\Auth\Register;

    $reflection = new \ReflectionMethod($component, 'register');
    $source = file_get_contents($reflection->getFileName());

    // Verify 'lowercase' rule is present in email validation
    expect($source)->toContain("'email' => ['required', 'string', 'lowercase'");
});

test('registration trims maximum field lengths', function () {
    Livewire::test(Register::class)
        ->set('name', str_repeat('a', 256))
        ->set('username', str_repeat('b', 256))
        ->set('email', str_repeat('c', 256).'@example.com')
        ->set('password', 'password123')
        ->set('password_confirmation', 'password123')
        ->call('register')
        ->assertHasErrors(['name', 'username', 'email']);
});

test('registration clears previous errors on new attempt', function () {
    Http::fake([
        'https://api.test/auth/register' => Http::sequence()
            ->push(['message' => 'Error', 'errors' => ['email' => ['Taken']]], 422)
            ->push(['success' => true], 201),
        'https://api.test/auth/login' => Http::response([
            'success' => ['token' => 'test-token'],
        ], 200),
        'https://api.test/users/me' => Http::response([
            'data' => (object) [
                'uuid' => 'user-uuid',
                'name' => 'Test',
                'username' => 'test',
                'email' => 'test@example.com',
                'language' => 'en',
                'description' => null,
                'public_profile' => false,
                'tmdb_token' => null,
                'profile_picture' => null,
            ],
        ], 200),
    ]);

    $component = Livewire::test(Register::class)
        ->set('name', 'Test User')
        ->set('username', 'testuser')
        ->set('email', 'test@example.com')
        ->set('password', 'password123')
        ->set('password_confirmation', 'password123')
        ->call('register')
        ->assertHasErrors(['email'])
        ->assertSet('message', 'Error');

    $component->call('register')
        ->assertHasNoErrors()
        ->assertSet('message', '')
        ->assertRedirect('/');
});

test('registration sets all user session data correctly', function () {
    Http::fake([
        'https://api.test/auth/register' => Http::response(['success' => true], 201),
        'https://api.test/auth/login' => Http::response([
            'success' => ['token' => 'test-token'],
        ], 200),
        'https://api.test/users/me' => Http::response([
            'data' => (object) [
                'uuid' => 'user-uuid-123',
                'name' => 'John Doe',
                'username' => 'johndoe',
                'email' => 'john@example.com',
                'language' => 'fr',
                'description' => 'My bio',
                'public_profile' => true,
                'tmdb_token' => 'tmdb-token-456',
                'profile_picture' => 'https://example.com/avatar.jpg',
            ],
        ], 200),
    ]);

    Livewire::test(Register::class)
        ->set('name', 'John Doe')
        ->set('username', 'johndoe')
        ->set('email', 'john@example.com')
        ->set('password', 'password123')
        ->set('password_confirmation', 'password123')
        ->call('register')
        ->assertRedirect('/');

    $user = session('app-user');
    expect($user['uuid'])->toBe('user-uuid-123');
    expect($user['name'])->toBe('John Doe');
    expect($user['username'])->toBe('johndoe');
    expect($user['email'])->toBe('john@example.com');
    expect($user['language'])->toBe('fr');
    expect($user['description'])->toBe('My bio');
    expect($user['public_profile'])->toBeTrue();
    expect($user['tmdb_token'])->toBe('tmdb-token-456');
    expect($user['profile_picture'])->toBe('https://example.com/avatar.jpg');
});
