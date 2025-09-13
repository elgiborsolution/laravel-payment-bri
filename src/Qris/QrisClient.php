
<?php

namespace ESolution\BriPayments\Qris;

use ESolution\BriPayments\Support\HttpClient;
use ESolution\BriPayments\Support\SnapSignature;
use RuntimeException;

class QrisClient
{
    public function __construct(protected array $config) {
        $this->http = new HttpClient($config);
        $this->sig = new SnapSignature($config);
    }
    protected HttpClient $http;
    protected SnapSignature $sig;

    public function getToken(): string
    {
        $timestamp = $this->sig->iso8601Now();
        $headers = $this->sig->rsaHeaders($timestamp);
        $res = $this->http->post('/snap/v1.0/access-token/b2b', ['grantType' => 'client_credentials'], $headers);
        $token = $res['accessToken'] ?? null;
        if (!$token) throw new RuntimeException('SNAP token not returned');
        return $token;
    }

    public function generateQr(string $partnerReferenceNo, string $amount, string $currency = 'IDR'): object
    {
        $token = $this->getToken();
        $path = '/v1.0/qr-dynamic-mpm/qr-mpm-generate-qr';
        $timestamp = $this->sig->iso8601Now();
        $body = [
            'partnerReferenceNo' => $partnerReferenceNo,
            'amount' => ['value' => $amount, 'currency' => $currency],
            'merchantId' => $this->config['qris']['merchant_id'],
            'terminalId' => $this->config['qris']['terminal_id'],
        ];
        $headers = $this->sig->businessHeaders($token, $timestamp, $path, $body);
        $res = $this->http->post($path, $body, $headers);
        return (object)$res;
    }

    public function inquiryPayment(string $originalReferenceNo, string $terminalId, string $serviceCode = '17'): object
    {
        $token = $this->getToken();
        $path = '/v1.0/qr-dynamic-mpm/qr-mpm-query';
        $timestamp = $this->sig->iso8601Now();
        $body = [
            'originalReferenceNo' => $originalReferenceNo,
            'serviceCode' => $serviceCode,
            'additionalInfo' => ['terminalId' => $terminalId],
        ];
        $headers = $this->sig->businessHeaders($token, $timestamp, $path, $body);
        $res = $this->http->post($path, $body, $headers);
        return (object)$res;
    }
}
