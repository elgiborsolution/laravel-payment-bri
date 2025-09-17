<?php

namespace ESolution\BriPayments\Support;

use Illuminate\Support\Facades\Http;
use RuntimeException;

class HttpClient
{
    public function __construct(protected array $config) {}

    public function get(string $url, array $headers = []): array
    {
        return $this->request('get', $url, null, $headers);
    }

    public function post(string $url, $body = null, array $headers = []): array
    {
        return $this->request('post', $url, $body, $headers);
    }

    public function put(string $url, $body = null, array $headers = []): array
    {
        return $this->request('put', $url, $body, $headers);
    }

    public function delete(string $url, $body = null, array $headers = [], string $contentType = 'text/plain'): array
    {
        $base = rtrim($this->config['base_url'], '/');
        $timeout = (int)($this->config['briva']['timeout'] ?? 30);
        $req = Http::withHeaders(array_merge($headers, ['Content-Type' => $contentType]))->timeout($timeout);
        $resp = $req->send('DELETE', $base.$url, ['body' => $body]);
        if (!$resp->successful()) throw new RuntimeException('BRI request failed: '.$resp->status().' '.$resp->body());
        $json = json_decode($resp->body(), true);
        return is_array($json) ? $json : ['raw' => $resp->body()];
    }

    protected function request(string $method, string $url, $body, array $headers): array
    {
        $base = rtrim($this->config['base_url'], '/');
        $timeout = (int)($this->config['qris']['timeout'] ?? 30);
        $req = Http::withHeaders($headers)->timeout($timeout);
        if ($body !== null):
            if (is_array($body)) $req = $req->asJson();
            else $req = $req->withBody($body, 'text/plain');
        else:
            $req = $req->acceptJson();
        endif;

        $resp = $req->{$method}($base.$url, $body ?? []);
        if (!$resp->successful()) throw new RuntimeException('BRI request failed: '.$resp->status().' '.$resp->body());
        return $resp->json();
    }
}
