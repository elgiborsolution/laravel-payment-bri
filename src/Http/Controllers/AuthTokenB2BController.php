<?php

namespace ESolution\BriPayments\Http\Controllers;

use Illuminate\Routing\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;
use ESolution\BriPayments\Support\SnapSignature;
use ESolution\BriPayments\Support\BriConfig;

class AuthTokenB2BController extends Controller
{
    
    public function handle(Request $request, $tenant = null)
    {
        $clientId  = $request->header('X-CLIENT-KEY');
        $timestamp = $request->header('X-TIMESTAMP');
        $signature = $request->header('X-SIGNATURE');

        // Cek header wajib
        if (!$clientId || !$timestamp || !$signature) {
            return response()->json([
                'responseCode' => '4007300',
                'responseMessage' => 'Bad Request',
            ], 400);
        }

        // Regex untuk ISO 8601
        $pattern = '/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}[+-]\d{2}:\d{2}$/';
        if (!preg_match($pattern, $timestamp)) {
            return response()->json([
                'responseCode' => '4007301',
                'responseMessage' => 'Invalid Field Format',
            ], 400);
        }

        // Ambil client
        $query = DB::table('bri_clients')->where('client_id', $clientId);
        if (!empty($tenant)) {
            $query->where('tenant_id', $tenant);
        }
        $client = $query->first();

        if (!$client) {
            return response()->json([
                'responseCode' => '4017300',
                'responseMessage' => 'Unauthorized Client',
            ], 401);
        }

        // String to sign
        $stringToSign = $clientId . '|' . $timestamp;

        // Decode signature Base64
        $decodedSignature = base64_decode($signature);

        $config =  BriConfig::for($tenant);

        // ============================
        // FUNCTION to load public key
        // ============================
        $loadPublicKey = function ($publicKeyString, $publicKeyPath) {
            // 1. Cek string public key langsung
            if (!empty($publicKeyString)) {
                $key = openssl_pkey_get_public($publicKeyString);
                if ($key !== false) {
                    return $key;
                }
            }

            // 2. Fallback: cek file path
            if (!empty($publicKeyPath)) {
                $fullPath = base_path($publicKeyPath);

                if (file_exists($fullPath) && is_readable($fullPath)) {
                    $content = file_get_contents($fullPath);
                    $key = openssl_pkey_get_public($content);

                    if ($key !== false) {
                        return $key;
                    }
                }
            }

            return null;
        };

        // Load both keys
        $publicKeyQris = $loadPublicKey(
            $config['qris']['public_key'] ?? null,
            $config['qris']['public_key_path'] ?? null
        );
        $publicKeyBriva = $loadPublicKey(
            $config['briva']['public_key'] ?? null,
            $config['briva']['public_key_path'] ?? null
        );

        // Verifikasi
        $verifyQris = 0;
        if ($publicKeyQris) {
            $verifyQris = openssl_verify(
                $stringToSign,
                $decodedSignature,
                $publicKeyQris,
                OPENSSL_ALGO_SHA256
            );
        }

        $verifyBriva = 0;
        if ($publicKeyBriva) {
            $verifyBriva = openssl_verify(
                $stringToSign,
                $decodedSignature,
                $publicKeyBriva,
                OPENSSL_ALGO_SHA256
            );
        }

        // Gagal semua verification
        if ($verifyQris !== 1 && $verifyBriva !== 1) {
            return response()->json([
                'responseCode' => '4017300',
                'responseMessage' => 'Unauthorized Signature',
            ], 401);
        }

        // Generate token
        $token = Str::random(64);
        DB::table('bri_access_tokens')->insert([
            'client_id'  => $client->id,
            'token'      => $token,
            'expires_at' => now()->addHours(1),
        ]);

        return response()->json([
            'accessToken' => $token,
            'tokenType'   => 'BearerToken',
            'expiresIn'   => 3600,
        ], 200);
    }


    public function getSignatureAuth(Request $request, $tenant=null)
    {
        // 1. Data untuk signature
        $clientId = $request->header('X-CLIENT-KEY');;

        // 2. Timestamp sesuai format ISO8601 + milliseconds + timezone
        $timestamp = $request->header('X-TIMESTAMP');
        // contoh: 2025-10-20T09:15:30.456+07:00

        // 3. Buat stringToSign
        $stringToSign = $clientId . '|' . $timestamp;

        // 4. Load private key (PEM)
        $config = config('bri');
        $privatePath = base_path($config['qris']['private_key_path']);

        // Cek file ada dan readable
        if (!file_exists($privatePath) || !is_readable($privatePath)) {

            return response()->json([
                'responseCode' => '4017301',
                'responseMessage' => 'Invalid Token (B2B)',
            ], 401);
        }
        $privateKey = openssl_pkey_get_private(file_get_contents($privatePath));

        // 5. Sign string dengan RSA + SHA256
        openssl_sign($stringToSign, $signature, $privateKey, OPENSSL_ALGO_SHA256);

        // 6. Encode signature ke Base64
        $xSignature = base64_encode($signature);

        // 7. Header final untuk request
        $headers = [
            'X-CLIENT-KEY' => $clientId,
            'X-TIMESTAMP'  => $timestamp,
            'X-SIGNATURE'  => $xSignature,
            'Content-Type' => 'application/json',
        ];
        return response()->json($headers, 200);
    }

    public function getSignature(Request $request, $tenant = null)
    {
        // Ambil header
        $token = $request->header('Authorization');
        $timestamp = $request->header('X-TIMESTAMP');
        $url = $request->header('EndpoinUrl');
        $method = $request->header('HttpMethod');

        // Validasi header wajib
        $missing = [];
        if (!$token) $missing[] = 'Authorization';
        if (!$timestamp) $missing[] = 'X-TIMESTAMP';
        if (!$url) $missing[] = 'EndpoinUrl';
        if (!$method) $missing[] = 'HttpMethod';

        if (!empty($missing)) {
            return response()->json([
                'responseCode' => '4003401',
                'responseMessage' => 'Missing required headers: ' . implode(', ', $missing),
            ], 400);
        }

        // Hapus prefix Bearer
        $token = str_replace('Bearer ', '', $token);

        // Instance SnapSignature
        $sig = new SnapSignature();
        $bodyRaw = $request->getContent();
        $client = $request->client ?? [];

        // Generate signature
        $xSignature = $sig->generateSignature(
            $url,
            $method,
            $token,
            $client['client_secret'] ?? '',
            $bodyRaw,
            strval($timestamp)
        );

        // Return header signature
        $headers = [
            'X-TIMESTAMP' => $timestamp,
            'X-SIGNATURE' => $xSignature,
        ];

        return response()->json($headers, 200);
    }

}
