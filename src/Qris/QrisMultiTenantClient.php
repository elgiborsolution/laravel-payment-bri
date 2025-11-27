<?php

namespace ESolution\BriPayments\Qris;

use ESolution\BriPayments\Support\HttpClient;
use ESolution\BriPayments\Support\SnapSignature;
use ESolution\BriPayments\Support\BriConfig;
use ESolution\BriPayments\Models\BriQrisPaymentLog;
use RuntimeException;
use Illuminate\Support\Facades\Cache;
use Carbon\Carbon;

class QrisMultiTenantClient
{
    
    protected array $config;
    protected string $tenantId;
    public function __construct(string $tenantId = 'default')
    {
        $this->tenantId = $tenantId;

        $this->config = BriConfig::for($tenantId);

        $this->http = new HttpClient($this->config);
        $this->sig  = new SnapSignature($this->config);
    }

    protected HttpClient $http;
    protected SnapSignature $sig;

    
    public function getToken($force = false): string
    {
        $tenantId = $this->tenantId;

        if ($force) {
            Cache::forget('bri_snap_qris_token-' . $tenantId);
        }

        return Cache::remember('bri_snap_qris_token-' . $tenantId, now()->addSeconds(700), function () use($tenantId) {
            $timestamp = $this->sig->iso8601Now();
            $headers = $this->sig->rsaHeaders($timestamp);

            $res = $this->http->post('/snap/v1.0/access-token/b2b', [
                'grantType' => 'client_credentials'
            ], $headers);

            $token = $res['accessToken'] ?? null;
            $expires = (int)($res['expiresIn'] ?? 899) - 100;

            if (!$token) {
                throw new RuntimeException('SNAP token not returned');
            }

            Cache::put('bri_snap_qris_token-' . $tenantId, $token, now()->addSeconds($expires));

            return $token;
        });
    }

    private function findOrCreateQRLog(array $params, string $reff_id, $throwIfFalse = false): BriQrisPaymentLog
    {
        $clientId = $this->config['common']['client_id'] ?? null;

        // Cek apakah sudah ada
        $log = BriQrisPaymentLog::where('client_id', $clientId)
            ->where('reff_id', $reff_id)
            ->first();

        if($throwIfFalse && empty($log)){
            throw new \Exception("Reference number not found");
        }elseif($throwIfFalse){
            return $log;
        }

        if ($log) {
            // Update existing record
            $log->update($params);
            return $log->refresh(); // agar data terbaru terambil
        }

        // Belum ada → insert baru
        $params = array_merge($params, [
            'client_id'   => $clientId,
            'reff_id' => $reff_id,
        ]);

        return BriQrisPaymentLog::create($params);
    }

    private function formatAmount($value)
    {
        // Jika sudah punya titik desimal 2 digit → langsung return
        if (preg_match('/^\d+(\.\d{2})$/', $value)) {
            return $value;
        }

        // Jika sudah punya titik, tapi bukan 2 digit → normalisasi
        if (preg_match('/^\d+(\.\d+)?$/', $value)) {
            return number_format((float) $value, 2, '.', '');
        }

        // Jika input tanpa titik atau dalam bentuk string angka
        if (is_numeric($value)) {
            return number_format((float) $value, 2, '.', '');
        }

        // Jika input tidak valid
        throw new \InvalidArgumentException("Invalid amount format: $value");
    }



    public function generateQr(string $partnerReferenceNo, $amount, string $currency = 'IDR'): array
    {
        $formatAmount = $this->formatAmount($amount);
        $token = $this->getToken();
        $path = '/snap/v1.1/qr/qr-mpm-generate';

        $body = [
            'partnerReferenceNo' => $partnerReferenceNo,
            'amount' => ['value' => $formatAmount, 'currency' => $currency],
            'merchantId' => $this->config['qris']['merchant_id'],
            'terminalId' => $this->config['qris']['terminal_id'],
        ];

        $headers = $this->sig->qrisBusinessHeaders($token, $path, $body);

        $QRLogParams = [
            'tenant_id'       => $this->tenantId,
            'reff_id'         => $partnerReferenceNo,
            'amount'          => $formatAmount,
            'status'          => 'PENDING',
            'expired_at'       => now()->addMinutes(15),
            'request_payload' => json_encode($body),

        ];

        $qrLog = $this->findOrCreateQRLog($QRLogParams, $partnerReferenceNo);


        $res = $this->http->post($path, $body, $headers);

        $code = $res['responseCode'] ?? null;
        if ($code === '2004700') {
            $qrLog->update([
                'qris_content' => $res['qrContent']??null,
                'bri_reference_no' => $res['referenceNo']??null,
                'response_payload' => $res,
                'status'           => 'WAITING_PAYMENT',
            ]);
            return $res;
        }

        $qrLog->update(['response_payload' => $res]);

        return $res;
    }

    public function inquiryPayment(string $partnerReferenceNo, string $serviceCode = '17'): array
    {
        $qrLog = $this->findOrCreateQRLog([], $partnerReferenceNo, true);
        $token = $this->getToken();
        $path = '/snap/v1.1/qr/qr-mpm-query';
        $body = [
            'originalReferenceNo' => $qrLog->bri_reference_no??$partnerReferenceNo,
            'serviceCode' => $serviceCode,
            'additionalInfo' => ['terminalId' => $this->config['qris']['terminal_id']],
        ];
        $headers = $this->sig->qrisBusinessHeaders($token, $path, $body);
        $res = $this->http->post($path, $body, $headers);
        return $res;
    }


    public function getLocalPaymentLog(string $partnerReferenceNo): BriQrisPaymentLog
    {
        $clientId = $this->config['common']['client_id'] ?? null;

        $log = BriQrisPaymentLog::where('client_id', $clientId)
            ->where('reff_id', $partnerReferenceNo)
            ->first();

        if(empty($log)){
            throw new \Exception("Reference number not found");
        }

        return $log;
    }
}
