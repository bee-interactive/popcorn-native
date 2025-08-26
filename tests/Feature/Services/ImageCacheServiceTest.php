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

    test('generates placeholder image with available method', function () {
        Storage::fake('local');
        
        $service = new ImageCacheService;
        
        // Get placeholder which will trigger generation
        $result = $service->getCachedImage('', 'w500');
        
        expect($result)->toContain('placeholder');
        
        // Check what type of file was created
        $jpegPath = 'cached-images/placeholders/placeholder_w500.jpg';
        $pngPath = 'cached-images/placeholders/placeholder_w500.png';
        $gifPath = 'cached-images/placeholders/placeholder_w500.gif';
        
        // At least one format should exist
        $jpegExists = Storage::disk('local')->exists($jpegPath);
        $pngExists = Storage::disk('local')->exists($pngPath);
        $gifExists = Storage::disk('local')->exists($gifPath);
        
        expect($jpegExists || $pngExists || $gifExists)->toBeTrue();
        
        // If GD is available with any format, verify the file
        if (extension_loaded('gd')) {
            if ($jpegExists) {
                $path = Storage::disk('local')->path($jpegPath);
                expect(file_exists($path))->toBeTrue();
            } elseif ($pngExists) {
                $path = Storage::disk('local')->path($pngPath);
                expect(file_exists($path))->toBeTrue();
            } elseif ($gifExists) {
                $path = Storage::disk('local')->path($gifPath);
                expect(file_exists($path))->toBeTrue();
            }
        } else {
            // Fallback was used - should create a JPEG with pre-generated data
            expect($jpegExists)->toBeTrue();
        }
    });
    
    test('reuses existing placeholder in different formats', function () {
        Storage::fake('local');
        
        // Create a PNG placeholder manually
        $pngPath = 'cached-images/placeholders/placeholder_w500.png';
        Storage::disk('local')->put($pngPath, 'fake-png-data');
        
        $service = new ImageCacheService;
        $result = $service->getCachedImage('', 'w500');
        
        // Should find and use the existing PNG
        expect($result)->toContain('placeholder_w500');
        
        // Should not create additional formats
        $jpegPath = 'cached-images/placeholders/placeholder_w500.jpg';
        $gifPath = 'cached-images/placeholders/placeholder_w500.gif';
        
        expect(Storage::disk('local')->exists($jpegPath))->toBeFalse();
        expect(Storage::disk('local')->exists($gifPath))->toBeFalse();
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
