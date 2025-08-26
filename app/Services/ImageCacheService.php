<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Spatie\Image\Enums\Fit;
use Spatie\Image\Image;

class ImageCacheService
{
    private const TMDB_IMAGE_BASE = 'https://image.tmdb.org/t/p/';

    private const CACHE_DISK = 'local';

    private const CACHE_PATH = 'cached-images';

    /**
     * Obtenir une image cachée ou la télécharger
     */
    public function getCachedImage(string $tmdbPath, string $size = 'w500'): string
    {
        if ($tmdbPath === '' || $tmdbPath === '0') {
            return $this->getPlaceholder($size);
        }

        $filename = $this->generateFilename($tmdbPath, $size);
        $cachePath = self::CACHE_PATH.'/'.$filename;

        // Vérifier si l'image est déjà cachée
        if (Storage::disk(self::CACHE_DISK)->exists($cachePath)) {
            return Storage::disk(self::CACHE_DISK)->path($cachePath);
        }

        // Télécharger et optimiser l'image
        return $this->downloadAndOptimize($tmdbPath, $size, $cachePath);
    }

    /**
     * Précharger plusieurs images en arrière-plan
     */
    public function preloadImages(array $tmdbPaths, string $size = 'w500'): void
    {
        foreach ($tmdbPaths as $path) {
            if (empty($path)) {
                continue;
            }

            dispatch(function () use ($path, $size): void {
                $this->getCachedImage($path, $size);
            })->afterResponse()->onQueue('low');
        }
    }

    /**
     * Télécharger et optimiser une image
     */
    private function downloadAndOptimize(string $tmdbPath, string $size, string $cachePath): string
    {
        try {
            $url = self::TMDB_IMAGE_BASE.$size.$tmdbPath;

            // Télécharger l'image
            $response = Http::timeout(10)->get($url);

            if (! $response->successful()) {
                Log::warning('Failed to download image', ['url' => $url]);

                return $this->getPlaceholder($size);
            }

            // Sauvegarder temporairement
            $tempPath = storage_path('app/temp/'.uniqid().'.jpg');
            file_put_contents($tempPath, $response->body());

            // Optimiser avec Spatie Image
            $optimizedPath = Storage::disk(self::CACHE_DISK)->path($cachePath);

            // Créer le répertoire si nécessaire
            $dir = dirname($optimizedPath);
            if (! file_exists($dir)) {
                mkdir($dir, 0755, true);
            }

            // Optimiser selon la taille
            $dimensions = $this->getDimensions($size);

            Image::load($tempPath)
                ->fit(Fit::Contain, $dimensions['width'], $dimensions['height'])
                ->optimize()
                ->quality(85)
                ->save($optimizedPath);

            // Nettoyer le fichier temporaire
            @unlink($tempPath);

            Log::info('Image cached successfully', [
                'path' => $tmdbPath,
                'size' => $size,
                'cache' => $cachePath,
            ]);

            return $optimizedPath;

        } catch (\Exception $e) {
            Log::error('Failed to cache image', [
                'path' => $tmdbPath,
                'error' => $e->getMessage(),
            ]);

            return $this->getPlaceholder($size);
        }
    }

    /**
     * Générer un nom de fichier unique pour le cache
     */
    private function generateFilename(string $tmdbPath, string $size): string
    {
        $extension = pathinfo($tmdbPath, PATHINFO_EXTENSION) !== [] && ! in_array(pathinfo($tmdbPath, PATHINFO_EXTENSION), ['', '0'], true) ? pathinfo($tmdbPath, PATHINFO_EXTENSION) : 'jpg';
        $hash = md5($tmdbPath.$size);

        // Organiser par les 2 premiers caractères du hash pour éviter trop de fichiers dans un dossier
        return substr($hash, 0, 2).'/'.$hash.'.'.$extension;
    }

    /**
     * Obtenir les dimensions selon la taille TMDB
     */
    private function getDimensions(string $size): array
    {
        return match ($size) {
            'w92' => ['width' => 92, 'height' => 138],
            'w154' => ['width' => 154, 'height' => 231],
            'w185' => ['width' => 185, 'height' => 278],
            'w342' => ['width' => 342, 'height' => 513],
            'w500' => ['width' => 500, 'height' => 750],
            'w780' => ['width' => 780, 'height' => 1170],
            'original' => ['width' => 2000, 'height' => 3000],
            default => ['width' => 500, 'height' => 750],
        };
    }

    /**
     * Obtenir une image placeholder
     */
    private function getPlaceholder(string $size): string
    {
        // Check for existing placeholder in different formats
        $basePath = self::CACHE_PATH.'/placeholders/placeholder_'.$size;
        $extensions = ['.jpg', '.png', '.gif'];
        
        foreach ($extensions as $ext) {
            $placeholderPath = $basePath . $ext;
            if (Storage::disk(self::CACHE_DISK)->exists($placeholderPath)) {
                return Storage::disk(self::CACHE_DISK)->path($placeholderPath);
            }
        }
        
        // Generate new placeholder if none exists
        $placeholderPath = $basePath . '.jpg'; // Default to .jpg path
        $this->generatePlaceholder($size, $placeholderPath);
        
        // Check again for any format that was actually created
        foreach ($extensions as $ext) {
            $checkPath = $basePath . $ext;
            if (Storage::disk(self::CACHE_DISK)->exists($checkPath)) {
                return Storage::disk(self::CACHE_DISK)->path($checkPath);
            }
        }
        
        // This shouldn't happen, but return the original path as fallback
        return Storage::disk(self::CACHE_DISK)->path($placeholderPath);
    }

    /**
     * Générer une image placeholder
     */
    private function generatePlaceholder(string $size, string $path): void
    {
        $dimensions = $this->getDimensions($size);
        $fullPath = Storage::disk(self::CACHE_DISK)->path($path);
        $dir = dirname($fullPath);
        
        if (! file_exists($dir)) {
            mkdir($dir, 0755, true);
        }

        // Check if GD extension is loaded
        if (extension_loaded('gd')) {
            try {
                // Check which image format functions are available
                $canUseJpeg = function_exists('imagejpeg');
                $canUsePng = function_exists('imagepng');
                $canUseGif = function_exists('imagegif');
                
                if (!$canUseJpeg && !$canUsePng && !$canUseGif) {
                    throw new \Exception('No image output functions available');
                }

                // Créer une image grise simple
                $image = \imagecreatetruecolor($dimensions['width'], $dimensions['height']);
                $gray = \imagecolorallocate($image, 200, 200, 200);
                \imagefill($image, 0, 0, $gray);

                // Ajouter du texte
                $textColor = \imagecolorallocate($image, 150, 150, 150);
                $text = 'No Image';
                $fontSize = 5;
                $textWidth = \imagefontwidth($fontSize) * strlen($text);
                $textHeight = \imagefontheight($fontSize);
                $x = ($dimensions['width'] - $textWidth) / 2;
                $y = ($dimensions['height'] - $textHeight) / 2;
                \imagestring($image, $fontSize, $x, $y, $text, $textColor);

                // Sauvegarder avec le format disponible
                if ($canUseJpeg) {
                    \imagejpeg($image, $fullPath, 90);
                } elseif ($canUsePng) {
                    // Change extension to PNG if needed
                    $fullPath = str_replace('.jpg', '.png', $fullPath);
                    \imagepng($image, $fullPath, 9);
                } elseif ($canUseGif) {
                    // Change extension to GIF if needed
                    $fullPath = str_replace('.jpg', '.gif', $fullPath);
                    \imagegif($image, $fullPath);
                }
                
                \imagedestroy($image);
                return;
            } catch (\Exception $e) {
                Log::warning('Failed to generate placeholder with GD: ' . $e->getMessage());
                // Fall through to fallback method
            }
        }

        // Fallback: Create an SVG placeholder and save it as a file
        $svgContent = $this->generateSvgPlaceholder($dimensions['width'], $dimensions['height']);
        file_put_contents($fullPath, $svgContent);
    }

    /**
     * Generate SVG placeholder content
     */
    private function generateSvgPlaceholder(int $width, int $height): string
    {
        // Create a simple SVG that looks like a JPEG when saved
        // We'll create a data URI of a 1x1 gray pixel and save it as JPEG data
        $grayPixel = base64_decode('/9j/4AAQSkZJRgABAQEAYABgAAD/2wBDAAEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQH/2wBDAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQH/wAARCAABAAEDASIAAhEBAxEB/8QAFQABAQAAAAAAAAAAAAAAAAAAAAv/xAAUEAEAAAAAAAAAAAAAAAAAAAAA/8QAFQEBAQAAAAAAAAAAAAAAAAAAAAX/xAAUEQEAAAAAAAAAAAAAAAAAAAAA/9oADAMBAAIRAxEAPwCwAAA//9k=');
        
        // Return the gray pixel JPEG data
        // This is a valid JPEG file that will work as a placeholder
        return $grayPixel;
    }

    /**
     * Get diagnostic info about available image functions
     */
    public function getImageSupportInfo(): array
    {
        return [
            'gd_extension' => extension_loaded('gd'),
            'gd_info' => extension_loaded('gd') ? gd_info() : null,
            'functions' => [
                'imagecreatetruecolor' => function_exists('imagecreatetruecolor'),
                'imagejpeg' => function_exists('imagejpeg'),
                'imagepng' => function_exists('imagepng'),
                'imagegif' => function_exists('imagegif'),
                'imagewbmp' => function_exists('imagewbmp'),
                'imagewebp' => function_exists('imagewebp'),
            ],
        ];
    }

    /**
     * Nettoyer les images cachées anciennes
     */
    public function cleanupOldImages(int $daysOld = 30): int
    {
        $deleted = 0;
        $cutoffTime = now()->subDays($daysOld)->timestamp;

        $files = Storage::disk(self::CACHE_DISK)->allFiles(self::CACHE_PATH);

        foreach ($files as $file) {
            $lastModified = Storage::disk(self::CACHE_DISK)->lastModified($file);

            if ($lastModified < $cutoffTime && ! str_contains($file, 'placeholder')) {
                Storage::disk(self::CACHE_DISK)->delete($file);
                $deleted++;
            }
        }

        Log::info("Cleaned up {$deleted} old cached images");

        return $deleted;
    }

    /**
     * Obtenir la taille totale du cache d'images
     */
    public function getCacheSizeInMb(): float
    {
        $totalSize = 0;
        $files = Storage::disk(self::CACHE_DISK)->allFiles(self::CACHE_PATH);

        foreach ($files as $file) {
            $totalSize += Storage::disk(self::CACHE_DISK)->size($file);
        }

        return round($totalSize / 1048576, 2); // Convert to MB
    }
}
