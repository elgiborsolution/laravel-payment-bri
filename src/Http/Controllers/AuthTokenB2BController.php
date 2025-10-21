<?php

namespace ESolution\BriPayments\Http\Controllers;

use Illuminate\Routing\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;
use ESolution\BriPayments\Support\SnapSignature;

class AuthTokenB2BController extends Controller
{
    
    public function handle(Request $request, $tenant=null)
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


        // Regex untuk cek format ISO 8601 dengan milidetik dan timezone
        $pattern = '/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}[+-]\d{2}:\d{2}$/';

        if (!preg_match($pattern, $timestamp)) {
            return response()->json([
                'responseCode' => '4007301',
                'responseMessage' => 'Invalid Field Format',
            ], 400);
        }

        // Ambil client + public_key dari database
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

        // Buat stringToSign
        $stringToSign = $clientId . '|' . $timestamp;

        // Decode signature dari Base64
        $decodedSignature = base64_decode($signature);
        $config = config('bri');
        $publicKeyPath = base_path($config['qris']['public_key_path']);
        // Cek file ada dan readable
        if (!file_exists($publicKeyPath) || !is_readable($publicKeyPath)) {

            return response()->json([
                'responseCode' => '4017301',
                'responseMessage' => 'Invalid Token (B2B)',
            ], 401);
        }
        $publicKey = openssl_pkey_get_public(file_get_contents($publicKeyPath));

        $verify = openssl_verify(
            $stringToSign,
            $decodedSignature,
            $publicKey,
            OPENSSL_ALGO_SHA256
        );

        if ($verify !== 1) {
            return response()->json([
                'responseCode' => '4017300',
                'responseMessage' => 'Unauthorized Signature',
            ], 401);
        }

        // Jika sukses → generate access_token
        $token = Str::random(64);
        DB::table('bri_access_tokens')->insert([
            'client_id'   => $client->id,
            'token'       => $token,
            'expires_at'  => now()->addHours(1),
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
