<?php

namespace ESolution\BriPayments\Http\Controllers;

use Illuminate\Routing\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Event;
use ESolution\BriPayments\Support\SnapSignature;
use ESolution\BriPayments\Events\QrisPaymentTenantNotified;
use Illuminate\Support\Facades\Validator;

class QrisNotificationMultipleTenantController extends Controller
{
    public function handle(Request $request, $tenant)
    {

        $validator = Validator::make($request->all(), [
            'originalReferenceNo'         => ['required', 'string'],
            'originalPartnerReferenceNo'  => ['required', 'string'],
            'latestTransactionStatus'     => ['nullable', 'string', 'size:2', 'in:00,01,02,03,04,05,06,07'],
            'transactionStatusDesc'       => ['nullable', 'string'],
            'customerNumber'              => ['required', 'string'],
            'accountType'                 => ['nullable', 'string'],
            'destinationAccountName'      => ['required', 'string'],
            'amount'                      => ['required', 'array'],
            'amount.value'                => ['required', 'numeric', 'min:0'],
            'amount.currency'             => ['required', 'string', 'size:3'],
            'bankCode'                    => ['nullable', 'string'],
            'additionalInfo'              => ['nullable', 'array'],
        ]);

        if ($validator->fails()) {
            $errors = $validator->errors()->toArray();
            $firstKey = array_key_first($errors);
            $failedRules = $validator->failed()[$firstKey] ?? [];

            // Tentukan kode error
            if (isset($failedRules['Required'])) {
                // Invalid Mandatory Field
                return response()->json([
                    'responseCode'    => '4005202', // HTTP 400 + Service 52 + 02
                    'responseMessage' => "Invalid Mandatory Field {$firstKey}",
                ], 400);
            }

            // Invalid Format
            return response()->json([
                'responseCode'    => '4005201',
                'responseMessage' => "Invalid Field Format {$firstKey}",
            ], 400);
        }

        $config = BriConfig::for($tenant);
        $sig  = new SnapSignature($config);

        $headers = [
            'Authorization' => $request->header('Authorization'),
            'X-SIGNATURE'   => $request->header('X-SIGNATURE'),
            'X-TIMESTAMP'   => $request->header('X-TIMESTAMP'),
            'X-PARTNER-ID'  => $request->header('X-PARTNER-ID'),
            'X-EXTERNAL-ID' => $request->header('X-EXTERNAL-ID') ?? $request->header('X-EXTRENAL-ID'),
        ];
        $client = $request->client??[];
        $clientId = $client['client_id'] ?? config('bri.common.client_id');
        $stringToSign = $clientId . '|' . ($headers['X-TIMESTAMP'] ?? '');
        $valid = $sig->verifyRsa($stringToSign, $headers['X-SIGNATURE'] ?? '');
        Event::dispatch(new QrisPaymentTenantNotified($request->all(), $headers, $valid, $tenant));
        return response()->json([ 'responseCode' => '2005200', 'responseMessage' => 'Successful' , 'additionalInfo'=> $request->additionalInfo??[]], 200);
    }
}
