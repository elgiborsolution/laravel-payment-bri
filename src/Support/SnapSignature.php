<?php

namespace ESolution\BriPayments\Support;

use Illuminate\Support\Str;
use RuntimeException;

class SnapSignature
{
    protected array $config;

    public function __construct(array $config = [])
    {
        $this->config = $config ?: (config('bri') ?? []);
    }

    public function iso8601Now(): string { return now()->format('Y-m-d\TH:i:sP'); }

    public function rsaHeaders(string $timestamp): array
    {
        $clientId = $this->config['common']['client_id'];
        $privatePath = base_path($this->config['qris']['private_key_path']);
        if (!file_exists($privatePath)) throw new RuntimeException('SNAP private key not found: '.$privatePath);
        $pkey = openssl_pkey_get_private(file_get_contents($privatePath));
        if (!$pkey) throw new RuntimeException('Invalid SNAP private key');
        openssl_sign($clientId.'|'.$timestamp, $sig, $pkey, OPENSSL_ALGO_SHA256);
        return [
            'X-SIGNATURE' => base64_encode($sig),
            'X-CLIENT-KEY' => $clientId,
            'X-TIMESTAMP' => $timestamp,
            'Content-Type' => 'application/json',
        ];
    }

    public function businessHeaders(string $accessToken, string $timestamp, string $path, array $body): array
    {
        $secret = $this->config['common']['client_secret'];
        $partnerId = $this->config['qris']['partner_id'];
        $channelId = $this->config['qris']['channel_id'];
        $externalId = (string) Str::uuid();
        $minified = json_encode($body, JSON_UNESCAPED_SLASHES);
        $bodyHash = hash('sha256', $minified);
        $stringToSign = implode(':', ['POST', $path, $accessToken, strtolower($bodyHash), $timestamp]);
        $hmac = hash_hmac('sha512', $stringToSign, $secret);
        return [
            'Authorization' => 'Bearer '.$accessToken,
            'X-TIMESTAMP'   => $timestamp,
            'X-SIGNATURE'   => $hmac,
            'Content-Type'  => 'application/json',
            'X-PARTNER-ID'  => $partnerId,
            'CHANNEL-ID'    => (string)$channelId,
            'X-EXTERNAL-ID' => $externalId,
            'X-EXTRENAL-ID' => $externalId,
        ];
    }

    public function verifyRsa(string $stringToSign, string $signatureBase64): bool
    {
        $publicPath = $this->config['qris']['public_key_path'] ? base_path($this->config['qris']['public_key_path']) : null;
        if (!$publicPath || !file_exists($publicPath)) return false;
        $pub = openssl_pkey_get_public(file_get_contents($publicPath));
        if (!$pub) return false;
        $ok = openssl_verify($stringToSign, base64_decode($signatureBase64), $pub, OPENSSL_ALGO_SHA256);
        return $ok === 1;
    }
}
