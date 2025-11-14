<?php

use App\Livewire\Auth\ForgotPassword;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;

beforeEach(function () {
    config(['services.api.url' => 'https://api.test/']);
});

test('can request password reset link successfully', function () {
    Http::fake([
        'https://api.test/auth/request-reset-link' => Http::response(['success' => true], 200),
    ]);

    Livewire::test(ForgotPassword::class)
        ->set('email', 'test@example.com')
        ->call('sendPasswordResetLink')
        ->assertHasNoErrors();

    Http::assertSent(function ($request) {
        return $request->url() === 'https://api.test/auth/request-reset-link'
            && $request['email'] === ['email' => 'test@example.com'];
    });
});

test('validates email is required', function () {
    Livewire::test(ForgotPassword::class)
        ->set('email', '')
        ->call('sendPasswordResetLink')
        ->assertHasErrors(['email' => 'required']);
});

test('validates email format', function () {
    Livewire::test(ForgotPassword::class)
        ->set('email', 'invalid-email')
        ->call('sendPasswordResetLink')
        ->assertHasErrors(['email' => 'email']);
});

test('completes request even when email does not exist', function () {
    Http::fake([
        'https://api.test/auth/request-reset-link' => Http::response([
            'message' => 'Email not found',
        ], 404),
    ]);

    Livewire::test(ForgotPassword::class)
        ->set('email', 'nonexistent@example.com')
        ->call('sendPasswordResetLink')
        ->assertHasNoErrors();
});

test('completes request even when API errors occur', function () {
    Http::fake([
        'https://api.test/auth/request-reset-link' => Http::response(null, 500),
    ]);

    Livewire::test(ForgotPassword::class)
        ->set('email', 'test@example.com')
        ->call('sendPasswordResetLink')
        ->assertHasNoErrors();
});

test('accepts various valid email formats', function ($email) {
    Http::fake([
        'https://api.test/auth/request-reset-link' => Http::response(['success' => true], 200),
    ]);

    Livewire::test(ForgotPassword::class)
        ->set('email', $email)
        ->call('sendPasswordResetLink')
        ->assertHasNoErrors();
})->with([
    'simple' => 'test@example.com',
    'subdomain' => 'user@mail.example.com',
    'plus sign' => 'user+tag@example.com',
    'dot in name' => 'first.last@example.com',
    'number in name' => 'user123@example.com',
    'hyphen in domain' => 'test@my-domain.com',
]);

test('email with whitespace fails validation', function () {
    Livewire::test(ForgotPassword::class)
        ->set('email', '  test@example.com  ')
        ->call('sendPasswordResetLink')
        ->assertHasErrors(['email']);
});

test('email field is string type', function () {
    $component = Livewire::test(ForgotPassword::class);

    expect($component->get('email'))->toBeString();
    expect($component->get('email'))->toBe('');
});

test('password reset request completes without exceptions', function () {
    Http::fake([
        'https://api.test/auth/request-reset-link' => Http::response(['success' => true], 200),
    ]);

    expect(fn () => Livewire::test(ForgotPassword::class)
        ->set('email', 'test@example.com')
        ->call('sendPasswordResetLink')
    )->not->toThrow(\Exception::class);
});

test('renders forgot password page', function () {
    Livewire::test(ForgotPassword::class)
        ->assertStatus(200);
});
