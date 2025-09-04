<?php

use App\Livewire\Settings\Account;
use App\Livewire\Settings\DeleteUserForm;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;

beforeEach(function () {
    session(['app-access-token' => 'test-token']);
    session(['app-user' => [
        'uuid' => 'user-123',
        'name' => 'Test User',
        'username' => 'testuser',
        'email' => 'test@example.com',
        'language' => 'en',
        'profile_picture' => null,
        'description' => null,
        'public_profile' => true,
        'tmdb_token' => null,
    ]]);
});

it('can access account settings page', function () {
    $response = $this->get(route('settings.account'));

    $response->assertOk();
    $response->assertSee('Account');
    $response->assertSee('Manage your account settings');
});

it('shows delete account section', function () {
    $response = $this->get(route('settings.account'));

    $response->assertSee('Delete account');
    $response->assertSee('Delete your account and all of its resources');
});

it('account settings page requires authentication', function () {
    session()->forget(['app-access-token', 'app-user']);

    $response = $this->get(route('settings.account'));

    $response->assertRedirect(route('login'));
});

it('shows account tab in settings navigation', function () {
    $response = $this->get(route('settings.profile'));

    $response->assertSee('Account');
    $response->assertSee('settings/account');
});

it('account tab appears after password in navigation', function () {
    $response = $this->get(route('settings.profile'));

    $content = $response->getContent();

    expect($content)->toContain('settings.profile');
    expect($content)->toContain('settings.password');
    expect($content)->toContain('settings.account');

    $passwordPosition = strpos($content, 'settings.password');
    $accountPosition = strpos($content, 'settings.account');
    $tmdbPosition = strpos($content, 'settings.tmdb');

    expect($accountPosition)->toBeGreaterThan($passwordPosition);
    expect($tmdbPosition)->toBeGreaterThan($accountPosition);
});

it('delete account form exists on account page', function () {
    Livewire::test(Account::class)
        ->assertSeeLivewire('settings.delete-user-form');
});

it('can delete account with correct password', function () {
    $apiUrl = config('services.api.url');

    Http::fake([
        $apiUrl.'users/user-123' => Http::response(null, 204),
    ]);

    Livewire::test(DeleteUserForm::class)
        ->set('password', 'correct-password')
        ->call('deleteUser')
        ->assertRedirect('/');
});

it('shows error with incorrect password on delete', function () {
    $apiUrl = config('services.api.url');

    Http::fake([
        $apiUrl.'users/user-123' => Http::response([
            'message' => 'The given data was invalid.',
            'errors' => [
                'password' => ['The password is incorrect.'],
            ],
        ], 422),
    ]);

    Livewire::test(DeleteUserForm::class)
        ->set('password', 'wrong-password')
        ->call('deleteUser')
        ->assertHasErrors(['password' => 'The password is incorrect.'])
        ->assertNoRedirect();
});

it('displays account settings in French when language is set to French', function () {
    session(['app-user' => array_merge(session('app-user'), ['language' => 'fr'])]);
    app()->setLocale('fr');

    $response = $this->get(route('settings.account'));

    $response->assertSee('Compte');
    $response->assertSee('Gérer les paramètres de votre compte');
});
