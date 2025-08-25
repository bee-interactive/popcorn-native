<?php

namespace App\Services\Cache;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Native\Mobile\Facades\SecureStorage;

class MobileCacheService
{
    // Durées de cache optimisées pour mobile
    private const CACHE_DURATIONS = [
        'tmdb_movie' => 604800,    // 7 jours - données immuables
        'tmdb_show' => 604800,     // 7 jours - données immuables
        'trending' => 10800,       // 3 heures - mise à jour régulière
        'search' => 3600,          // 1 heure - résultats de recherche
        'user_data' => 300,        // 5 minutes - données utilisateur
        'wishlist' => 600,         // 10 minutes - collections
        'api_response' => 1800,    // 30 minutes - réponses API génériques
    ];

    /**
     * Cache intelligent avec fallback offline
     */
    public function remember(string $key, string $type, \Closure $callback, ?int $ttl = null)
    {
        $ttl ??= self::CACHE_DURATIONS[$type] ?? 3600;

        // Tentative de récupération depuis le cache
        $cached = Cache::get($key);

        if ($cached !== null) {
            $this->updateAccessTime($key);

            return $cached;
        }

        // Si online, récupérer et cacher
        if ($this->isOnline()) {
            try {
                $data = $callback();
                Cache::put($key, $data, $ttl);
                $this->storeOfflineBackup($key, $data, $type);

                return $data;
            } catch (\Exception) {
                // Fallback sur données offline si API fail
                return $this->getOfflineBackup($key);
            }
        }

        // Mode offline : utiliser le backup
        return $this->getOfflineBackup($key);
    }

    /**
     * Stockage sécurisé des tokens API
     */
    public function storeSecureToken(string $name, string $token): void
    {
        SecureStorage::set($name, $token);
    }

    /**
     * Récupération sécurisée des tokens
     */
    public function getSecureToken(string $name): ?string
    {
        return SecureStorage::get($name);
    }

    /**
     * Préchargement intelligent basé sur l'usage
     */
    public function prefetch(array $keys): void
    {
        foreach ($keys as $key => $callback) {
            if (! Cache::has($key)) {
                dispatch(function () use ($key, $callback): void {
                    $this->remember($key, 'api_response', $callback);
                })->afterResponse();
            }
        }
    }

    /**
     * Backup offline dans une table SQLite dédiée
     */
    private function storeOfflineBackup(string $key, mixed $data, string $type): void
    {
        if (! \Schema::hasTable('offline_cache')) {
            return;
        }

        DB::table('offline_cache')->updateOrInsert(
            ['key' => $key],
            [
                'type' => $type,
                'data' => json_encode($data),
                'expires_at' => now()->addSeconds(self::CACHE_DURATIONS[$type] ?? 3600),
                'updated_at' => now(),
            ]
        );
    }

    /**
     * Récupération depuis le backup offline
     */
    private function getOfflineBackup(string $key): mixed
    {
        if (! \Schema::hasTable('offline_cache')) {
            return null;
        }

        $backup = DB::table('offline_cache')
            ->where('key', $key)
            ->where('expires_at', '>', now())
            ->first();

        return $backup ? json_decode((string) $backup->data, true) : null;
    }

    /**
     * Vérification de la connectivité
     */
    private function isOnline(): bool
    {
        // Simple check - peut être amélioré avec ping API
        return Cache::get('app.online_status', true);
    }

    /**
     * Track des accès pour cache LRU
     */
    private function updateAccessTime(string $key): void
    {
        if (! \Schema::hasTable('cache_analytics')) {
            return;
        }

        $existing = DB::table('cache_analytics')->where('key', $key)->first();

        if ($existing) {
            DB::table('cache_analytics')
                ->where('key', $key)
                ->update([
                    'access_count' => $existing->access_count + 1,
                    'last_accessed_at' => now(),
                    'updated_at' => now(),
                ]);
        } else {
            DB::table('cache_analytics')->insert([
                'key' => $key,
                'access_count' => 1,
                'last_accessed_at' => now(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    /**
     * Nettoyage intelligent du cache (garde les plus utilisés)
     */
    public function smartCleanup(int $maxSizeMb = 100): void
    {
        $currentSize = $this->getCacheSizeInMb();

        if ($currentSize > $maxSizeMb) {
            // Supprimer les moins utilisés
            $keysToDelete = DB::table('cache_analytics')
                ->orderBy('last_accessed_at', 'asc')
                ->orderBy('access_count', 'asc')
                ->limit(100)
                ->pluck('key');

            foreach ($keysToDelete as $key) {
                Cache::forget($key);
                DB::table('offline_cache')->where('key', $key)->delete();
            }
        }
    }

    private function getCacheSizeInMb(): float
    {
        $size = DB::table('offline_cache')
            ->sum(DB::raw('LENGTH(data)'));

        return $size / 1048576; // Convert to MB
    }

    public function invalidatePattern(string $pattern): void
    {
        $pattern = str_replace('*', '%', $pattern);

        if (\Schema::hasTable('cache_analytics')) {
            $keysToDelete = DB::table('cache_analytics')
                ->where('key', 'like', $pattern)
                ->pluck('key');

            foreach ($keysToDelete as $key) {
                Cache::forget($key);
                if (\Schema::hasTable('offline_cache')) {
                    DB::table('offline_cache')->where('key', $key)->delete();
                }
                DB::table('cache_analytics')->where('key', $key)->delete();
            }
        }

        if (\Schema::hasTable('offline_cache')) {
            $keysToDelete = DB::table('offline_cache')
                ->where('key', 'like', $pattern)
                ->pluck('key');

            foreach ($keysToDelete as $key) {
                Cache::forget($key);
                DB::table('offline_cache')->where('key', $key)->delete();
            }
        }

        if (\Schema::hasTable('cache')) {
            $keysToDelete = DB::table('cache')
                ->where('key', 'like', $pattern)
                ->orWhere('key', 'like', 'laravel_cache_'.$pattern)
                ->pluck('key');

            foreach ($keysToDelete as $key) {
                $cleanKey = str_replace('laravel_cache_', '', $key);
                Cache::forget($cleanKey);
            }
        }
    }
}
