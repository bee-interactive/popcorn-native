<?php

namespace App\Http\Integrations\Tmdb;

use App\Http\Integrations\Middleware\OfflineFallbackMiddleware;
use App\Http\Integrations\Middleware\SqliteCacheMiddleware;
use App\Services\Cache\MobileCacheService;
use Saloon\Http\Connector;
use Saloon\Traits\Plugins\AcceptsJson;
use Saloon\Traits\Plugins\HasTimeout;

class TmdbConnector extends Connector
{
    use AcceptsJson, HasTimeout;

    protected int $connectTimeout = 10;

    protected int $requestTimeout = 30;

    public function resolveBaseUrl(): string
    {
        return 'https://api.themoviedb.org/3';
    }

    protected function defaultHeaders(): array
    {
        // Récupérer le token depuis la session ou le stockage sécurisé
        $token = $this->getTmdbToken();

        return [
            'Authorization' => 'Bearer '.$token,
            'Content-Type' => 'application/json',
            'Accept' => 'application/json',
            'Accept-Language' => app()->getLocale() === 'fr' ? 'fr-FR' : 'en-US',
        ];
    }

    /**
     * Middlewares pour optimisation mobile
     */
    protected function defaultMiddleware(): array
    {
        return [
            // Fallback offline en premier
            new OfflineFallbackMiddleware,

            // Cache SQLite avec TTL selon le type de requête
            new SqliteCacheMiddleware(
                ttl: $this->determineCacheTtl(),
                cacheType: 'tmdb_movie'
            ),
        ];
    }

    /**
     * Récupérer le token TMDB
     */
    private function getTmdbToken(): string
    {
        // Priorité : session > secure storage > config
        if (session('app-user') && isset(session('app-user')['tmdb_token'])) {
            return session('app-user')['tmdb_token'];
        }

        // Fallback sur stockage sécurisé
        $secureToken = app(MobileCacheService::class)->getSecureToken('tmdb_token');
        if ($secureToken) {
            return $secureToken;
        }

        // Dernier recours : config
        return config('services.tmdb.token', '');
    }

    /**
     * Déterminer le TTL du cache selon le contexte
     */
    private function determineCacheTtl(): int
    {
        // Les données TMDB sont relativement stables
        // Films/Séries : 7 jours
        // Trending : 3 heures
        // Search : 1 heure

        // Par défaut, cache longue durée pour les données TMDB
        return 604800; // 7 jours
    }

    /**
     * Hook pour pré-traitement des requêtes
     */
    public function boot(\Saloon\Http\PendingRequest $pendingRequest): void
    {
        // Ajouter des paramètres par défaut si nécessaire
        $pendingRequest->query()->merge([
            'language' => $pendingRequest->query()->get('language', app()->getLocale() === 'fr' ? 'fr-FR' : 'en-US'),
        ]);
    }
}
