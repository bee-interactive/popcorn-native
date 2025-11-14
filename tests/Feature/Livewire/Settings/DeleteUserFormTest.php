<?php

use App\Livewire\Settings\DeleteUserForm;
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
        ],
    ]);

    config(['services.api.url' => 'https://api.test/']);
});

test('deletes user successfully and logs out', function () {
    Http::fake([
        'https://api.test/users/user-uuid-123' => Http::response(['success' => true], 200),
    ]);

    Livewire::test(DeleteUserForm::class)
        ->set('password', 'MyPassword123!')
        ->call('deleteUser')
        ->assertHasNoErrors()
        ->assertRedirect('/');
});

test('validates password is required', function () {
    Livewire::test(DeleteUserForm::class)
        ->set('password', '')
        ->call('deleteUser')
        ->assertHasErrors(['password']);
});

test('sends correct data to API', function () {
    Http::fake([
        'https://api.test/users/user-uuid-123' => Http::response(['success' => true], 200),
    ]);

    Livewire::test(DeleteUserForm::class)
        ->set('password', 'TestPassword123!')
        ->call('deleteUser');

    Http::assertSent(function ($request) {
        return $request->url() === 'https://api.test/users/user-uuid-123'
            && $request->method() === 'DELETE'
            && $request['password'] === 'TestPassword123!';
    });
});

test('sends auth token in request', function () {
    Http::fake([
        'https://api.test/users/user-uuid-123' => Http::response(['success' => true], 200),
    ]);

    Livewire::test(DeleteUserForm::class)
        ->set('password', 'TestPassword123!')
        ->call('deleteUser');

    Http::assertSent(function ($request) {
        return $request->hasHeader('Authorization')
            && $request->header('Authorization')[0] === 'Bearer test-token';
    });
});

test('handles validation errors from API', function () {
    Http::fake([
        'https://api.test/users/user-uuid-123' => Http::response([
            'message' => 'Validation failed',
            'errors' => [
                'password' => ['The password is incorrect.'],
            ],
        ], 422),
    ]);

    Livewire::test(DeleteUserForm::class)
        ->set('password', 'WrongPassword')
        ->call('deleteUser')
        ->assertHasErrors(['password'])
        ->assertSet('message', 'Validation failed');
});

test('handles server errors gracefully', function () {
    Http::fake([
        'https://api.test/users/user-uuid-123' => Http::response([
            'message' => 'Server error',
        ], 500),
    ]);

    Livewire::test(DeleteUserForm::class)
        ->set('password', 'TestPassword123!')
        ->call('deleteUser')
        ->assertSet('message', 'Server error');
});

test('handles missing user session', function () {
    session()->forget('app-user');

    Livewire::test(DeleteUserForm::class)
        ->set('password', 'TestPassword123!')
        ->call('deleteUser')
        ->assertSet('message', 'User session not found. Please login again.');
});

test('handles user session without uuid', function () {
    session([
        'app-user' => [
            'name' => 'John Doe',
        ],
    ]);

    Livewire::test(DeleteUserForm::class)
        ->set('password', 'TestPassword123!')
        ->call('deleteUser')
        ->assertSet('message', 'User session not found. Please login again.');
});

test('resets message before deletion', function () {
    Http::fake([
        'https://api.test/users/user-uuid-123' => Http::response(['success' => true], 200),
    ]);

    Livewire::test(DeleteUserForm::class)
        ->set('message', 'Previous error message')
        ->set('password', 'TestPassword123!')
        ->call('deleteUser')
        ->assertSet('message', '');
});

test('uses correct API endpoint with user uuid', function () {
    Http::fake([
        'https://api.test/users/user-uuid-123' => Http::response(['success' => true], 200),
    ]);

    Livewire::test(DeleteUserForm::class)
        ->set('password', 'TestPassword123!')
        ->call('deleteUser');

    Http::assertSent(function ($request) {
        return str_contains($request->url(), 'users/user-uuid-123');
    });
});

test('handles multiple validation errors', function () {
    Http::fake([
        'https://api.test/users/user-uuid-123' => Http::response([
            'message' => 'Multiple errors',
            'errors' => [
                'password' => ['The password is incorrect.'],
                'confirmation' => ['Confirmation required.'],
            ],
        ], 422),
    ]);

    Livewire::test(DeleteUserForm::class)
        ->set('password', 'TestPassword')
        ->call('deleteUser')
        ->assertHasErrors(['password', 'confirmation'])
        ->assertSet('message', 'Multiple errors');
});

test('handles array of error messages for single field', function () {
    Http::fake([
        'https://api.test/users/user-uuid-123' => Http::response([
            'message' => 'Validation failed',
            'errors' => [
                'password' => [
                    'The password is incorrect.',
                    'The password must be confirmed.',
                ],
            ],
        ], 422),
    ]);

    Livewire::test(DeleteUserForm::class)
        ->set('password', 'TestPassword')
        ->call('deleteUser')
        ->assertHasErrors(['password']);
});

test('handles string error message for field', function () {
    Http::fake([
        'https://api.test/users/user-uuid-123' => Http::response([
            'message' => 'Validation failed',
            'errors' => [
                'password' => 'The password is incorrect.',
            ],
        ], 422),
    ]);

    Livewire::test(DeleteUserForm::class)
        ->set('password', 'TestPassword')
        ->call('deleteUser')
        ->assertHasErrors(['password']);
});

test('shows default validation message when API provides none', function () {
    Http::fake([
        'https://api.test/users/user-uuid-123' => Http::response([
            'errors' => [
                'password' => ['Incorrect password'],
            ],
        ], 422),
    ]);

    Livewire::test(DeleteUserForm::class)
        ->set('password', 'TestPassword')
        ->call('deleteUser')
        ->assertSet('message', 'Validation failed. Please check your password.');
});

test('shows default error message when API error has no message', function () {
    Http::fake([
        'https://api.test/users/user-uuid-123' => Http::response([], 500),
    ]);

    Livewire::test(DeleteUserForm::class)
        ->set('password', 'TestPassword123!')
        ->call('deleteUser')
        ->assertSet('message', 'Failed to delete account. Please try again.');
});

test('password property starts empty', function () {
    Livewire::test(DeleteUserForm::class)
        ->assertSet('password', '')
        ->assertSet('message', '');
});

test('accepts json response', function () {
    Http::fake([
        'https://api.test/users/user-uuid-123' => Http::response(['success' => true], 200),
    ]);

    Livewire::test(DeleteUserForm::class)
        ->set('password', 'TestPassword123!')
        ->call('deleteUser');

    Http::assertSent(function ($request) {
        return $request->hasHeader('Accept')
            && str_contains($request->header('Accept')[0], 'application/json');
    });
});

test('does not delete when validation fails', function () {
    Http::fake();

    Livewire::test(DeleteUserForm::class)
        ->set('password', '')
        ->call('deleteUser');

    Http::assertNothingSent();
});

test('handles unauthorized response', function () {
    Http::fake([
        'https://api.test/users/user-uuid-123' => Http::response([
            'message' => 'Unauthorized',
        ], 401),
    ]);

    Livewire::test(DeleteUserForm::class)
        ->set('password', 'TestPassword123!')
        ->call('deleteUser')
        ->assertSet('message', 'Unauthorized');
});

test('handles not found response', function () {
    Http::fake([
        'https://api.test/users/user-uuid-123' => Http::response([
            'message' => 'User not found',
        ], 404),
    ]);

    Livewire::test(DeleteUserForm::class)
        ->set('password', 'TestPassword123!')
        ->call('deleteUser')
        ->assertSet('message', 'User not found');
});

test('password can contain special characters', function () {
    Http::fake([
        'https://api.test/users/user-uuid-123' => Http::response(['success' => true], 200),
    ]);

    Livewire::test(DeleteUserForm::class)
        ->set('password', 'P@ssw0rd!#$%^&*()')
        ->call('deleteUser')
        ->assertHasNoErrors();
});

test('password can be very long', function () {
    Http::fake([
        'https://api.test/users/user-uuid-123' => Http::response(['success' => true], 200),
    ]);

    $longPassword = str_repeat('LongPassword123!', 10);

    Livewire::test(DeleteUserForm::class)
        ->set('password', $longPassword)
        ->call('deleteUser')
        ->assertHasNoErrors();
});

test('redirects with navigate parameter', function () {
    Http::fake([
        'https://api.test/users/user-uuid-123' => Http::response(['success' => true], 200),
    ]);

    Livewire::test(DeleteUserForm::class)
        ->set('password', 'TestPassword123!')
        ->call('deleteUser')
        ->assertRedirect('/');
});

test('validates password is string type', function () {
    Http::fake([
        'https://api.test/users/user-uuid-123' => Http::response(['success' => true], 200),
    ]);

    Livewire::test(DeleteUserForm::class)
        ->set('password', 'ValidStringPassword')
        ->call('deleteUser')
        ->assertHasNoErrors();
});

test('preserves password value when session check fails', function () {
    session()->forget('app-user');

    Livewire::test(DeleteUserForm::class)
        ->set('password', 'TestPassword123!')
        ->call('deleteUser')
        ->assertSet('password', 'TestPassword123!');
});

test('handles empty errors array from API', function () {
    Http::fake([
        'https://api.test/users/user-uuid-123' => Http::response([
            'message' => 'Validation failed',
            'errors' => [],
        ], 422),
    ]);

    Livewire::test(DeleteUserForm::class)
        ->set('password', 'TestPassword')
        ->call('deleteUser')
        ->assertSet('message', 'Validation failed');
});
