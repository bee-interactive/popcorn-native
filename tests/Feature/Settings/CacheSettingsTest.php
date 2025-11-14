<?php

use App\Helpers\Popcorn;
use App\Livewire\Settings\Cache;
use Illuminate\Support\Facades\Cache as CacheFacade;
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

    CacheFacade::flush();
});

it('can access cache settings page', function () {
    $response = $this->get(route('settings.cache'));

    $response->assertOk();
    $response->assertSee('Cache');
    $response->assertSee('Manage your application cache');
});

it('displays cache information on the settings page', function () {
    $response = $this->get(route('settings.cache'));

    $response->assertSee('The cache stores temporary data to make the app faster');
    $response->assertSee('Clearing the cache will remove all temporarily stored data including');
    $response->assertSee('Search results');
    $response->assertSee('Trending content');
    $response->assertSee('Movie and TV show details');
    $response->assertSee('Wishlist data');
    $response->assertSee('User profiles');
    $response->assertSee('This will not log you out');
});

it('can clear cache using the button', function () {
    $apiUrl = config('services.api.url');

    Http::fake([
        $apiUrl.'items' => Http::response(['data' => [
            ['id' => 1, 'name' => 'Cached Item'],
        ]], 200),
    ]);

    Popcorn::get('items');

    $cacheKey = 'popcorn.get.'.md5($apiUrl.'items'.serialize(null));
    expect(CacheFacade::get($cacheKey))->not->toBeNull();

    Livewire::test(Cache::class)
        ->call('clearCache');

    expect(CacheFacade::get($cacheKey))->toBeNull();
});

it('clears all cache patterns when clear cache is called', function () {
    CacheFacade::put('popcorn.get.test1', 'value1', 3600);
    CacheFacade::put('popcorn.get.test2', 'value2', 3600);
    CacheFacade::put('other.cache.key', 'value3', 3600);

    expect(CacheFacade::get('popcorn.get.test1'))->toBe('value1');
    expect(CacheFacade::get('popcorn.get.test2'))->toBe('value2');
    expect(CacheFacade::get('other.cache.key'))->toBe('value3');

    Livewire::test(Cache::class)
        ->call('clearCache');

    expect(CacheFacade::get('popcorn.get.test1'))->toBeNull();
    expect(CacheFacade::get('popcorn.get.test2'))->toBeNull();
    expect(CacheFacade::get('other.cache.key'))->toBeNull();
});

it('shows clear cache button', function () {
    Livewire::test(Cache::class)
        ->assertSee('Clear Cache');
});

it('displays cache settings in French when language is set to French', function () {
    session(['app-user' => array_merge(session('app-user'), ['language' => 'fr'])]);
    app()->setLocale('fr');

    $response = $this->get(route('settings.cache'));

    $response->assertSee('Gérer le cache de votre application');
    $response->assertSee('Vider le cache');
});

it('cache settings page requires authentication', function () {
    session()->forget(['app-access-token', 'app-user']);

    $response = $this->get(route('settings.cache'));

    $response->assertRedirect(route('login'));
});

it('shows cache tab in settings navigation', function () {
    $response = $this->get(route('settings.profile'));

    $response->assertSee('Cache');
    $response->assertSee('settings/cache');
});

it('cache tab appears in settings navigation order', function () {
    $response = $this->get(route('settings.profile'));

    $content = $response->getContent();

    expect($content)->toContain('settings/profile');
    expect($content)->toContain('settings/password');
    expect($content)->toContain('settings/the-movie-database-token');
    expect($content)->toContain('settings/appearance');
    expect($content)->toContain('settings/cache');
});
