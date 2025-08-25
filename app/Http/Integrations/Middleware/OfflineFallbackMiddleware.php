<?php

namespace App\Http\Integrations\Middleware;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Saloon\Contracts\Middleware;
use Saloon\Contracts\Request;
use Saloon\Contracts\Response;
use Saloon\Enums\PipeOrder;

class OfflineFallbackMiddleware implements Middleware
{
    public function __invoke(Request $request, callable $next): Response
    {
        try {
            // Vérifier si on est online
            if (! $this->isOnline()) {
                // Tenter de récupérer depuis le cache offline
                return $this->getOfflineResponse($request);
            }

            // Exécuter la requête normale
            $response = $next($request);

            // Si succès, sauvegarder pour usage offline
            if ($response->successful()) {
                $this->storeForOffline($request, $response);
            }

            return $response;

        } catch (\Exception) {
            // En cas d'erreur réseau, tenter le fallback offline
            return $this->getOfflineResponse($request);
        }
    }

    private function isOnline(): bool
    {
        // Check simple de connectivité
        $onlineStatus = Cache::get('app.online_status');

        if ($onlineStatus === null) {
            // Tester la connectivité avec un ping léger
            try {
                $ch = curl_init('https://api.themoviedb.org/3/configuration');
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                curl_setopt($ch, CURLOPT_TIMEOUT, 2);
                curl_setopt($ch, CURLOPT_NOBODY, true);
                curl_exec($ch);
                $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                curl_close($ch);

                $isOnline = $httpCode > 0;
                Cache::put('app.online_status', $isOnline, 30); // Cache for 30 seconds

                return $isOnline;
            } catch (\Exception) {
                Cache::put('app.online_status', false, 30);

                return false;
            }
        }

        return $onlineStatus;
    }

    private function getOfflineResponse(Request $request): Response
    {
        $cacheKey = $this->getCacheKey($request);

        // Chercher dans le cache offline
        $offlineData = DB::table('offline_cache')
            ->where('key', $cacheKey)
            ->first();

        if ($offlineData) {
            $data = json_decode((string) $offlineData->data, true);

            return new CachedResponse(
                $data['body'] ?? '{}',
                200,
                ['X-Offline-Mode' => 'true']
            );
        }

        // Si aucune donnée offline, retourner une erreur
        return new CachedResponse(
            json_encode(['error' => 'No offline data available']),
            503,
            ['X-Offline-Mode' => 'true', 'X-Error' => 'No cached data']
        );
    }

    private function storeForOffline(Request $request, Response $response): void
    {
        $cacheKey = $this->getCacheKey($request);

        DB::table('offline_cache')->updateOrInsert(
            ['key' => $cacheKey],
            [
                'type' => 'saloon_response',
                'data' => json_encode([
                    'body' => $response->body(),
                    'status' => $response->status(),
                    'headers' => $response->headers()->all(),
                ]),
                'expires_at' => now()->addDays(7),
                'updated_at' => now(),
            ]
        );
    }

    private function getCacheKey(Request $request): string
    {
        return 'offline.saloon.'.md5($request->resolveEndpoint().serialize($request->query()->all()));
    }

    public function priority(): PipeOrder
    {
        return PipeOrder::FIRST;
    }
}
