<?php

use App\Jobs\ProcessSyncQueueJob;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;

beforeEach(function () {
    // Créer la table sync_queue si elle n'existe pas
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

    // Nettoyer avant chaque test
    DB::table('sync_queue')->delete();
    Cache::flush();

    // Configurer l'API
    config(['services.api.url' => 'https://api.example.com']);

    // Configurer un token de test en session
    session(['app-access-token' => 'test-job-token']);
});

describe('ProcessSyncQueueJob', function () {
    test('processes pending sync items when online', function () {
        // Simuler mode online
        Cache::put('app.online_status', true);

        // Créer un item à synchroniser
        DB::table('sync_queue')->insert([
            'type' => 'post_request',
            'payload' => json_encode([
                'method' => 'POST',
                'url' => '/items',
                'params' => ['name' => 'Test Item'],
            ]),
            'status' => 'pending',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Mock la réponse HTTP
        Http::fake([
            'https://api.example.com/items' => Http::response(['success' => true], 200),
        ]);

        // Exécuter le job
        $job = new ProcessSyncQueueJob;
        $job->handle();

        // Vérifier que l'item est marqué comme complété
        $item = DB::table('sync_queue')->first();
        expect($item->status)->toBe('completed');
    });

    test('skips processing when offline', function () {
        // Simuler mode offline
        Cache::put('app.online_status', false);

        // Créer un item à synchroniser
        DB::table('sync_queue')->insert([
            'type' => 'post_request',
            'payload' => json_encode([
                'method' => 'POST',
                'url' => '/items',
                'params' => ['name' => 'Test Item'],
            ]),
            'status' => 'pending',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Exécuter le job
        $job = new ProcessSyncQueueJob;
        $job->handle();

        // L'item devrait rester pending
        $item = DB::table('sync_queue')->first();
        expect($item->status)->toBe('pending');
    });
});
