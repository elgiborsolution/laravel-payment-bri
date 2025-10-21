<?php

namespace ESolution\BriPayments\Http\Controllers;

use Illuminate\Routing\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Event;
use ESolution\BriPayments\Support\SnapSignature;
use ESolution\BriPayments\Events\BrivaPaymentTenantNotified;

class BrivaNotificationMultipleTenantController extends Controller
{
    public function handle(Request $request, SnapSignature $sig, $tenant)
    { 
        $token = $request->header('Authorization');
        $timestamp = $request->header('X-TIMESTAMP');
        $signature = $request->header('X-SIGNATURE')??'';
        $token = str_replace('Bearer ', '', $token);
        $absoluteUrl = $request->fullUrl();
        $url = $request->getPathInfo(); 
        $pos = strpos($url, '/snap/');
        $urlSnap = $pos !== false ? substr($url, $pos) : '';
        $bodyRaw = $request->getContent();
        $client = $request->client??[];
        
        $expected = $sig->generateSignature($urlSnap, 'POST', $token, $client['client_secret']??'', $bodyRaw, strval($timestamp));
        $expectedAlt = $sig->generateSignature($url, 'POST', $token, $client['client_secret']??'', $bodyRaw, strval($timestamp));

        $valid = hash_equals($signature, $expected) || hash_equals($signature, $expectedAlt);
        Event::dispatch(new BrivaPaymentTenantNotified($request->all(), [
            'Authorization' => $token,
            'X-TIMESTAMP' => $timestamp,
            'X-SIGNATURE' => $signature,
        ], $valid, $tenant));

        return response()->json([
            'responseCode' => $valid ? '2003400' : '4013401',
            'responseMessage' => $valid ? 'Successful' : 'Unauthorized. Verify Client Secret Fail.',
        ], ($valid ? 200 : 400));
    }
}
