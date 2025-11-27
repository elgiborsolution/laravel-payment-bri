<?php

namespace ESolution\BriPayments\Briva;

use ESolution\BriPayments\Support\HttpClient;
use ESolution\BriPayments\Support\SnapSignature;
use ESolution\BriPayments\Support\BriConfig;
use ESolution\BriPayments\Models\BriVaPaymentLog;
use RuntimeException;
use InvalidArgumentException;
use Illuminate\Support\Facades\Cache;
use Carbon\Carbon;

class BrivaSnapClient
{
    protected HttpClient $http;
    protected SnapSignature $sig;
    protected array $config;
    protected string $tenantId;

    public function __construct(string $tenantId = 'default')
    {
        $this->tenantId = $tenantId;

        $this->config = BriConfig::for($tenantId);

        $this->http = new HttpClient($this->config);
        $this->sig  = new SnapSignature($this->config);
    }

    public function getToken($force = false): string
    {
        $tenantId = $this->tenantId;

        if ($force) {
            Cache::forget('bri_snap_va_token-' . $tenantId);
        }

        return Cache::remember('bri_snap_va_token-' . $tenantId, now()->addSeconds(700), function () use($tenantId) {
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

            Cache::put('bri_snap_va_token-' . $tenantId, $token, now()->addSeconds($expires));

            return $token;
        });
    }

    public function generateCustomerNo(): string
    {
        $tenantId = $this->tenantId;
        $cacheKey = "briva_last_customer_no_{$tenantId}";

        // 1. Cek VA yang belum dipakai
        $unused = BriVaPaymentLog::where('status', 'PENDING')
            ->when($tenantId, fn($q) => $q->where('tenant_id', $tenantId))
            ->orderBy('id', 'ASC')
            ->value('customer_no');

        if ($unused) {
            return str_pad((int)$unused, 13, '0', STR_PAD_LEFT);
        }

        // 2. Ambil dari cache
        $last = Cache::get($cacheKey);

        if (!$last) {
            $last = BriVaPaymentLog::when($tenantId, fn($q) => $q->where('tenant_id', $tenantId))
                ->orderBy('id', 'DESC')
                ->value('customer_no');

            $last = $last ? (int)$last : 0;
        }

        $next = $last + 1;
        $customerNo = str_pad($next, 13, '0', STR_PAD_LEFT);

        Cache::put($cacheKey, $next, 86400);

        return $customerNo;
    }

    private function findOrCreateVaLog(array $params, string $customerNo): BriVaPaymentLog
    {
        $clientId = $this->config['common']['client_id'] ?? null;

        // Cek apakah sudah ada
        $log = BriVaPaymentLog::where('client_id', $clientId)
            ->where('customer_no', $customerNo)
            ->first();

        if ($log) {
            // Update existing record
            $log->update($params);
            return $log->refresh(); // agar data terbaru terambil
        }

        // Belum ada → insert baru
        $params = array_merge($params, [
            'client_id'   => $clientId,
            'customer_no' => $customerNo,
        ]);

        return BriVaPaymentLog::create($params);
    }


    private function findVa(string $va): BriVaPaymentLog
    {
        // Remove any whitespaces from input
        $cleanVa = str_replace(' ', '', $va);

        // Query VA by removing spaces on both sides (DB + input)
        $vaLog = BriVaPaymentLog::whereRaw("REPLACE(bri_va_number, ' ', '') = ?", [$cleanVa])
            ->first();

        // Throw error if VA is not found
        if (! $vaLog) {
            throw new RuntimeException("VA not found: {$va}");
        }

        return $vaLog;
    }


     private function getCustomerNoFromVa(string $partnerId, string $va): string
    {
        if (!str_starts_with($va, $partnerId)) {
            throw new InvalidArgumentException("VA ($va) tidak diawali partner_id ($partnerId)");
        }

        return substr($va, strlen($partnerId));
    }

    private function parseSnapTimestamp(?string $timestamp): string
    {
        // Validate null or empty value
        if ($timestamp === null || trim($timestamp) === '') {
            throw new InvalidArgumentException("Timestamp cannot be null or empty.");
        }

        $format = 'Y-m-d\TH:i:sP';

        // Strict parsing based on the expected format
        $dt = Carbon::createFromFormat($format, $timestamp);

        // Ensure the parsed result exactly matches the original string (strict check)
        if (! $dt || $dt->format($format) !== $timestamp) {

            // Generate a dynamic example for proper format reference
            $example = Carbon::now()->format($format);

            throw new InvalidArgumentException(
                "Invalid timestamp format. Expected: {$format}. Example: {$example}"
            );
        }

        // Validate that the timestamp is at least 15 minutes in the future
        $minAllowed = Carbon::now()->addMinutes(15);

        if ($dt->lt($minAllowed)) {
            throw new InvalidArgumentException(
                "Timestamp must be at least 15 minutes in the future. Minimum allowed: " .
                $minAllowed->format($format)
            );
        }

        return $dt->format($format);
    }



    public function createVa(array $params, string $amount, string $currency = 'IDR'): array
    {
        $tenantId = $this->tenantId;
        $token = $this->getToken();

        $path = '/snap/v1.0/transfer-va/create-va';
        $partnerServiceId ='   '. $this->config['briva']['partner_service_id'];
        $customerNo = $this->generateCustomerNo();

        $virtualAccountNo = $partnerServiceId . $customerNo;

        $body = [
            'partnerServiceId' => $partnerServiceId,
            'customerNo'       => $customerNo,
            'virtualAccountNo' => $virtualAccountNo,
            'virtualAccountName'=> $params['virtualAccountName'],
            'totalAmount'      => ['value' => $amount, 'currency' => $currency],
            'expiredDate'      => now()->addHours(24)->format('Y-m-d\TH:i:sP'),
            'trxId'            => $params['trxId'] ?? $customerNo,
            'additionalInfo'   => ['description' => $params['description'] ?? '-'],
        ];

        $headers = $this->sig->brivaBusinessHeaders($token, $path, $body);
        $vaLogParams = [
            'tenant_id'       => $this->tenantId,
            'reff_id'         => $params['trxId'] ?? $customerNo,
            'customer_name'   => $params['virtualAccountName'] ?? null,
            'bri_va_number'   => $virtualAccountNo,
            'amount'          => $amount,
            'status'          => 'PENDING',
            'external_id'     => $headers['X-EXTERNAL-ID'],
            'expired_at'      => now()->addHours(24),
            'request_payload' => json_encode($body),
        ];

        $vaLog = $this->findOrCreateVaLog($vaLogParams, $customerNo);

        // --- Call API ---
        $res = $this->http->post($path, $body, $headers);

        // Simpan response
        $vaLog->update(['response_payload' => $res]);

        $code = $res['responseCode'] ?? null;

        if ($code === '2002700') {
            $vaLog->update([
                'status'           => 'WAITING_PAYMENT',
            ]);
            return $res;
        }

        if ($code === '4042712') {
            $vaLog->update(['status' => 'FAILED']);
            return $this->createVa($params, $amount, $currency);
        }

        // ERROR lain → hapus cache nomor terakhir agar regenerate
        Cache::forget("briva_last_customer_no_{$tenantId}");

        return $res;
    }
     /**
     * Update VA
     * PUT /snap/v1.0/transfer-va/update-va :contentReference[oaicite:2]{index=2}
     */
    public function updateVa(string $virtualAccountNo, array $params, string $amount, string $currency = 'IDR'): array
    {
        
        $params['expiredDate'] = $this->parseSnapTimestamp($params['expiredDate']);

        $cleanVa = str_replace(' ', '', $virtualAccountNo);


        $params['customerNo'] = $this->getCustomerNoFromVa($this->config['briva']['partner_service_id'], $cleanVa);

        $token = $this->getToken();
        $path = '/snap/v1.0/transfer-va/update-va';

        $body = [
            'partnerServiceId'   => '   '.$this->config['briva']['partner_service_id'],
            'customerNo'         => $params['customerNo'],
            'virtualAccountNo'   => '   '.$cleanVa,
            'virtualAccountName' => $params['virtualAccountName'],
            'trxId'              => $params['trxId'],
            'totalAmount'        => [
                'value'    => $amount,
                'currency' => $currency,
            ],
            'expiredDate'        => $params['expiredDate'],
            'additionalInfo'     => ['description' => $params['description'] ?? '-'],
        ];

        $headers = $this->sig->brivaBusinessHeaders($token, $path, $body, 'PUT');
        //update log local        
        $vaLogParams = [
            'tenant_id'       => $this->tenantId,
            'reff_id'         => $params['trxId'] ?? $params['customerNo'],
            'customer_name'   => $params['virtualAccountName'] ?? null,
            'bri_va_number'   => '   '.$cleanVa,
            'amount'          => $amount,
            'status'          => 'PENDING',
            'external_id'     => $headers['X-EXTERNAL-ID'],
            'expired_at'      => Carbon::parse($params['expiredDate'])->format('Y-m-d H:i:s'),
            'request_payload' => json_encode($body),
        ];

        $vaLog = $this->findOrCreateVaLog($vaLogParams, $params['customerNo']);

        // --- Call API ---
        $res = $this->http->put($path, $body, $headers);

        // Simpan response
        $vaLog->update(['response_payload' => $res]);

        $code = $res['responseCode'] ?? null;

        if ($code === '2002800') {
            $vaLog->update([
                'status'           => 'WAITING_PAYMENT',
            ]);
            return $res;
        }

        throw new RuntimeException($res['responseMessage']);
        if ($code === '4042812') {
            $vaLog->update(['status' => 'PENDING']);
        }else{

            $vaLog->update(['status' => 'FAILED']);
        }

        return $res;
    }

    /**
     * Update Status VA (Inquiry status)
     * PUT /snap/v1.0/transfer-va/update-status :contentReference[oaicite:3]{index=3}
     */
    public function updateStatus(string $virtualAccountNo, string $status): array
    {
        // Validate status value
        // Only "N" (Not Paid) or "Y" (Paid) are allowed
        if (!in_array($status, ['N', 'Y'], true)) {
            throw new \InvalidArgumentException('Invalid status value. Only "N" or "Y" are allowed.');
        }

        // Clean VA input (remove spaces)
        $cleanVa = str_replace(' ', '', $virtualAccountNo);

        // Retrieve VA data, throw error if not found
        $dataVa = $this->findVa($cleanVa);
        $token = $this->getToken();
        $path = '/snap/v1.0/transfer-va/update-status';

        // Prepare request body for BRIVA API
        $body = [
            'partnerServiceId' => '   ' . $this->config['briva']['partner_service_id'],
            'customerNo'       => $dataVa->customer_no,
            'virtualAccountNo' => '   ' . $cleanVa,
            'trxId'            => $dataVa->reff_id,
            'paidStatus'       => $status,
        ];

        // Sign header for BRIVA request
        $headers = $this->sig->brivaBusinessHeaders($token, $path, $body, 'PUT');

        // Execute API request
        return $this->http->put($path, $body, $headers);
    }


    /**
     * Inquiry VA
     * POST /snap/v1.0/transfer-va/inquiry-va :contentReference[oaicite:4]{index=4}
     * Response additionalInfo biasanya object → decode
     */
    public function inquiryVa(string $virtualAccountNo): array
    {
        // Clean VA input (remove spaces)
        $cleanVa = str_replace(' ', '', $virtualAccountNo);

        // Retrieve VA data, throw error if not found
        $dataVa = $this->findVa($cleanVa);
        $token = $this->getToken();
        $path = '/snap/v1.0/transfer-va/inquiry-va';

        // Prepare request body for BRIVA API
        $body = [
            'partnerServiceId' => '   ' . $this->config['briva']['partner_service_id'],
            'customerNo'       => $dataVa->customer_no,
            'virtualAccountNo' => '   ' . $cleanVa,
            'trxId'            => $dataVa->reff_id,
        ];

        $headers = $this->sig->brivaBusinessHeaders($token, $path, $body, 'POST');
        $res = $this->http->post($path, $body, $headers);

        return $res;
    }

    /**
     * Delete VA
     * DELETE /snap/v1.0/transfer-va/delete-va :contentReference[oaicite:5]{index=5}
     */
    public function deleteVa(array $params): array
    {

        $cleanVa = str_replace(' ', '', $virtualAccountNo);

        $dataVa = $this->findVa($cleanVa);
        $token = $this->getToken();
        $path = '/snap/v1.0/transfer-va/delete-va';

        // Prepare request body for BRIVA API
        $body = [
            'partnerServiceId' => '   ' . $this->config['briva']['partner_service_id'],
            'customerNo'       => $dataVa->customer_no,
            'virtualAccountNo' => '   ' . $cleanVa,
            'trxId'            => $dataVa->reff_id,
        ];

        $headers = $this->sig->brivaBusinessHeaders($token, $path, $body, 'DELETE');
        $res = $this->http->delete($path, json_encode($body), $headers);

        $code = $res['responseCode'] ?? null;
        if ($code === '2003100') {
            $dataVa->update([
                'status'           => 'CANCELED',
            ]);
            return $res;
        }
        return $res;
    }

    /**
     * Inquiry VA Payment Status
     * POST /snap/v1.0/transfer-va/status :contentReference[oaicite:6]{index=6}
     * Response additionalInfo wajib punya "notes"
     */
    public function inquiryStatus(string $virtualAccountNo): array
    {
        $cleanVa = str_replace(' ', '', $virtualAccountNo);
        $dataVa = $this->findVa($cleanVa);

        $token = $this->getToken();
        $path = '/snap/v1.0/transfer-va/status';

        $body = [
            'partnerServiceId' => '   ' . $this->config['briva']['partner_service_id'],
            'customerNo'       => $dataVa->customer_no,
            'virtualAccountNo' => '   ' . $cleanVa,
            'inquiryRequestId' => str_pad((string)random_int(0, 99999), 5, '0', STR_PAD_LEFT),
        ];


        $headers = $this->sig->brivaBusinessHeaders($token, $path, $body, 'POST');
        $res = $this->http->post($path, $body, $headers);


        return $res;
    }

    /**
     * Inquiry VA Payment Status
     * POST /snap/v1.0/transfer-va/status :contentReference[oaicite:6]{index=6}
     * Response additionalInfo wajib punya "notes"
     */
    public function getReport(string $date): array
    {

        // Strict regex: 4 digit tahun, 2 digit bulan, 2 digit hari
        if (! preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
            $example = date('Y-m-d');
            throw new InvalidArgumentException(
                "Invalid date format. Expected: YYYY-MM-DD. Example: {$example}"
            );
        }

        // Split value
        [$year, $month, $day] = explode('-', $date);

        // Check valid calendar date
        if (! checkdate((int)$month, (int)$day, (int)$year)) {
            throw new InvalidArgumentException("Invalid date value: {$date}");
        }

        $token = $this->getToken();
        $path = '/snap/v1.0/transfer-va/report';

        $body = [
            'partnerServiceId' => '   ' . $this->config['briva']['partner_service_id'],
            'startDate'       => $date,
            'startTime' => '00:00:00+07:00',
            'endTime' => '23:59:59+07:00',
        ];


        $headers = $this->sig->brivaBusinessHeaders($token, $path, $body, 'POST');
        $res = $this->http->post($path, $body, $headers);


        return $res;
    }


    public function getLocalPaymentLog(string $trxID): BriVaPaymentLog
    {

        $clientId = $this->config['common']['client_id'] ?? null;
        $vaLog = BriVaPaymentLog::where('client_id', $clientId)
        ->where("reff_id", $trxID)
        ->orderBy('id', 'desc')
        ->first();

        if(empty($vaLog)){
            throw new \Exception("Reference number not found");
        }

        return $vaLog;
    }
}
