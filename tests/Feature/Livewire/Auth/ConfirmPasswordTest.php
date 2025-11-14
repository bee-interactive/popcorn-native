<?php

use App\Livewire\Auth\ConfirmPassword;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;

uses(RefreshDatabase::class);

test('confirms password successfully with correct password', function () {
    $user = User::factory()->create([
        'password' => Hash::make('correct-password'),
    ]);

    $this->actingAs($user);

    Livewire::test(ConfirmPassword::class)
        ->set('password', 'correct-password')
        ->call('confirmPassword')
        ->assertHasNoErrors()
        ->assertRedirect(route('dashboard'));

    expect(session('auth.password_confirmed_at'))->not->toBeNull();
    expect(session('auth.password_confirmed_at'))->toBeInt();
});

test('rejects incorrect password', function () {
    $user = User::factory()->create([
        'password' => Hash::make('correct-password'),
    ]);

    $this->actingAs($user);

    Livewire::test(ConfirmPassword::class)
        ->set('password', 'wrong-password')
        ->call('confirmPassword')
        ->assertHasErrors(['password']);

    expect(session('auth.password_confirmed_at'))->toBeNull();
});

test('validates password is required', function () {
    $user = User::factory()->create();

    $this->actingAs($user);

    Livewire::test(ConfirmPassword::class)
        ->set('password', '')
        ->call('confirmPassword')
        ->assertHasErrors(['password']);
});

test('stores confirmation timestamp in session', function () {
    $user = User::factory()->create([
        'password' => Hash::make('test-password'),
    ]);

    $this->actingAs($user);

    $beforeTime = time();

    Livewire::test(ConfirmPassword::class)
        ->set('password', 'test-password')
        ->call('confirmPassword');

    $afterTime = time();

    $confirmedAt = session('auth.password_confirmed_at');

    expect($confirmedAt)->toBeGreaterThanOrEqual($beforeTime);
    expect($confirmedAt)->toBeLessThanOrEqual($afterTime);
});

test('redirects to intended destination when set', function () {
    $user = User::factory()->create([
        'password' => Hash::make('test-password'),
    ]);

    $this->actingAs($user);

    session()->put('url.intended', route('settings.profile'));

    Livewire::test(ConfirmPassword::class)
        ->set('password', 'test-password')
        ->call('confirmPassword')
        ->assertRedirect(route('settings.profile'));
});

test('redirects to dashboard by default when no intended destination', function () {
    $user = User::factory()->create([
        'password' => Hash::make('test-password'),
    ]);

    $this->actingAs($user);

    Livewire::test(ConfirmPassword::class)
        ->set('password', 'test-password')
        ->call('confirmPassword')
        ->assertRedirect(route('dashboard'));
});

test('requires authentication to access', function () {
    $response = $this->get(route('password.confirm'));

    $response->assertRedirect(route('login'));
});

test('empty password fails validation', function () {
    $user = User::factory()->create([
        'password' => Hash::make('test-password'),
    ]);

    $this->actingAs($user);

    Livewire::test(ConfirmPassword::class)
        ->set('password', '   ')
        ->call('confirmPassword')
        ->assertHasErrors(['password']);
});

test('renders confirm password page for authenticated users', function () {
    $user = User::factory()->create();

    $this->actingAs($user);

    $response = $this->get(route('password.confirm'));

    $response->assertStatus(200);
    $response->assertSeeLivewire(ConfirmPassword::class);
});

test('does not confirm password without calling confirmPassword method', function () {
    $user = User::factory()->create([
        'password' => Hash::make('test-password'),
    ]);

    $this->actingAs($user);

    Livewire::test(ConfirmPassword::class)
        ->set('password', 'test-password');

    expect(session('auth.password_confirmed_at'))->toBeNull();
});

test('confirmation timestamp is unix timestamp', function () {
    $user = User::factory()->create([
        'password' => Hash::make('test-password'),
    ]);

    $this->actingAs($user);

    Livewire::test(ConfirmPassword::class)
        ->set('password', 'test-password')
        ->call('confirmPassword');

    $timestamp = session('auth.password_confirmed_at');

    expect($timestamp)->toBeInt();
    expect($timestamp)->toBeGreaterThan(1600000000);
    expect($timestamp)->toBeLessThan(2000000000);
});

test('multiple wrong attempts do not lock account', function () {
    $user = User::factory()->create([
        'password' => Hash::make('correct-password'),
    ]);

    $this->actingAs($user);

    for ($i = 0; $i < 5; $i++) {
        Livewire::test(ConfirmPassword::class)
            ->set('password', 'wrong-password')
            ->call('confirmPassword')
            ->assertHasErrors(['password']);
    }

    Livewire::test(ConfirmPassword::class)
        ->set('password', 'correct-password')
        ->call('confirmPassword')
        ->assertHasNoErrors();
});

test('password field is string type', function () {
    $user = User::factory()->create();

    $this->actingAs($user);

    $component = Livewire::test(ConfirmPassword::class);

    expect($component->get('password'))->toBe('');
    expect($component->get('password'))->toBeString();
});

test('uses correct auth guard', function () {
    $user = User::factory()->create([
        'password' => Hash::make('test-password'),
    ]);

    $this->actingAs($user, 'web');

    Livewire::test(ConfirmPassword::class)
        ->set('password', 'test-password')
        ->call('confirmPassword')
        ->assertHasNoErrors();
});
