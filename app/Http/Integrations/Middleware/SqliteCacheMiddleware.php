<?php

namespace App\Http\Integrations\Middleware;

use App\Services\Cache\MobileCacheService;
use Saloon\Contracts\Middleware;
use Saloon\Contracts\Request;
use Saloon\Contracts\Response;
use Saloon\Enums\PipeOrder;

class SqliteCacheMiddleware implements Middleware
{
    public function __construct(
        private readonly int $ttl = 3600,
        private readonly ?string $cacheType = null
    ) {}

    public function __invoke(Request $request, callable $next): Response
    {
        $cacheService = app(MobileCacheService::class);
        $cacheKey = $this->getCacheKey($request);
        $type = $this->cacheType ?? $this->determineCacheType($request);

        // Tenter de récupérer depuis le cache
        $cachedResponse = $cacheService->remember(
            $cacheKey,
            $type,
            function () use ($next, $request): ?array {
                $response = $next($request);

                // Ne cacher que les réponses réussies
                if ($response->successful()) {
                    return [
                        'status' => $response->status(),
                        'headers' => $response->headers()->all(),
                        'body' => $response->body(),
                        'json' => $response->json(),
                        'cached_at' => now()->toIso8601String(),
                    ];
                }

                return null;
            },
            $this->ttl
        );

        // Si on a une réponse cachée, la reconstruire
        if ($cachedResponse && isset($cachedResponse['body'])) {
            return new CachedResponse(
                $cachedResponse['body'],
                $cachedResponse['status'] ?? 200,
                $cachedResponse['headers'] ?? []
            );
        }

        // Sinon, continuer avec la requête normale
        return $next($request);
    }

    private function getCacheKey(Request $request): string
    {
        $url = $request->resolveEndpoint();
        $query = $request->query()->all();
        $headers = $request->headers()->all();

        // Inclure certains headers dans la clé (ex: langue)
        $relevantHeaders = array_intersect_key($headers, [
            'Accept-Language' => true,
            'Authorization' => true,
        ]);

        return 'saloon.'.md5($url.serialize($query).serialize($relevantHeaders));
    }

    private function determineCacheType(Request $request): string
    {
        $endpoint = $request->resolveEndpoint();
        // Déterminer le type de cache selon l'endpoint
        if (str_contains($endpoint, '/movie/') || str_contains($endpoint, '/tv/')) {
            return 'tmdb_movie';
        }
        if (str_contains($endpoint, '/trending/')) {
            return 'trending';
        }

        // Déterminer le type de cache selon l'endpoint
        if (str_contains($endpoint, '/search/')) {
            return 'search';
        }

        return 'api_response';
    }

    public function priority(): PipeOrder
    {
        return PipeOrder::FIRST;
    }
}
