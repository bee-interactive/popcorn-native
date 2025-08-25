<?php

namespace App\Http\Controllers\Trending;

use App\Http\Integrations\Tmdb\Requests\TrendingRequest;
use App\Http\Integrations\Tmdb\TmdbConnector;
use Illuminate\Support\Facades\Cache;
use Illuminate\View\View;

class TrendingController
{
    public function __invoke(TmdbConnector $connector): View
    {
        $results = Cache::remember('trending_items.'.session('app-user')['username'], 7200, function () use ($connector): array {
            $results = [];

            for ($i = 1; $i <= 6; $i++) {
                $page = $connector->send(new TrendingRequest($i));

                if ($page->failed()) {
                    return [];
                }

                $results = array_merge($results, $page->json('results'));
            }

            return $results;
        });

        return view('trending.index', [
            'results' => $results,
        ]);
    }
}
