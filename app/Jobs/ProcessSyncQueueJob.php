<?php

namespace App\Jobs;

use App\Helpers\Popcorn;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ProcessSyncQueueJob implements ShouldQueue
{
    use Queueable;

    public function __construct()
    {
        $this->onQueue('sync');
    }

    /**
     * Traiter la file de synchronisation
     */
    public function handle(): void
    {
        // Récupérer les items en attente
        $pendingItems = DB::table('sync_queue')
            ->where('status', 'pending')
            ->where(function ($query): void {
                $query->whereNull('next_attempt_at')
                    ->orWhere('next_attempt_at', '<=', now());
            })
            ->orderBy('created_at')
            ->limit(10)
            ->get();

        foreach ($pendingItems as $item) {
            $this->processSyncItem($item);
        }
    }

    /**
     * Traiter un item de synchronisation
     */
    private function processSyncItem($item): void
    {
        $payload = json_decode((string) $item->payload, true);

        try {
            // Vérifier qu'on est online
            if (! cache()->get('app.online_status', true)) {
                Log::info('Sync skipped - offline mode', ['id' => $item->id]);

                return;
            }

            // Exécuter la requête selon le type
            $result = match ($payload['method']) {
                'POST' => Popcorn::post($payload['url'], $payload['params'], false),
                'PATCH' => Popcorn::patch($payload['url'], $payload['params'], false),
                'DELETE' => Popcorn::delete($payload['url'], $payload['params'], false),
                default => null
            };

            if ($result && ! $result->has('error')) {
                // Succès - marquer comme complété
                DB::table('sync_queue')
                    ->where('id', $item->id)
                    ->update([
                        'status' => 'completed',
                        'updated_at' => now(),
                    ]);

                Log::info('Sync item completed', [
                    'id' => $item->id,
                    'method' => $payload['method'],
                    'url' => $payload['url'],
                ]);
            } else {
                // Échec - incrémenter les tentatives
                $this->handleSyncFailure($item, $result?->get('error', 'Unknown error'));
            }
        } catch (\Exception $e) {
            $this->handleSyncFailure($item, $e->getMessage());
        }
    }

    /**
     * Gérer l'échec d'une synchronisation
     */
    private function handleSyncFailure($item, string $error): void
    {
        $attempts = $item->attempts + 1;
        $maxAttempts = 5;

        if ($attempts >= $maxAttempts) {
            // Trop de tentatives - marquer comme échoué
            DB::table('sync_queue')
                ->where('id', $item->id)
                ->update([
                    'status' => 'failed',
                    'attempts' => $attempts,
                    'error_message' => $error,
                    'updated_at' => now(),
                ]);

            Log::error('Sync item failed permanently', [
                'id' => $item->id,
                'error' => $error,
            ]);
        } else {
            // Réessayer plus tard avec backoff exponentiel
            $nextAttempt = now()->addMinutes(2 ** $attempts);

            DB::table('sync_queue')
                ->where('id', $item->id)
                ->update([
                    'attempts' => $attempts,
                    'next_attempt_at' => $nextAttempt,
                    'error_message' => $error,
                    'updated_at' => now(),
                ]);

            Log::warning('Sync item will retry', [
                'id' => $item->id,
                'attempts' => $attempts,
                'next_attempt' => $nextAttempt,
            ]);
        }
    }
}
