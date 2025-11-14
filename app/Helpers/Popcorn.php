<?php

namespace App\Helpers;

use App\Services\Cache\MobileCacheService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class Popcorn
{
    private static ?MobileCacheService $cacheService = null;

    private static function getCacheService(): MobileCacheService
    {
        if (! self::$cacheService instanceof \App\Services\Cache\MobileCacheService) {
            self::$cacheService = app(MobileCacheService::class);
        }

        return self::$cacheService;
    }

    private static function getToken(): string
    {
        // Priorité : session > secure storage
        if (session('app-access-token')) {
            return session('app-access-token');
        }

        // Fallback sur le stockage sécurisé natif
        $token = self::getCacheService()->getSecureToken('api_token');

        if (! $token) {
            throw new \RuntimeException(
                'No API token found. User must be authenticated. '.
                'Please ensure the user is logged in before making API requests.'
            );
        }

        return $token;
    }

    public static function get(string $url = '', $token = null, $params = null, $useCache = true)
    {
        $token ??= self::getToken();
        $fullUrl = config('services.api.url').$url;

        // Déterminer le type de cache selon l'URL
        $cacheType = self::determineCacheType($url);
        $cacheKey = 'popcorn.get.'.md5($fullUrl.serialize($params));

        // Si le cache est activé, utiliser MobileCacheService
        if ($useCache) {
            return self::getCacheService()->remember(
                $cacheKey,
                $cacheType,
                function () use ($fullUrl, $token, $params) {
                    try {
                        $response = Http::acceptJson()
                            ->withToken($token)
                            ->timeout(10)
                            ->retry(3, 100)
                            ->get($fullUrl, $params);

                        if ($response->successful()) {
                            $data = json_decode($response->body());

                            return collect($data);
                        }

                        return collect([]);
                    } catch (\Exception $e) {
                        Log::error('Popcorn API exception', [
                            'url' => $fullUrl,
                            'error' => $e->getMessage(),
                        ]);

                        return collect([]);
                    }
                }
            );
        }

        $response = Http::acceptJson()->withToken($token)->get($fullUrl, $params);
        $data = json_decode($response->body());

        return collect($data);
    }

    public static function post(string $url, $params = null, $queueSync = false)
    {
        $token = self::getToken();
        $fullUrl = config('services.api.url').$url;

        if ($queueSync && ! self::isOnline()) {
            try {
                self::queueForSync('POST', $url, $params);

                return collect([
                    'success' => true,
                    'queued' => true,
                    'message' => 'Will sync when online',
                ]);
            } catch (\RuntimeException $e) {
                return collect([
                    'success' => false,
                    'queued' => false,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        try {
            $response = Http::acceptJson()
                ->withToken($token)
                ->timeout(10)
                ->retry(3, 100)
                ->post($fullUrl, $params);

            $data = json_decode($response->body());

            if ($response->successful()) {
                self::invalidateRelatedCache($url);
            }

            return collect($data);
        } catch (\Exception $e) {
            if ($queueSync) {
                try {
                    self::queueForSync('POST', $url, $params);

                    return collect(['success' => false, 'queued' => true]);
                } catch (\RuntimeException $queueException) {
                    return collect([
                        'success' => false,
                        'queued' => false,
                        'error' => $queueException->getMessage(),
                    ]);
                }
            }

            return collect(['error' => $e->getMessage()]);
        }
    }

    public static function patch(string $url, $params = null, $queueSync = false)
    {
        $token = self::getToken();
        $fullUrl = config('services.api.url').$url;

        if ($queueSync && ! self::isOnline()) {
            try {
                self::queueForSync('PATCH', $url, $params);

                return collect(['success' => true, 'queued' => true]);
            } catch (\RuntimeException $e) {
                return collect([
                    'success' => false,
                    'queued' => false,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        try {
            $response = Http::acceptJson()
                ->withToken($token)
                ->timeout(10)
                ->retry(3, 100)
                ->patch($fullUrl, $params);

            $data = json_decode($response->body());

            if ($response->successful()) {
                self::invalidateRelatedCache($url);
            }

            return collect($data);
        } catch (\Exception $e) {
            if ($queueSync) {
                try {
                    self::queueForSync('PATCH', $url, $params);

                    return collect(['success' => false, 'queued' => true]);
                } catch (\RuntimeException $queueException) {
                    return collect([
                        'success' => false,
                        'queued' => false,
                        'error' => $queueException->getMessage(),
                    ]);
                }
            }

            return collect(['error' => $e->getMessage()]);
        }
    }

    public static function delete(string $url, $params = null, $queueSync = false)
    {
        $token = self::getToken();
        $fullUrl = config('services.api.url').$url;

        if ($queueSync && ! self::isOnline()) {
            try {
                self::queueForSync('DELETE', $url, $params);

                return collect(['success' => true, 'queued' => true]);
            } catch (\RuntimeException $e) {
                return collect([
                    'success' => false,
                    'queued' => false,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        try {
            $response = Http::acceptJson()
                ->withToken($token)
                ->timeout(10)
                ->retry(3, 100)
                ->delete($fullUrl, $params);

            $data = json_decode($response->body());

            if ($response->successful()) {
                self::invalidateRelatedCache($url);
            }

            return collect($data);
        } catch (\Exception $e) {
            if ($queueSync) {
                try {
                    self::queueForSync('DELETE', $url, $params);

                    return collect(['success' => false, 'queued' => true]);
                } catch (\RuntimeException $queueException) {
                    return collect([
                        'success' => false,
                        'queued' => false,
                        'error' => $queueException->getMessage(),
                    ]);
                }
            }

            return collect(['error' => $e->getMessage()]);
        }
    }

    public static function postWithFile(string $url, $fileFieldName, $file, $fileName = null, $extraParams = [])
    {
        $token = self::getToken();

        // Validation de sécurité
        self::validateUploadedFile($file);

        $fileContents = is_string($file)
            ? file_get_contents($file)
            : file_get_contents($file->getRealPath());

        $name = $fileName ?? (is_string($file) ? basename($file) : $file->getClientOriginalName());

        $response = Http::acceptJson()
            ->withToken($token)
            ->attach($fileFieldName, $fileContents, $name)
            ->post(config('services.api.url').$url, $extraParams);

        $data = json_decode($response->body());

        return collect($data);
    }

    /**
     * Validate uploaded file for security
     *
     * @throws \InvalidArgumentException
     */
    private static function validateUploadedFile($file): void
    {
        // Taille maximale: 5MB
        $maxSize = 5 * 1024 * 1024;

        // Types MIME autorisés (images uniquement)
        $allowedMimes = [
            'image/jpeg',
            'image/jpg',
            'image/png',
            'image/gif',
            'image/webp',
        ];

        // Extensions autorisées
        $allowedExtensions = ['jpg', 'jpeg', 'png', 'gif', 'webp'];

        // Vérification de la taille
        if (is_object($file) && method_exists($file, 'getSize')) {
            $fileSize = $file->getSize();

            if ($fileSize > $maxSize) {
                throw new \InvalidArgumentException(
                    "File size ({$fileSize} bytes) exceeds maximum allowed size ({$maxSize} bytes / 5MB)."
                );
            }
        } elseif (is_string($file) && file_exists($file)) {
            $fileSize = filesize($file);

            if ($fileSize > $maxSize) {
                throw new \InvalidArgumentException(
                    "File size ({$fileSize} bytes) exceeds maximum allowed size ({$maxSize} bytes / 5MB)."
                );
            }
        }

        // Vérification du type MIME
        if (is_object($file) && method_exists($file, 'getMimeType')) {
            $mimeType = $file->getMimeType();

            if (! in_array($mimeType, $allowedMimes)) {
                throw new \InvalidArgumentException(
                    "File type '{$mimeType}' is not allowed. Allowed types: ".implode(', ', $allowedMimes)
                );
            }
        } elseif (is_string($file) && file_exists($file)) {
            $mimeType = mime_content_type($file);

            if (! in_array($mimeType, $allowedMimes)) {
                throw new \InvalidArgumentException(
                    "File type '{$mimeType}' is not allowed. Allowed types: ".implode(', ', $allowedMimes)
                );
            }
        }

        // Vérification de l'extension
        if (is_object($file) && method_exists($file, 'getClientOriginalExtension')) {
            $extension = strtolower($file->getClientOriginalExtension());

            if (! in_array($extension, $allowedExtensions)) {
                throw new \InvalidArgumentException(
                    "File extension '{$extension}' is not allowed. Allowed extensions: ".implode(', ', $allowedExtensions)
                );
            }
        } elseif (is_string($file)) {
            $extension = strtolower(pathinfo($file, PATHINFO_EXTENSION));

            if (! in_array($extension, $allowedExtensions)) {
                throw new \InvalidArgumentException(
                    "File extension '{$extension}' is not allowed. Allowed extensions: ".implode(', ', $allowedExtensions)
                );
            }
        }
    }

    /**
     * Déterminer le type de cache selon l'URL
     */
    private static function determineCacheType(string $url): string
    {
        if (str_contains($url, '/users/')) {
            return 'user_data';
        }
        if (str_contains($url, '/wishlists/')) {
            return 'wishlist';
        }
        if (str_contains($url, '/items/')) {
            return 'api_response';
        }
        if (str_contains($url, '/trending')) {
            return 'trending';
        }

        return 'api_response';
    }

    /**
     * Invalider le cache lié après une modification
     */
    private static function invalidateRelatedCache(string $url): void
    {
        $cacheService = self::getCacheService();
        $apiUrl = config('services.api.url');

        if (str_contains($url, 'wishlists')) {
            cache()->forget('popcorn.get.'.md5($apiUrl.'wishlists'.serialize(null)));
            cache()->forget('popcorn.get.'.md5($apiUrl.'/wishlists'.serialize(null)));

            $wishlistId = self::extractIdFromUrl($url, 'wishlists');
            if ($wishlistId !== null && $wishlistId !== '' && $wishlistId !== '0') {
                cache()->forget('popcorn.get.'.md5($apiUrl."wishlists/{$wishlistId}".serialize(null)));
                cache()->forget('popcorn.get.'.md5($apiUrl."wishlists/{$wishlistId}/items".serialize(null)));
            }

            cache()->forget('popcorn.get.'.md5($apiUrl.'items'.serialize(null)));
            cache()->forget('popcorn.get.'.md5($apiUrl.'/items'.serialize(null)));
            cache()->forget('popcorn.get.'.md5($apiUrl.'trending'.serialize(null)));
            cache()->forget('popcorn.get.'.md5($apiUrl.'/trending'.serialize(null)));

            $cacheService->invalidatePattern('popcorn.get.*wishlists*');
            $cacheService->invalidatePattern('popcorn.get.*items*');
            $cacheService->invalidatePattern('popcorn.get.*trending*');
        } elseif (str_contains($url, 'items')) {
            cache()->forget('popcorn.get.'.md5($apiUrl.'items'.serialize(null)));
            cache()->forget('popcorn.get.'.md5($apiUrl.'/items'.serialize(null)));

            $itemId = self::extractIdFromUrl($url, 'items');
            if ($itemId !== null && $itemId !== '' && $itemId !== '0') {
                cache()->forget('popcorn.get.'.md5($apiUrl."items/{$itemId}".serialize(null)));
            }

            $cacheService->invalidatePattern('popcorn.get.*items*');
            $cacheService->invalidatePattern('popcorn.get.*wishlists/*');

            $wishlists = cache()->get('popcorn.get.'.md5($apiUrl.'wishlists'.serialize(null)));
            if ($wishlists instanceof \Illuminate\Support\Collection && $wishlists->has('data')) {
                foreach ($wishlists->get('data') as $wishlist) {
                    if (isset($wishlist->uuid)) {
                        cache()->forget('popcorn.get.'.md5($apiUrl.'wishlists/'.$wishlist->uuid.serialize(null)));
                    }
                }
            }
        }
    }

    /**
     * Invalider tout le cache de l'utilisateur lors de la connexion/déconnexion
     */
    public static function invalidateUserCache(): void
    {
        $cacheService = self::getCacheService();

        $cacheService->invalidatePattern('popcorn.get.*');

        cache()->flush();
    }

    /**
     * Ajouter une action à la file de synchronisation
     *
     * @throws \RuntimeException
     */
    private static function queueForSync(string $method, string $url, ?array $params): void
    {
        $maxQueueSize = 1000;

        $pendingCount = DB::table('sync_queue')
            ->where('status', 'pending')
            ->count();

        if ($pendingCount >= $maxQueueSize) {
            Log::warning('Sync queue limit reached', [
                'current_count' => $pendingCount,
                'max_size' => $maxQueueSize,
                'attempted_method' => $method,
                'attempted_url' => $url,
            ]);

            throw new \RuntimeException(
                "Sync queue has reached its maximum size ({$maxQueueSize} items). ".
                'Please sync pending items before adding more.'
            );
        }

        DB::table('sync_queue')->insert([
            'type' => strtolower($method).'_request',
            'payload' => json_encode([
                'method' => $method,
                'url' => $url,
                'params' => $params,
            ]),
            'status' => 'pending',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        if ($pendingCount > ($maxQueueSize * 0.8)) {
            Log::warning('Sync queue is approaching limit', [
                'current_count' => $pendingCount + 1,
                'max_size' => $maxQueueSize,
                'percentage' => round((($pendingCount + 1) / $maxQueueSize) * 100, 2),
            ]);
        }
    }

    /**
     * Vérifier si l'app est online
     */
    private static function isOnline(): bool
    {
        return cache()->get('app.online_status', true);
    }

    /**
     * Extraire l'ID depuis une URL
     */
    private static function extractIdFromUrl(string $url, string $resource): ?string
    {
        if (preg_match("/{$resource}\/([^\/]+)/", $url, $matches)) {
            return $matches[1];
        }

        return null;
    }

    /**
     * Précharger des données en arrière-plan
     */
    public static function prefetch(array $urls): void
    {
        foreach ($urls as $url) {
            dispatch(function () use ($url): void {
                self::get($url, null, null, true);
            })->afterResponse();
        }
    }
}
