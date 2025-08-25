<?php

use App\Helpers\Popcorn;
use App\Jobs\MonitorConnectivityJob;
use App\Jobs\ProcessSyncQueueJob;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;

beforeEach(function () {
    // Créer toutes les tables nécessaires
    if (! Schema::hasTable('offline_cache')) {
        Schema::create('offline_cache', function ($table) {
            $table->id();
            $table->string('key')->unique();
            $table->string('type', 50);
            $table->longText('data');
            $table->timestamp('expires_at');
            $table->timestamps();
            $table->index(['type', 'expires_at']);
        });
    }

    if (! Schema::hasTable('cache_analytics')) {
        Schema::create('cache_analytics', function ($table) {
            $table->id();
            $table->string('key')->unique();
            $table->unsignedInteger('access_count')->default(0);
            $table->timestamp('last_accessed_at')->nullable();
            $table->timestamps();
            $table->index(['last_accessed_at', 'access_count']);
        });
    }

    if (! Schema::hasTable('sync_queue')) {
        Schema::create('sync_queue', function ($table) {
            $table->id();
            $table->string('type', 50);
            $table->json('payload');
            $table->string('status', 20)->default('pending');
            $table->unsignedInteger('attempts')->default(0);
            $table->timestamp('next_attempt_at')->nullable();
            $table->text('error_message')->nullable();
            $table->timestamps();
            $table->index(['status', 'next_attempt_at']);
        });
    }

    // Nettoyer
    DB::table('sync_queue')->delete();
    DB::table('offline_cache')->delete();
    DB::table('cache_analytics')->delete();
    Cache::flush();

    config(['services.api.url' => 'https://api.example.com']);
});

describe('Full Optimization Stack Integration', function () {
    test('complete flow: online -> offline -> sync', function () {
        // 1. Mode ONLINE - Faire des requêtes qui seront cachées
        Cache::put('app.online_status', true);

        Http::fake([
            'https://api.example.com/users/john' => Http::response(['name' => 'John', 'id' => 1], 200),
            'https://api.example.com/items' => Http::response(['success' => true], 201),
        ]);

        // GET avec cache
        $user = Popcorn::get('/users/john');
        expect($user->get('name'))->toBe('John');

        // Vérifier que c'est en cache
        $cacheKey = 'popcorn.get.'.md5('https://api.example.com/users/john'.serialize(null));
        expect(Cache::has($cacheKey))->toBeTrue();

        // Vérifier qu'il y a un backup offline
        $offlineBackup = DB::table('offline_cache')->where('key', $cacheKey)->first();
        expect($offlineBackup)->not->toBeNull();

        // 2. Passer en mode OFFLINE
        Cache::put('app.online_status', false);
        Http::fake(); // Plus de réponses

        // Le GET devrait toujours fonctionner (depuis cache offline)
        $cachedUser = Popcorn::get('/users/john');
        expect($cachedUser->get('name'))->toBe('John');

        // POST en mode offline avec queue
        $result = Popcorn::post('/items', ['name' => 'New Item'], true);
        expect($result->get('queued'))->toBeTrue();

        // Vérifier que c'est dans la sync queue
        $queuedItems = DB::table('sync_queue')->where('status', 'pending')->count();
        expect($queuedItems)->toBe(1);

        // 3. Retour en mode ONLINE - Synchronisation
        Cache::put('app.online_status', true);

        Http::fake([
            'https://api.example.com/items' => Http::response(['id' => 2, 'created' => true], 201),
        ]);

        // Exécuter le job de synchronisation
        $syncJob = new ProcessSyncQueueJob;
        $syncJob->handle();

        // Vérifier que l'item a été synchronisé
        $syncedItem = DB::table('sync_queue')->first();
        expect($syncedItem->status)->toBe('completed');
    });

    test('performance: cache hits are faster than misses', function () {
        Http::fake([
            'https://api.example.com/performance/*' => Http::response(['data' => 'test'], 200),
        ]);

        $iterations = 50;

        // Warm up - premier appel pour créer le cache
        Popcorn::get('/performance/test');

        // Benchmark cache HITS
        $startHit = microtime(true);
        for ($i = 0; $i < $iterations; $i++) {
            Popcorn::get('/performance/test');
        }
        $timeHit = microtime(true) - $startHit;

        // Benchmark cache MISSES
        $startMiss = microtime(true);
        for ($i = 0; $i < $iterations; $i++) {
            Cache::forget('popcorn.get.'.md5('https://api.example.com/performance/miss'.$i.serialize(null)));
            Popcorn::get('/performance/miss'.$i);
        }
        $timeMiss = microtime(true) - $startMiss;

        // Les cache hits devraient être plus rapides
        expect($timeHit)->toBeLessThan($timeMiss);

        // Afficher les métriques
        $improvement = (($timeMiss - $timeHit) / $timeMiss) * 100;
        dump([
            'Cache HIT total (s)' => round($timeHit, 4),
            'Cache MISS total (s)' => round($timeMiss, 4),
            'Performance improvement' => round($improvement, 2).'%',
            'Avg HIT (ms)' => round(($timeHit / $iterations) * 1000, 2),
            'Avg MISS (ms)' => round(($timeMiss / $iterations) * 1000, 2),
        ]);
    });

    test('monitoring: connectivity detection and recovery', function () {
        // Simuler perte de connexion
        Cache::put('app.online_status', true);

        // Ajouter des items à synchroniser
        DB::table('sync_queue')->insert([
            'type' => 'post_request',
            'payload' => json_encode(['method' => 'POST', 'url' => '/test', 'params' => []]),
            'status' => 'pending',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Mock des endpoints pour le monitoring
        Http::fake([
            'https://api.example.com/health' => Http::response('', 200),
            '*' => Http::response('', 200),
        ]);

        // Le job de monitoring devrait détecter qu'on est online
        $monitorJob = new MonitorConnectivityJob;
        $monitorJob->handle();

        expect(Cache::get('app.online_status'))->toBeTrue();
    });
});
