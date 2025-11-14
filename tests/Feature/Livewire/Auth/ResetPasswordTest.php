<?php

use App\Livewire\Auth\ResetPassword;
use App\Models\User;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Livewire\Livewire;

uses(RefreshDatabase::class);

test('resets password successfully with valid token', function () {
    Event::fake();

    $user = User::factory()->create([
        'email' => 'test@example.com',
    ]);

    $token = Password::createToken($user);

    Livewire::test(ResetPassword::class, ['token' => $token])
        ->set('email', 'test@example.com')
        ->set('password', 'NewPassword123!')
        ->set('password_confirmation', 'NewPassword123!')
        ->call('resetPassword')
        ->assertHasNoErrors()
        ->assertRedirect(route('login'));

    $user->refresh();
    expect(Hash::check('NewPassword123!', $user->password))->toBeTrue();

    Event::assertDispatched(PasswordReset::class);
});

test('validates required fields', function () {
    Livewire::test(ResetPassword::class, ['token' => 'test-token'])
        ->set('email', '')
        ->set('password', '')
        ->set('password_confirmation', '')
        ->call('resetPassword')
        ->assertHasErrors(['email', 'password']);
});

test('validates email format', function () {
    Livewire::test(ResetPassword::class, ['token' => 'test-token'])
        ->set('email', 'invalid-email')
        ->set('password', 'NewPassword123!')
        ->set('password_confirmation', 'NewPassword123!')
        ->call('resetPassword')
        ->assertHasErrors(['email']);
});

test('validates password confirmation', function () {
    Livewire::test(ResetPassword::class, ['token' => 'test-token'])
        ->set('email', 'test@example.com')
        ->set('password', 'NewPassword123!')
        ->set('password_confirmation', 'DifferentPassword123!')
        ->call('resetPassword')
        ->assertHasErrors(['password']);
});

test('rejects invalid token', function () {
    $user = User::factory()->create([
        'email' => 'test@example.com',
    ]);

    Livewire::test(ResetPassword::class, ['token' => 'invalid-token'])
        ->set('email', 'test@example.com')
        ->set('password', 'NewPassword123!')
        ->set('password_confirmation', 'NewPassword123!')
        ->call('resetPassword')
        ->assertHasErrors(['email'])
        ->assertNoRedirect();

    $user->refresh();
    expect(Hash::check('NewPassword123!', $user->password))->toBeFalse();
});

test('rejects expired token', function () {
    $user = User::factory()->create([
        'email' => 'test@example.com',
    ]);

    $token = Password::createToken($user);

    $this->travel(2)->hours();

    Livewire::test(ResetPassword::class, ['token' => $token])
        ->set('email', 'test@example.com')
        ->set('password', 'NewPassword123!')
        ->set('password_confirmation', 'NewPassword123!')
        ->call('resetPassword')
        ->assertHasErrors(['email']);
});

test('rejects non-existent email', function () {
    $token = 'some-token';

    Livewire::test(ResetPassword::class, ['token' => $token])
        ->set('email', 'nonexistent@example.com')
        ->set('password', 'NewPassword123!')
        ->set('password_confirmation', 'NewPassword123!')
        ->call('resetPassword')
        ->assertHasErrors(['email']);
});

test('token is locked and cannot be modified', function () {
    $user = User::factory()->create([
        'email' => 'test@example.com',
    ]);

    $validToken = Password::createToken($user);

    $this->expectException(\Livewire\Features\SupportLockedProperties\CannotUpdateLockedPropertyException::class);

    Livewire::test(ResetPassword::class, ['token' => $validToken])
        ->set('token', 'modified-token');
});

test('email is pre-filled from query string', function () {
    $response = $this->get(route('password.reset', [
        'token' => 'test-token',
        'email' => 'prefilled@example.com',
    ]));

    $response->assertSeeLivewire(ResetPassword::class);
    $response->assertSee('prefilled@example.com');
});

test('sets session flash message on success', function () {
    $user = User::factory()->create([
        'email' => 'test@example.com',
    ]);

    $token = Password::createToken($user);

    Livewire::test(ResetPassword::class, ['token' => $token])
        ->set('email', 'test@example.com')
        ->set('password', 'NewPassword123!')
        ->set('password_confirmation', 'NewPassword123!')
        ->call('resetPassword');

    expect(session('status'))->not->toBeNull();
});

test('updates remember token on password reset', function () {
    $user = User::factory()->create([
        'email' => 'test@example.com',
        'remember_token' => 'old-token',
    ]);

    $originalToken = $user->remember_token;
    $token = Password::createToken($user);

    Livewire::test(ResetPassword::class, ['token' => $token])
        ->set('email', 'test@example.com')
        ->set('password', 'NewPassword123!')
        ->set('password_confirmation', 'NewPassword123!')
        ->call('resetPassword');

    $user->refresh();
    expect($user->remember_token)->not->toBe($originalToken);
    expect($user->remember_token)->not->toBeNull();
});

test('enforces password rules from Password defaults', function () {
    Livewire::test(ResetPassword::class, ['token' => 'test-token'])
        ->set('email', 'test@example.com')
        ->set('password', 'weak')
        ->set('password_confirmation', 'weak')
        ->call('resetPassword')
        ->assertHasErrors(['password']);
});

test('accepts password at minimum length', function () {
    $user = User::factory()->create([
        'email' => 'test@example.com',
    ]);

    $token = Password::createToken($user);

    Livewire::test(ResetPassword::class, ['token' => $token])
        ->set('email', 'test@example.com')
        ->set('password', 'Password1!')
        ->set('password_confirmation', 'Password1!')
        ->call('resetPassword')
        ->assertHasNoErrors()
        ->assertRedirect(route('login'));
});

test('email must match exact case from database', function () {
    $user = User::factory()->create([
        'email' => 'test@example.com',
    ]);

    $token = Password::createToken($user);

    Livewire::test(ResetPassword::class, ['token' => $token])
        ->set('email', 'TEST@EXAMPLE.COM')
        ->set('password', 'NewPassword123!')
        ->set('password_confirmation', 'NewPassword123!')
        ->call('resetPassword')
        ->assertHasErrors(['email']);

    $user->refresh();
    expect(Hash::check('NewPassword123!', $user->password))->toBeFalse();
});

test('renders reset password page', function () {
    $response = $this->get(route('password.reset', ['token' => 'test-token']));

    $response->assertStatus(200);
    $response->assertSeeLivewire(ResetPassword::class);
});

test('password is hashed before saving', function () {
    $user = User::factory()->create([
        'email' => 'test@example.com',
    ]);

    $token = Password::createToken($user);
    $plainPassword = 'NewPassword123!';

    Livewire::test(ResetPassword::class, ['token' => $token])
        ->set('email', 'test@example.com')
        ->set('password', $plainPassword)
        ->set('password_confirmation', $plainPassword)
        ->call('resetPassword');

    $user->refresh();
    expect($user->password)->not->toBe($plainPassword);
    expect(Hash::check($plainPassword, $user->password))->toBeTrue();
});

test('multiple failed attempts do not lock account', function () {
    $user = User::factory()->create([
        'email' => 'test@example.com',
    ]);

    for ($i = 0; $i < 3; $i++) {
        Livewire::test(ResetPassword::class, ['token' => 'invalid-token'])
            ->set('email', 'test@example.com')
            ->set('password', 'NewPassword123!')
            ->set('password_confirmation', 'NewPassword123!')
            ->call('resetPassword')
            ->assertHasErrors(['email']);
    }

    $validToken = Password::createToken($user);

    Livewire::test(ResetPassword::class, ['token' => $validToken])
        ->set('email', 'test@example.com')
        ->set('password', 'NewPassword123!')
        ->set('password_confirmation', 'NewPassword123!')
        ->call('resetPassword')
        ->assertHasNoErrors();
});

test('token cannot be reused after successful reset', function () {
    $user = User::factory()->create([
        'email' => 'test@example.com',
    ]);

    $token = Password::createToken($user);

    Livewire::test(ResetPassword::class, ['token' => $token])
        ->set('email', 'test@example.com')
        ->set('password', 'NewPassword123!')
        ->set('password_confirmation', 'NewPassword123!')
        ->call('resetPassword')
        ->assertHasNoErrors();

    Livewire::test(ResetPassword::class, ['token' => $token])
        ->set('email', 'test@example.com')
        ->set('password', 'AnotherPassword123!')
        ->set('password_confirmation', 'AnotherPassword123!')
        ->call('resetPassword')
        ->assertHasErrors(['email']);
});
