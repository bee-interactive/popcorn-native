<?php

namespace App\Providers;

use App\Jobs\MonitorConnectivityJob;
use App\Jobs\ProcessSyncQueueJob;
use App\Services\Cache\MobileCacheService;
use App\Services\ImageCacheService;
use App\Services\PrefetchService;
use Illuminate\Support\Facades\Schedule;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Enregistrer MobileCacheService comme singleton
        $this->app->singleton(MobileCacheService::class, fn ($app): \App\Services\Cache\MobileCacheService => new MobileCacheService);

        // Enregistrer ImageCacheService comme singleton
        $this->app->singleton(ImageCacheService::class, fn ($app): \App\Services\ImageCacheService => new ImageCacheService);

        // Enregistrer PrefetchService avec ses dépendances
        $this->app->singleton(PrefetchService::class, fn ($app): \App\Services\PrefetchService => new PrefetchService(
            $app->make(MobileCacheService::class),
            new \App\Http\Integrations\Tmdb\TmdbConnector
        ));
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Configurer les jobs récurrents pour l'optimisation mobile
        if ($this->app->runningInConsole()) {
            $this->configureScheduledJobs();
        }

        // Précharger les données au démarrage de l'app
        if (! $this->app->runningInConsole()) {
            $this->warmupCache();
        }
    }

    /**
     * Configurer les tâches planifiées
     */
    private function configureScheduledJobs(): void
    {
        Schedule::call(function (): void {
            // Vérifier la connectivité toutes les minutes
            dispatch(new MonitorConnectivityJob);
        })->everyMinute();

        Schedule::call(function (): void {
            // Traiter la file de synchronisation toutes les 5 minutes
            dispatch(new ProcessSyncQueueJob);
        })->everyFiveMinutes();

        Schedule::call(function (): void {
            // Nettoyer le cache ancien une fois par jour
            app(PrefetchService::class)->cleanupOldCache();
            app(ImageCacheService::class)->cleanupOldImages(30);
        })->dailyAt('03:00');

        Schedule::call(function (): void {
            // Rafraîchir les données trending toutes les 3 heures
            app(PrefetchService::class)->prefetchTrending();
        })->everyThreeHours();
    }

    /**
     * Préchauffer le cache au démarrage
     */
    private function warmupCache(): void
    {
        // Précharger les données trending en arrière-plan
        dispatch(function (): void {
            try {
                app(PrefetchService::class)->prefetchTrending();

                // Si un utilisateur est connecté, précharger ses données
                if (auth()->check() && auth()->user()->username) {
                    app(PrefetchService::class)->prefetchUserData(
                        auth()->user()->username
                    );
                }
            } catch (\Exception $e) {
                logger()->error('Cache warmup failed', [
                    'error' => $e->getMessage(),
                ]);
            }
        })->afterResponse();
    }
}
