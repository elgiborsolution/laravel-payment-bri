
<?php
namespace ESolution\BriPayments\Briva;

use ESolution\BriPayments\Support\HttpClient;
use ESolution\BriPayments\Support\NonSnapSignature;
use RuntimeException;

class BrivaClient
{
    protected HttpClient $http;
    protected NonSnapSignature $sig;
    public function __construct(protected array $config) {
        $this->http = new HttpClient($config);
        $this->sig = new NonSnapSignature($config);
    }

    public function getToken(): string
    {
        $url = '/oauth/client_credential/accesstoken?grant_type=client_credentials';
        $resp = $this->http->post($url, [
            'client_id' => $this->config['common']['client_id'],
            'client_secret' => $this->config['common']['client_secret'],
        ], ['Content-Type' => 'application/x-www-form-urlencoded']);
        $token = $resp['access_token'] ?? null;
        if (!$token) throw new RuntimeException('BRIVA token not returned');
        return $token;
    }

    protected function signedHeaders(string $token, string $verb, string $path, string $body = ''): array
    {
        $timestamp = $this->sig->iso8601UtcNow();
        $signature = $this->sig->buildSignature($path, $verb, 'Bearer '.$token, $timestamp, $body);
        return [
            'Authorization' => 'Bearer '.$token,
            'BRI-Timestamp' => $timestamp,
            'BRI-Signature' => $signature,
            'Content-Type'  => 'application/json',
        ];
    }

    public function createVa(array $params): array
    {
        $token = $this->getToken();
        $path = '/v1/briva';
        $body = $this->sig->minifyJson($params);
        $headers = $this->signedHeaders($token, 'POST', $path, $body);
        return $this->http->post($path, $params, $headers);
    }

    public function getVa(string $institutionCode, string $brivaNo, string $custCode): array
    {
        $token = $this->getToken();
        $path = "/v1/briva/{$institutionCode}/{$brivaNo}/{$custCode}";
        $headers = $this->signedHeaders($token, 'GET', $path, '');
        return $this->http->get($path, $headers);
    }

    public function getStatus(string $institutionCode, string $brivaNo, string $custCode): array
    {
        $token = $this->getToken();
        $path = "/v1/briva/status/{$institutionCode}/{$brivaNo}/{$custCode}";
        $headers = $this->signedHeaders($token, 'GET', $path, '');
        return $this->http->get($path, $headers);
    }

    public function updateStatus(string $institutionCode, string $brivaNo, string $custCode, string $statusBayar): array
    {
        $token = $this->getToken();
        $path = '/v1/briva/status';
        $payload = ['institutionCode'=>$institutionCode,'brivaNo'=>$brivaNo,'custCode'=>$custCode,'statusBayar'=>$statusBayar];
        $body = $this->sig->minifyJson($payload);
        $headers = $this->signedHeaders($token, 'PUT', $path, $body);
        return $this->http->put($path, $payload, $headers);
    }

    public function updateVa(array $params): array
    {
        $token = $this->getToken();
        $path = '/v1/briva';
        $body = $this->sig->minifyJson($params);
        $headers = $this->signedHeaders($token, 'PUT', $path, $body);
        return $this->http->put($path, $params, $headers);
    }

    public function deleteVa(string $institutionCode, string $brivaNo, string $custCode): array
    {
        $token = $this->getToken();
        $path = '/v1/briva';
        $body = "institutionCode={$institutionCode}&brivaNo={$brivaNo}&custCode={$custCode}";
        $timestamp = $this->sig->iso8601UtcNow();
        $signature = $this->sig->buildSignature($path, 'DELETE', 'Bearer '.$token, $timestamp, $body);
        $headers = ['Authorization'=>'Bearer '.$token,'BRI-Timestamp'=>$timestamp,'BRI-Signature'=>$signature,'Content-Type'=>'text/plain'];
        return $this->http->delete($path, $body, $headers, 'text/plain');
    }

    public function getReport(string $institutionCode, string $brivaNo, string $startDate, string $endDate): array
    {
        $token = $this->getToken();
        $path = "/v1/briva/report/{$institutionCode}/{$brivaNo}/{$startDate}/{$endDate}";
        $headers = $this->signedHeaders($token, 'GET', $path, '');
        return $this->http->get($path, $headers);
    }

    public function getReportTime(string $institutionCode, string $brivaNo, string $startDate, string $startTime, string $endDate, string $endTime): array
    {
        $token = $this->getToken();
        $path = "/v1/briva/report_time/{$institutionCode}/{$brivaNo}/{$startDate}/{$startTime}/{$endDate}/{$endTime}";
        $headers = $this->signedHeaders($token, 'GET', $path, '');
        return $this->http->get($path, $headers);
    }
}
