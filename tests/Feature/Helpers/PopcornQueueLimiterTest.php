<?php

use App\Helpers\Popcorn;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

beforeEach(function () {
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

    DB::table('sync_queue')->delete();
    Cache::flush();

    config(['services.api.url' => 'https://api.test/']);
    session(['app-access-token' => 'test-token']);

    Cache::put('app.online_status', false);
});

test('queues item when offline and under limit', function () {
    $result = Popcorn::post('/items', ['name' => 'Test'], true);

    expect($result->get('success'))->toBeTrue();
    expect($result->get('queued'))->toBeTrue();
    expect(DB::table('sync_queue')->where('status', 'pending')->count())->toBe(1);
});

test('rejects queueing when limit is reached', function () {
    for ($i = 0; $i < 1000; $i++) {
        DB::table('sync_queue')->insert([
            'type' => 'post_request',
            'payload' => json_encode(['method' => 'POST', 'url' => '/items', 'params' => []]),
            'status' => 'pending',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    Log::shouldReceive('warning')
        ->once()
        ->with('Sync queue limit reached', \Mockery::type('array'));

    $result = Popcorn::post('/items', ['name' => 'Test'], true);

    expect($result->get('success'))->toBeFalse();
    expect($result->get('queued'))->toBeFalse();
    expect($result->get('error'))->toContain('maximum size');
    expect(DB::table('sync_queue')->where('status', 'pending')->count())->toBe(1000);
});

test('logs warning when queue approaches 80 percent capacity', function () {
    for ($i = 0; $i < 801; $i++) {
        DB::table('sync_queue')->insert([
            'type' => 'post_request',
            'payload' => json_encode(['method' => 'POST', 'url' => '/items', 'params' => []]),
            'status' => 'pending',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    Log::shouldReceive('warning')
        ->once()
        ->with('Sync queue is approaching limit', \Mockery::type('array'));

    $result = Popcorn::post('/items', ['name' => 'Test'], true);

    expect($result->get('success'))->toBeTrue();
    expect($result->get('queued'))->toBeTrue();
});

test('allows queueing at exactly 999 items', function () {
    for ($i = 0; $i < 999; $i++) {
        DB::table('sync_queue')->insert([
            'type' => 'post_request',
            'payload' => json_encode(['method' => 'POST', 'url' => '/items', 'params' => []]),
            'status' => 'pending',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    $result = Popcorn::post('/items', ['name' => 'Test'], true);

    expect($result->get('success'))->toBeTrue();
    expect($result->get('queued'))->toBeTrue();
    expect(DB::table('sync_queue')->where('status', 'pending')->count())->toBe(1000);
});

test('completed items do not count toward limit', function () {
    for ($i = 0; $i < 1000; $i++) {
        DB::table('sync_queue')->insert([
            'type' => 'post_request',
            'payload' => json_encode(['method' => 'POST', 'url' => '/items', 'params' => []]),
            'status' => 'completed',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    $result = Popcorn::post('/items', ['name' => 'Test'], true);

    expect($result->get('success'))->toBeTrue();
    expect($result->get('queued'))->toBeTrue();
    expect(DB::table('sync_queue')->where('status', 'pending')->count())->toBe(1);
});

test('patch method respects queue limit', function () {
    for ($i = 0; $i < 1000; $i++) {
        DB::table('sync_queue')->insert([
            'type' => 'patch_request',
            'payload' => json_encode(['method' => 'PATCH', 'url' => '/items/1', 'params' => []]),
            'status' => 'pending',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    Log::shouldReceive('warning')
        ->once()
        ->with('Sync queue limit reached', \Mockery::type('array'));

    $result = Popcorn::patch('/items/1', ['name' => 'Updated'], true);

    expect($result->get('success'))->toBeFalse();
    expect($result->get('queued'))->toBeFalse();
    expect($result->get('error'))->toContain('maximum size');
});

test('delete method respects queue limit', function () {
    for ($i = 0; $i < 1000; $i++) {
        DB::table('sync_queue')->insert([
            'type' => 'delete_request',
            'payload' => json_encode(['method' => 'DELETE', 'url' => '/items/1', 'params' => []]),
            'status' => 'pending',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    Log::shouldReceive('warning')
        ->once()
        ->with('Sync queue limit reached', \Mockery::type('array'));

    $result = Popcorn::delete('/items/1', null, true);

    expect($result->get('success'))->toBeFalse();
    expect($result->get('queued'))->toBeFalse();
    expect($result->get('error'))->toContain('maximum size');
});

test('error message includes current limit value', function () {
    for ($i = 0; $i < 1000; $i++) {
        DB::table('sync_queue')->insert([
            'type' => 'post_request',
            'payload' => json_encode(['method' => 'POST', 'url' => '/items', 'params' => []]),
            'status' => 'pending',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    Log::shouldReceive('warning')->once();

    $result = Popcorn::post('/items', ['name' => 'Test'], true);

    expect($result->get('error'))->toContain('1000 items');
});

test('logs include method and url when limit reached', function () {
    for ($i = 0; $i < 1000; $i++) {
        DB::table('sync_queue')->insert([
            'type' => 'post_request',
            'payload' => json_encode(['method' => 'POST', 'url' => '/items', 'params' => []]),
            'status' => 'pending',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    Log::shouldReceive('warning')
        ->once()
        ->with('Sync queue limit reached', \Mockery::on(function ($context) {
            return isset($context['attempted_method'])
                && isset($context['attempted_url'])
                && $context['attempted_method'] === 'POST'
                && $context['attempted_url'] === '/items';
        }));

    Popcorn::post('/items', ['name' => 'Test'], true);
});

test('queue limit only applies when queueSync is enabled', function () {
    for ($i = 0; $i < 1000; $i++) {
        DB::table('sync_queue')->insert([
            'type' => 'post_request',
            'payload' => json_encode(['method' => 'POST', 'url' => '/items', 'params' => []]),
            'status' => 'pending',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    Cache::put('app.online_status', true);

    Http::fake([
        'https://api.test/*' => Http::response(['success' => true], 200),
    ]);

    $result = Popcorn::post('/items', ['name' => 'Test'], false);

    expect($result->get('error'))->toBeNull();
});

test('mixed status items only count pending toward limit', function () {
    for ($i = 0; $i < 500; $i++) {
        DB::table('sync_queue')->insert([
            'type' => 'post_request',
            'payload' => json_encode(['method' => 'POST', 'url' => '/items', 'params' => []]),
            'status' => 'pending',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    for ($i = 0; $i < 500; $i++) {
        DB::table('sync_queue')->insert([
            'type' => 'post_request',
            'payload' => json_encode(['method' => 'POST', 'url' => '/items', 'params' => []]),
            'status' => 'completed',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    $result = Popcorn::post('/items', ['name' => 'Test'], true);

    expect($result->get('success'))->toBeTrue();
    expect($result->get('queued'))->toBeTrue();
    expect(DB::table('sync_queue')->where('status', 'pending')->count())->toBe(501);
});
