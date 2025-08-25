<?php

namespace App\Services;

use App\Helpers\Popcorn;
use App\Http\Integrations\Tmdb\Requests\TrendingRequest;
use App\Http\Integrations\Tmdb\TmdbConnector;
use App\Services\Cache\MobileCacheService;
use Illuminate\Support\Facades\Log;

class PrefetchService
{
    public function __construct(
        private readonly MobileCacheService $cacheService,
        private readonly TmdbConnector $tmdbConnector
    ) {}

    /**
     * Précharger les données trending au démarrage
     */
    public function prefetchTrending(): void
    {
        dispatch(function (): void {
            try {
                // Précharger trending movies
                $moviesRequest = new TrendingRequest('movie', 'week');
                $moviesRequest->query()->merge(['page' => 1]);
                $this->tmdbConnector->send($moviesRequest);

                // Précharger trending TV shows
                $tvRequest = new TrendingRequest('tv', 'week');
                $tvRequest->query()->merge(['page' => 1]);
                $this->tmdbConnector->send($tvRequest);

                // Précharger trending all
                $allRequest = new TrendingRequest('all', 'week');
                $allRequest->query()->merge(['page' => 1]);
                $this->tmdbConnector->send($allRequest);

                Log::info('Trending data prefetched successfully');
            } catch (\Exception $e) {
                Log::error('Failed to prefetch trending data', [
                    'error' => $e->getMessage(),
                ]);
            }
        })->afterResponse();
    }

    /**
     * Précharger les détails d'items populaires
     */
    public function prefetchPopularItems(array $tmdbIds, string $mediaType = 'movie'): void
    {
        foreach (array_slice($tmdbIds, 0, 10) as $tmdbId) {
            dispatch(function () use ($tmdbId, $mediaType): void {
                $cacheKey = "tmdb.{$mediaType}.{$tmdbId}";

                // Vérifier si déjà en cache
                if ($this->cacheService->remember($cacheKey, 'tmdb_movie', fn (): null => null)) {
                    return;
                }

                try {
                    // Utiliser l'API TMDB pour récupérer les détails
                    $endpoint = $mediaType === 'movie'
                        ? "/movie/{$tmdbId}"
                        : "/tv/{$tmdbId}";

                    // La requête sera automatiquement cachée via les middlewares
                    $request = new \Saloon\Http\Request(
                        \Saloon\Enums\Method::GET,
                        $endpoint
                    );

                    $this->tmdbConnector->send($request);
                } catch (\Exception $e) {
                    Log::warning("Failed to prefetch {$mediaType} {$tmdbId}", [
                        'error' => $e->getMessage(),
                    ]);
                }
            })->afterResponse()->onQueue('low');
        }
    }

    /**
     * Précharger les films/séries similaires
     */
    public function prefetchSimilar(int $tmdbId, string $mediaType = 'movie'): void
    {
        dispatch(function () use ($tmdbId, $mediaType): void {
            try {
                $endpoint = $mediaType === 'movie'
                    ? "/movie/{$tmdbId}/similar"
                    : "/tv/{$tmdbId}/similar";

                $request = new \Saloon\Http\Request(
                    \Saloon\Enums\Method::GET,
                    $endpoint
                );

                $response = $this->tmdbConnector->send($request);

                if ($response->successful()) {
                    $similar = $response->json('results', []);

                    // Précharger les 5 premiers similaires
                    $similarIds = array_column(array_slice($similar, 0, 5), 'id');
                    $this->prefetchPopularItems($similarIds, $mediaType);
                }
            } catch (\Exception) {
                Log::warning("Failed to prefetch similar for {$mediaType} {$tmdbId}");
            }
        })->afterResponse()->onQueue('low');
    }

    /**
     * Précharger les données utilisateur au login
     */
    public function prefetchUserData(string $username): void
    {
        dispatch(function () use ($username): void {
            try {
                // Précharger le profil utilisateur
                Popcorn::get("/users/{$username}");

                // Précharger les wishlists
                $wishlists = Popcorn::get("/users/{$username}/wishlists");

                // Précharger les premiers items de chaque wishlist
                foreach ($wishlists->take(3) as $wishlist) {
                    if (isset($wishlist->uuid)) {
                        Popcorn::get("/wishlists/{$wishlist->uuid}");
                    }
                }

                Log::info("User data prefetched for {$username}");
            } catch (\Exception $e) {
                Log::error('Failed to prefetch user data', [
                    'username' => $username,
                    'error' => $e->getMessage(),
                ]);
            }
        })->afterResponse();
    }

    /**
     * Précharger selon les patterns de navigation
     */
    public function prefetchNavigationPrediction(string $currentPage): void
    {
        $predictions = $this->getPredictedPages($currentPage);

        foreach ($predictions as $prediction) {
            dispatch(function () use ($prediction): void {
                match ($prediction['type']) {
                    'trending' => $this->prefetchTrending(),
                    'user' => $this->prefetchUserData($prediction['param']),
                    'item' => $this->prefetchPopularItems([$prediction['param']], $prediction['media_type']),
                    default => null
                };
            })->afterResponse()->delay(now()->addSeconds(2));
        }
    }

    /**
     * Prédire les prochaines pages visitées
     */
    private function getPredictedPages(string $currentPage): array
    {
        // Analyse simple basée sur les patterns de navigation courants
        $predictions = [];

        if ($currentPage === '/') {
            // Depuis la home, les users vont souvent vers trending
            $predictions[] = ['type' => 'trending'];
        } elseif (str_contains($currentPage, '/trending')) {
            // Depuis trending, précharger les premiers items
            $predictions[] = ['type' => 'item', 'param' => 1, 'media_type' => 'movie'];
        } elseif (str_contains($currentPage, '/movie/')) {
            // Depuis un film, précharger les similaires
            preg_match('/\/movie\/(\d+)/', $currentPage, $matches);
            if (isset($matches[1])) {
                $predictions[] = ['type' => 'similar', 'param' => $matches[1], 'media_type' => 'movie'];
            }
        }

        return $predictions;
    }

    /**
     * Nettoyer le cache ancien et optimiser l'espace
     */
    public function cleanupOldCache(): void
    {
        dispatch(function (): void {
            $this->cacheService->smartCleanup(100); // Garder max 100MB

            // Nettoyer les entrées expirées de la DB
            \DB::table('offline_cache')
                ->where('expires_at', '<', now())
                ->delete();

            \DB::table('sync_queue')
                ->where('status', 'completed')
                ->where('updated_at', '<', now()->subDays(7))
                ->delete();

            Log::info('Cache cleanup completed');
        })->daily()->at('03:00');
    }
}
