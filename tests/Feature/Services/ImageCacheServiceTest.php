<?php

use App\Services\ImageCacheService;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    // Utiliser un disque de test
    Storage::fake('local');
});

describe('ImageCacheService', function () {
    test('returns placeholder for empty path', function () {
        $service = new ImageCacheService;

        $result = $service->getCachedImage('', 'w500');

        expect($result)->toContain('placeholder');
    });

    test('generates correct cache path', function () {
        Storage::fake('local');
        Http::fake([
            'https://image.tmdb.org/t/p/w500/*' => Http::response('fake-image-data', 200),
        ]);

        $service = new ImageCacheService;

        // Test avec un chemin TMDB valide
        $result = $service->getCachedImage('/abc123.jpg', 'w500');

        expect($result)->toBeString();
        expect($result)->toContain('cached-images');
    });

    test('calculates cache size correctly', function () {
        Storage::fake('local');

        $service = new ImageCacheService;

        // Au début, le cache est vide
        $size = $service->getCacheSizeInMb();

        expect($size)->toBe(0.0);

        // Ajouter des fichiers
        Storage::disk('local')->put('cached-images/test1.jpg', str_repeat('a', 1048576)); // 1MB
        Storage::disk('local')->put('cached-images/test2.jpg', str_repeat('b', 524288));  // 0.5MB

        $size = $service->getCacheSizeInMb();

        expect($size)->toBe(1.5);
    });
});
