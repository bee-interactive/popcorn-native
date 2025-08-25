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
        $placeholderPath = self::CACHE_PATH.'/placeholders/placeholder_'.$size.'.jpg';

        if (! Storage::disk(self::CACHE_DISK)->exists($placeholderPath)) {
            $this->generatePlaceholder($size, $placeholderPath);
        }

        return Storage::disk(self::CACHE_DISK)->path($placeholderPath);
    }

    /**
     * Générer une image placeholder
     */
    private function generatePlaceholder(string $size, string $path): void
    {
        $dimensions = $this->getDimensions($size);

        // Créer une image grise simple
        $image = imagecreatetruecolor($dimensions['width'], $dimensions['height']);
        $gray = imagecolorallocate($image, 200, 200, 200);
        imagefill($image, 0, 0, $gray);

        // Ajouter du texte
        $textColor = imagecolorallocate($image, 150, 150, 150);
        $text = 'No Image';
        $fontSize = 5;
        $textWidth = imagefontwidth($fontSize) * strlen($text);
        $textHeight = imagefontheight($fontSize);
        $x = ($dimensions['width'] - $textWidth) / 2;
        $y = ($dimensions['height'] - $textHeight) / 2;
        imagestring($image, $fontSize, $x, $y, $text, $textColor);

        // Sauvegarder
        $fullPath = Storage::disk(self::CACHE_DISK)->path($path);
        $dir = dirname($fullPath);
        if (! file_exists($dir)) {
            mkdir($dir, 0755, true);
        }

        imagejpeg($image, $fullPath, 90);
        imagedestroy($image);
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
