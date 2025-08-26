<?php

namespace App\Http\Integrations\Tmdb\Requests;

use Saloon\Enums\Method;
use Saloon\Http\Request;

class TrendingRequest extends Request
{
    protected Method $method = Method::GET;

    public function __construct(
        protected string $mediaType = 'all',
        protected string $timeWindow = 'week',
        protected int $page = 1
    ) {}

    public function resolveEndpoint(): string
    {
        return "/trending/{$this->mediaType}/{$this->timeWindow}";
    }

    protected function defaultQuery(): array
    {
        return [
            'include_adult' => 'false',
            'language' => (session('app-user')['language'] ?? 'en') == 'en' ? 'en-US' : 'fr-Fr',
            'page' => $this->page,
        ];
    }
}
