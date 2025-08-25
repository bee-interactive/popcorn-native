<?php

namespace App\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class MonitorConnectivityJob implements ShouldQueue
{
    use Queueable;

    /**
     * Vérifier la connectivité et déclencher la synchro si nécessaire
     */
    public function handle(): void
    {
        $wasOffline = ! Cache::get('app.online_status', true);
        $isOnline = $this->checkConnectivity();

        Cache::put('app.online_status', $isOnline, 60); // Cache for 1 minute

        if ($wasOffline && $isOnline) {
            // On vient de passer online - déclencher la synchronisation
            Log::info('Connectivity restored - triggering sync');
            dispatch(new ProcessSyncQueueJob)->onQueue('sync');

            // Rafraîchir les données trending
            app(\App\Services\PrefetchService::class)->prefetchTrending();
        } elseif (! $wasOffline && ! $isOnline) {
            // On vient de passer offline
            Log::warning('Lost connectivity - entering offline mode');
        }
    }

    /**
     * Vérifier la connectivité réelle
     */
    private function checkConnectivity(): bool
    {
        try {
            // Tester plusieurs endpoints pour être sûr
            $endpoints = [
                config('services.api.url').'/health',
                'https://api.themoviedb.org/3/configuration',
                'https://www.google.com',
            ];

            foreach ($endpoints as $endpoint) {
                try {
                    $response = Http::timeout(3)->get($endpoint);
                    if ($response->successful() || $response->status() === 401) {
                        return true; // Au moins un endpoint répond
                    }
                } catch (\Exception) {
                    continue; // Essayer le prochain
                }
            }

            return false; // Aucun endpoint ne répond
        } catch (\Exception $e) {
            Log::error('Connectivity check failed', ['error' => $e->getMessage()]);

            return false;
        }
    }
}
