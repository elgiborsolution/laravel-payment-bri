
<?php

namespace Elgibor\BriQris;

use Elgibor\BriQris\Support\HttpClient;
use Elgibor\BriQris\Support\Signature;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class BriQris
{
    protected array $config;
    protected HttpClient $http;
    protected Signature $sig;

    public function __construct(array $config)
    {
        $this->config = $config;
        $this->http = new HttpClient($config);
        $this->sig = new Signature($config);
    }

    /** Get B2B token (15 minutes expiry) */
    public function getToken(): string
    {
        $path = '/snap/v1.0/access-token/b2b';
        $timestamp = $this->sig->iso8601Now();
        $headers = $this->sig->rsaHeaders($timestamp);
        $body = ['grantType' => 'client_credentials'];

        $res = $this->http->post($path, $body, $headers);
        $token = $res['accessToken'] ?? null;
        if (!$token) {
            throw new RuntimeException('BRI token not returned');
        }
        return $token;
    }

    /** Generate QR MPM Dynamic */
    public function generateQr(string $partnerReferenceNo, string $amount, string $currency = 'IDR', ?string $merchantId = null, ?string $terminalId = null): object
    {
        $token = $this->getToken();
        $path = '/v1.0/qr-dynamic-mpm/qr-mpm-generate-qr';
        $timestamp = $this->sig->iso8601Now();
        $body = [
            'partnerReferenceNo' => $partnerReferenceNo,
            'amount' => [
                'value' => $amount,
                'currency' => $currency,
            ],
            'merchantId' => $merchantId ?: $this->config['merchant_id'],
            'terminalId' => $terminalId ?: $this->config['terminal_id'],
        ];

        $headers = $this->sig->businessHeaders($token, $timestamp, $path, $body);

        $res = $this->http->post($path, $body, $headers);
        return (object) $res;
    }

    /** Inquiry Payment status */
    public function inquiryPayment(string $originalReferenceNo, string $terminalId, string $serviceCode = '17'): object
    {
        $token = $this->getToken();
        $path = '/v1.0/qr-dynamic-mpm/qr-mpm-query';
        $timestamp = $this->sig->iso8601Now();
        $body = [
            'originalReferenceNo' => $originalReferenceNo,
            'serviceCode' => $serviceCode,
            'additionalInfo' => [
                'terminalId' => $terminalId,
            ],
        ];
        $headers = $this->sig->businessHeaders($token, $timestamp, $path, $body);
        $res = $this->http->post($path, $body, $headers);
        return (object) $res;
    }
}
