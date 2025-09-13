
<?php

namespace Elgibor\BriQris\Support;

use Illuminate\Support\Facades\Http;
use RuntimeException;

class HttpClient
{
    protected array $config;

    public function __construct(array $config)
    {
        $this->config = $config;
    }

    public function post(string $path, array $body = [], array $headers = []): array
    {
        $base = rtrim($this->config['base_url'], '/');
        $timeout = (int) ($this->config['timeout'] ?? 30);

        $response = Http::withHeaders($headers)
            ->timeout($timeout)
            ->acceptJson()
            ->asJson()
            ->post($base . $path, $body);

        if (!$response->successful()) {
            throw new RuntimeException('BRI request failed: ' . $response->status() . ' ' . $response->body());
        }

        return $response->json();
    }
}
