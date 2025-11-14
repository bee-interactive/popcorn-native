<?php

use App\Livewire\Settings\Password;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function () {
    session(['app-access-token' => 'test-token']);
    config(['services.api.url' => 'https://api.test/']);
});

test('updates password successfully', function () {
    Http::fake([
        'https://api.test/users/password' => Http::response(['success' => true], 200),
    ]);

    Livewire::test(Password::class)
        ->set('password', 'NewPassword123!')
        ->set('password_confirmation', 'NewPassword123!')
        ->call('updatePassword')
        ->assertHasNoErrors()
        ->assertDispatched('password-updated');
});

test('validates password is required', function () {
    Livewire::test(Password::class)
        ->set('password', '')
        ->set('password_confirmation', '')
        ->call('updatePassword')
        ->assertHasErrors(['password']);
});

test('validates password confirmation matches', function () {
    Livewire::test(Password::class)
        ->set('password', 'NewPassword123!')
        ->set('password_confirmation', 'DifferentPassword123!')
        ->call('updatePassword')
        ->assertHasErrors(['password']);
});

test('validates password meets strength requirements', function () {
    Livewire::test(Password::class)
        ->set('password', 'weak')
        ->set('password_confirmation', 'weak')
        ->call('updatePassword')
        ->assertHasErrors(['password']);
});

test('accepts strong password', function () {
    Http::fake([
        'https://api.test/users/password' => Http::response(['success' => true], 200),
    ]);

    Livewire::test(Password::class)
        ->set('password', 'StrongPassword123!')
        ->set('password_confirmation', 'StrongPassword123!')
        ->call('updatePassword')
        ->assertHasNoErrors();
});

test('resets password fields on validation error', function () {
    Livewire::test(Password::class)
        ->set('current_password', 'CurrentPassword123!')
        ->set('password', 'weak')
        ->set('password_confirmation', 'weak')
        ->call('updatePassword')
        ->assertSet('current_password', '')
        ->assertSet('password', '')
        ->assertSet('password_confirmation', '');
});

test('sends correct data to API', function () {
    Http::fake([
        'https://api.test/users/password' => Http::response(['success' => true], 200),
    ]);

    Livewire::test(Password::class)
        ->set('password', 'NewPassword123!')
        ->set('password_confirmation', 'NewPassword123!')
        ->call('updatePassword');

    Http::assertSent(function ($request) {
        return $request->url() === 'https://api.test/users/password'
            && $request->method() === 'POST'
            && $request['password'] === 'NewPassword123!';
    });
});

test('dispatches password-updated event on success', function () {
    Http::fake([
        'https://api.test/users/password' => Http::response(['success' => true], 200),
    ]);

    Livewire::test(Password::class)
        ->set('password', 'NewPassword123!')
        ->set('password_confirmation', 'NewPassword123!')
        ->call('updatePassword')
        ->assertDispatched('password-updated');
});

test('validates password is string', function () {
    Http::fake([
        'https://api.test/users/password' => Http::response(['success' => true], 200),
    ]);

    Livewire::test(Password::class)
        ->set('password', 'ValidString123!')
        ->set('password_confirmation', 'ValidString123!')
        ->call('updatePassword')
        ->assertHasNoErrors();
});

test('minimum password length is enforced', function () {
    Livewire::test(Password::class)
        ->set('password', 'Short1!')
        ->set('password_confirmation', 'Short1!')
        ->call('updatePassword')
        ->assertHasErrors(['password']);
});

test('accepts password at minimum valid length', function () {
    Http::fake([
        'https://api.test/users/password' => Http::response(['success' => true], 200),
    ]);

    Livewire::test(Password::class)
        ->set('password', 'Password1!')
        ->set('password_confirmation', 'Password1!')
        ->call('updatePassword')
        ->assertHasNoErrors();
});

test('password with numbers and special characters is valid', function () {
    Http::fake([
        'https://api.test/users/password' => Http::response(['success' => true], 200),
    ]);

    Livewire::test(Password::class)
        ->set('password', 'Complex@Pass123!')
        ->set('password_confirmation', 'Complex@Pass123!')
        ->call('updatePassword')
        ->assertHasNoErrors();
});

test('password confirmation is validated', function () {
    Livewire::test(Password::class)
        ->set('password', 'NewPassword123!')
        ->set('password_confirmation', '')
        ->call('updatePassword')
        ->assertHasErrors(['password']);
});

test('current_password field exists but is not validated', function () {
    Http::fake([
        'https://api.test/users/password' => Http::response(['success' => true], 200),
    ]);

    Livewire::test(Password::class)
        ->set('current_password', '')
        ->set('password', 'NewPassword123!')
        ->set('password_confirmation', 'NewPassword123!')
        ->call('updatePassword')
        ->assertHasNoErrors();
});

test('does not send current_password to API', function () {
    Http::fake([
        'https://api.test/users/password' => Http::response(['success' => true], 200),
    ]);

    Livewire::test(Password::class)
        ->set('current_password', 'CurrentPassword123!')
        ->set('password', 'NewPassword123!')
        ->set('password_confirmation', 'NewPassword123!')
        ->call('updatePassword');

    Http::assertSent(function ($request) {
        return ! isset($request['current_password']);
    });
});

test('only sends new password to API', function () {
    Http::fake([
        'https://api.test/users/password' => Http::response(['success' => true], 200),
    ]);

    Livewire::test(Password::class)
        ->set('password', 'NewPassword123!')
        ->set('password_confirmation', 'NewPassword123!')
        ->call('updatePassword');

    Http::assertSent(function ($request) {
        $body = $request->data();

        return count($body) === 1 && isset($body['password']);
    });
});

test('accepts long complex password', function () {
    Http::fake([
        'https://api.test/users/password' => Http::response(['success' => true], 200),
    ]);

    $longPassword = 'VeryLongAndComplexPassword123!@#$%^&*()';

    Livewire::test(Password::class)
        ->set('password', $longPassword)
        ->set('password_confirmation', $longPassword)
        ->call('updatePassword')
        ->assertHasNoErrors();
});

test('clears all password fields after validation error', function () {
    Livewire::test(Password::class)
        ->set('current_password', 'Old123!')
        ->set('password', 'weak')
        ->set('password_confirmation', 'weak')
        ->call('updatePassword')
        ->assertSet('current_password', '')
        ->assertSet('password', '')
        ->assertSet('password_confirmation', '');
});

test('password property starts empty', function () {
    Livewire::test(Password::class)
        ->assertSet('password', '')
        ->assertSet('password_confirmation', '')
        ->assertSet('current_password', '');
});

test('case sensitive password confirmation', function () {
    Livewire::test(Password::class)
        ->set('password', 'Password123!')
        ->set('password_confirmation', 'PASSWORD123!')
        ->call('updatePassword')
        ->assertHasErrors(['password']);
});

test('whitespace in password is preserved', function () {
    Http::fake([
        'https://api.test/users/password' => Http::response(['success' => true], 200),
    ]);

    Livewire::test(Password::class)
        ->set('password', 'Pass Word 123!')
        ->set('password_confirmation', 'Pass Word 123!')
        ->call('updatePassword');

    Http::assertSent(function ($request) {
        return $request['password'] === 'Pass Word 123!';
    });
});

test('unicode characters in password are accepted', function () {
    Http::fake([
        'https://api.test/users/password' => Http::response(['success' => true], 200),
    ]);

    Livewire::test(Password::class)
        ->set('password', 'Pässwörd123!')
        ->set('password_confirmation', 'Pässwörd123!')
        ->call('updatePassword')
        ->assertHasNoErrors();
});
