<?php

namespace ESolution\BriPayments\Http\Controllers;

use Illuminate\Routing\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Event;
use ESolution\BriPayments\Support\SnapSignature;
use ESolution\BriPayments\Events\QrisPaymentNotified;

class QrisNotificationMultipleTenantController extends Controller
{
    public function handle(Request $request, SnapSignature $sig, $tenant)
    {
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
        Event::dispatch(new QrisPaymentNotified($request->all(), $headers, $valid, $tenant));
        return response()->json([ 'responseCode' => '2005200', 'responseMessage' => 'Successful' , 'additionalInfo'=> $request->additionalInfo??[]]);
    }
}
