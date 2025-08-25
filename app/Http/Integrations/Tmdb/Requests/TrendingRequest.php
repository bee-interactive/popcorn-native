<?php

namespace App\Http\Integrations\Tmdb\Requests;

use Saloon\Enums\Method;
use Saloon\Http\Request;

class TrendingRequest extends Request
{
    protected Method $method = Method::GET;

    public function __construct(protected int $page = 1) {}

    public function resolveEndpoint(): string
    {
        return '/trending/all/week';
    }

    protected function defaultQuery(): array
    {
        return [
            'include_adult' => 'false',
            'language' => 'fr-Fr',
            'page' => $this->page,
        ];
    }
}
