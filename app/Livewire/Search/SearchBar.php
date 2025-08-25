<?php

namespace App\Livewire\Search;

use App\Http\Integrations\Tmdb\Requests\SearchMultiRequest;
use App\Http\Integrations\Tmdb\TmdbConnector;
use App\Services\Cache\MobileCacheService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Debounce;
use Livewire\Attributes\On;
use Livewire\Component;

class SearchBar extends Component
{
    public array $results = [];

    #[Debounce(300)]
    public string $query = '';

    public string $layout = 'maximal';

    public bool $isSearching = false;

    public function mount(string $layout): void
    {
        $this->layout = $layout;

        // Précharger les suggestions depuis l'historique
        $this->loadSearchSuggestions();
    }

    private function getCacheService(): MobileCacheService
    {
        return app(MobileCacheService::class);
    }

    public function updatedQuery(TmdbConnector $connector): void
    {
        $this->search($connector);
    }

    public function search(TmdbConnector $connector): void
    {
        // Ignorer les recherches trop courtes
        if (strlen($this->query) < 2) {
            $this->results = [];
            $this->isSearching = false;

            return;
        }

        $this->isSearching = true;

        // Clé de cache pour cette recherche
        $cacheKey = 'search.tmdb.'.md5($this->query.app()->getLocale());

        // Essayer de récupérer depuis le cache
        $cachedResults = $this->getCacheService()->remember(
            $cacheKey,
            'search',
            function () use ($connector) {
                $response = $connector->send(new SearchMultiRequest($this->query));

                if ($response->failed()) {
                    return [];
                }

                return $response->json('results', []);
            },
            3600 // Cache pour 1 heure
        );

        $this->results = $cachedResults ?: [];
        $this->isSearching = false;

        // Sauvegarder dans l'historique de recherche
        if ($this->results !== []) {
            $this->saveSearchHistory();
        }

        // Précharger les images des résultats
        $this->preloadResultImages();
    }

    public function save($result): void
    {
        $this->dispatch('openModal', SaveForLater::class, ['result' => $result]);
    }

    #[On('data-updated')]
    public function render()
    {
        return view('livewire.search.search-bar');
    }

    /**
     * Charger les suggestions de recherche depuis l'historique
     */
    private function loadSearchSuggestions(): void
    {
        if (! \Schema::hasTable('search_history')) {
            return;
        }

        // Récupérer les dernières recherches populaires
        $suggestions = Cache::remember('search.suggestions', 3600, fn () => DB::table('search_history')
            ->select('query', DB::raw('COUNT(*) as count'))
            ->where('results_count', '>', 0)
            ->groupBy('query')
            ->orderByDesc('count')
            ->limit(10)
            ->pluck('query')
            ->toArray());

        // Les suggestions peuvent être utilisées dans la vue
        $this->dispatch('search-suggestions-loaded', suggestions: $suggestions);
    }

    /**
     * Sauvegarder la recherche dans l'historique
     */
    private function saveSearchHistory(): void
    {
        if (! \Schema::hasTable('search_history')) {
            return;
        }

        dispatch(function (): void {
            DB::table('search_history')->insert([
                'user_id' => auth()->id(),
                'query' => $this->query,
                'results' => json_encode(array_slice($this->results, 0, 5)),
                'results_count' => count($this->results),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        })->afterResponse();
    }

    /**
     * Précharger les images des résultats
     */
    private function preloadResultImages(): void
    {
        $imagePaths = array_filter(
            array_column($this->results, 'poster_path')
        );

        if ($imagePaths !== []) {
            app(\App\Services\ImageCacheService::class)
                ->preloadImages(array_slice($imagePaths, 0, 10), 'w185');
        }
    }
}
