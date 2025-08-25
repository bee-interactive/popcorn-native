<?php

namespace App\Http\Integrations\Middleware;

use Saloon\Contracts\PendingRequest;
use Saloon\Contracts\Request;
use Saloon\Contracts\Response;
use Saloon\Traits\Responses\HasResponse;

class CachedResponse implements Response
{
    public $connector;

    public $request;

    use HasResponse;

    protected array $decodedJson;

    public function __construct(
        protected string $body,
        protected int $status = 200,
        protected array $headers = []
    ) {
        $this->headers['X-Cache'] = 'HIT';
        $this->headers['X-Cached-At'] = now()->toIso8601String();
    }

    public function body(): string
    {
        return $this->body;
    }

    public function status(): int
    {
        return $this->status;
    }

    public function headers(): \Saloon\Http\Response\Headers
    {
        return new \Saloon\Http\Response\Headers($this->headers);
    }

    public function json(?string $key = null, mixed $default = null): mixed
    {
        if (! isset($this->decodedJson)) {
            $this->decodedJson = json_decode($this->body, true) ?? [];
        }

        if ($key === null) {
            return $this->decodedJson;
        }

        return data_get($this->decodedJson, $key, $default);
    }

    public function successful(): bool
    {
        return $this->status >= 200 && $this->status < 300;
    }

    public function failed(): bool
    {
        return ! $this->successful();
    }

    public function throw(): static
    {
        return $this;
    }

    public function throwUnlessStatus(callable|int $status): static
    {
        return $this;
    }

    public function getPendingRequest(): PendingRequest
    {
        return new \Saloon\Http\PendingRequest($this->connector, $this->request);
    }

    public function getRequest(): Request
    {
        return $this->request;
    }
}
