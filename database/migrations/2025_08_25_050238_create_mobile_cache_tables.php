<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Table pour backup offline des données critiques
        Schema::create('offline_cache', function (Blueprint $table): void {
            $table->id();
            $table->string('key')->unique();
            $table->string('type', 50);
            $table->longText('data');
            $table->timestamp('expires_at');
            $table->timestamps();

            $table->index(['type', 'expires_at']);
            $table->index('expires_at');
        });

        // Analytics pour cache LRU intelligent
        Schema::create('cache_analytics', function (Blueprint $table): void {
            $table->id();
            $table->string('key')->unique();
            $table->unsignedInteger('access_count')->default(0);
            $table->timestamp('last_accessed_at')->nullable();
            $table->timestamps();

            $table->index(['last_accessed_at', 'access_count']);
        });

        // Stockage local des médias TMDB
        Schema::create('tmdb_items', function (Blueprint $table): void {
            $table->id();
            $table->unsignedInteger('tmdb_id');
            $table->string('media_type', 20); // movie, tv
            $table->string('title');
            $table->text('overview')->nullable();
            $table->string('poster_path')->nullable();
            $table->string('backdrop_path')->nullable();
            $table->date('release_date')->nullable();
            $table->float('vote_average')->nullable();
            $table->json('genres')->nullable();
            $table->json('full_data');
            $table->string('language', 10)->default('en');
            $table->timestamps();

            $table->unique(['tmdb_id', 'media_type', 'language']);
            $table->index('title');
            $table->index(['media_type', 'updated_at']);
        });

        // Historique de recherche pour suggestions offline
        Schema::create('search_history', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('query');
            $table->json('results')->nullable();
            $table->unsignedInteger('results_count')->default(0);
            $table->timestamps();

            $table->index(['user_id', 'created_at']);
            $table->index('query');
        });

        // File d'attente de synchronisation
        Schema::create('sync_queue', function (Blueprint $table): void {
            $table->id();
            $table->string('type', 50); // wishlist_add, wishlist_remove, etc.
            $table->json('payload');
            $table->string('status', 20)->default('pending');
            $table->unsignedInteger('attempts')->default(0);
            $table->timestamp('next_attempt_at')->nullable();
            $table->text('error_message')->nullable();
            $table->timestamps();

            $table->index(['status', 'next_attempt_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('sync_queue');
        Schema::dropIfExists('search_history');
        Schema::dropIfExists('tmdb_items');
        Schema::dropIfExists('cache_analytics');
        Schema::dropIfExists('offline_cache');
    }
};
