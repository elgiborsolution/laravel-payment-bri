
<?php

namespace Elgibor\BriQris\Support;

use Illuminate\Support\Str;
use RuntimeException;

class Signature
{
    public function __construct(protected array $config) {}

    public function iso8601Now(): string
    {
        // Keep timezone offset like +07:00
        return now()->format('Y-m-d\TH:i:sP');
    }

    /** RSA SHA256 for token endpoint headers */
    public function rsaHeaders(string $timestamp): array
    {
        $clientId = $this->config['client_id'];
        $privatePath = base_path($this->config['private_key_path']);
        if (!file_exists($privatePath)) {
            throw new RuntimeException('BRI private key not found at ' . $privatePath);
        }
        $privateKey = openssl_pkey_get_private(file_get_contents($privatePath));
        if (!$privateKey) throw new RuntimeException('Invalid private key');

        $stringToSign = $clientId . '|' . $timestamp;

        $signature = '';
        openssl_sign($stringToSign, $signature, $privateKey, OPENSSL_ALGO_SHA256);
        $sig64 = base64_encode($signature);

        return [
            'X-SIGNATURE' => $sig64,
            'X-CLIENT-KEY' => $clientId,
            'X-TIMESTAMP' => $timestamp,
            'Content-Type' => 'application/json',
        ];
    }

    /** HMAC SHA512 symmetric signature for business endpoints */
    public function businessHeaders(string $accessToken, string $timestamp, string $path, array $body): array
    {
        $clientSecret = $this->config['client_secret'];
        $partnerId = $this->config['partner_id'];
        $channelId = $this->config['channel_id'];
        $externalId = (string) Str::uuid();

        $minified = json_encode($body, JSON_UNESCAPED_SLASHES);
        $bodyHash = hash('sha256', $minified);
        $stringToSign = implode(':', [
            'POST',
            $path,
            $accessToken,
            strtolower($bodyHash),
            $timestamp,
        ]);

        $hmac = hash_hmac('sha512', $stringToSign, $clientSecret);
        return [
            'Authorization'   => 'Bearer ' . $accessToken,
            'X-TIMESTAMP'     => $timestamp,
            'X-SIGNATURE'     => $hmac,
            'Content-Type'    => 'application/json',
            'X-PARTNER-ID'    => $partnerId,
            'CHANNEL-ID'      => (string)$channelId,
            'X-EXTERNAL-ID'   => $externalId,
            'X-EXTRENAL-ID'  => $externalId, // some docs use this misspelling
        ];
    }

    /** Optional: verify RSA signature from BRI using supplied public key */
    public function verifyRsa(string $stringToSign, string $signatureBase64): bool
    {
        $publicPath = $this->config['public_key_path'] ? base_path($this->config['public_key_path']) : null;
        if (!$publicPath || !file_exists($publicPath)) {
            return false; // skip if not configured
        }
        $pubKey = openssl_pkey_get_public(file_get_contents($publicPath));
        if (!$pubKey) return false;
        $ok = openssl_verify($stringToSign, base64_decode($signatureBase64), $pubKey, OPENSSL_ALGO_SHA256);
        return $ok === 1;
    }
}
