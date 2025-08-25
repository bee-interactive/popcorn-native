<?php

use App\Helpers\Popcorn;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;

beforeEach(function () {
    // Créer les tables nécessaires si elles n'existent pas
    if (! Schema::hasTable('offline_cache')) {
        Schema::create('offline_cache', function ($table) {
            $table->id();
            $table->string('key')->unique();
            $table->string('type', 50);
            $table->longText('data');
            $table->timestamp('expires_at');
            $table->timestamps();

            $table->index(['type', 'expires_at']);
            $table->index('expires_at');
        });
    }

    if (! Schema::hasTable('cache_analytics')) {
        Schema::create('cache_analytics', function ($table) {
            $table->id();
            $table->string('key')->unique();
            $table->unsignedInteger('access_count')->default(0);
            $table->timestamp('last_accessed_at')->nullable();
            $table->timestamps();

            $table->index(['last_accessed_at', 'access_count']);
        });
    }

    if (! Schema::hasTable('sync_queue')) {
        Schema::create('sync_queue', function ($table) {
            $table->id();
            $table->string('type', 50);
            $table->json('payload');
            $table->string('status', 20)->default('pending');
            $table->unsignedInteger('attempts')->default(0);
            $table->timestamp('next_attempt_at')->nullable();
            $table->text('error_message')->nullable();
            $table->timestamps();

            $table->index(['status', 'next_attempt_at']);
        });
    }

    // Nettoyer avant chaque test
    DB::table('sync_queue')->delete();
    DB::table('offline_cache')->delete();
    DB::table('cache_analytics')->delete();
    Cache::flush();

    // Configurer l'URL de l'API
    config(['services.api.url' => 'https://api.example.com']);
});

describe('Popcorn Helper GET requests', function () {
    test('makes successful GET request with cache', function () {
        // Mock HTTP response
        Http::fake([
            'https://api.example.com/test' => Http::response(['data' => 'test'], 200),
        ]);

        // Premier appel - depuis l'API
        $result = Popcorn::get('/test');

        expect($result)->toBeInstanceOf(\Illuminate\Support\Collection::class);
        expect($result->get('data'))->toBe('test');

        // Vérifier que c'est en cache
        $cacheKey = 'popcorn.get.'.md5('https://api.example.com/test'.serialize(null));
        expect(Cache::has($cacheKey))->toBeTrue();

        // Deuxième appel - depuis le cache
        Http::fake(); // Plus de réponse mock
        $cachedResult = Popcorn::get('/test');

        expect($cachedResult->get('data'))->toBe('test');
    });

    test('handles API errors gracefully', function () {
        Http::fake([
            'https://api.example.com/error' => Http::response(null, 500),
        ]);

        $result = Popcorn::get('/error');

        expect($result)->toBeInstanceOf(\Illuminate\Support\Collection::class);
        expect($result->isEmpty())->toBeTrue();
    });

    test('bypasses cache when requested', function () {
        Http::fake([
            'https://api.example.com/nocache' => Http::sequence()
                ->push(['data' => 'first'], 200)
                ->push(['data' => 'second'], 200),
        ]);

        $result1 = Popcorn::get('/nocache', null, null, false);
        expect($result1->get('data'))->toBe('first');

        $result2 = Popcorn::get('/nocache', null, null, false);
        expect($result2->get('data'))->toBe('second');
    });
});
