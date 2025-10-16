<?php

namespace ESolution\BriPayments\Http\Controllers;

use Illuminate\Routing\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Event;
use ESolution\BriPayments\Support\NonSnapSignature;
use ESolution\BriPayments\Events\BrivaPaymentTenantNotified;

class BrivaNotificationMultipleTenantController extends Controller
{
    public function handle(Request $request, NonSnapSignature $sig, $tenant)
    {
        $token = $request->header('Authorization');
        $timestamp = $request->header('X-TIMESTAMP');
        $signature = $request->header('X-SIGNATURE');

        $absoluteUrl = $request->fullUrl();
        $bodyRaw = json_encode($request->all(), JSON_UNESCAPED_SLASHES);
        $expected = $sig->buildSignature($absoluteUrl, 'POST', $token, strval($timestamp), $bodyRaw);
        $expectedAlt = $sig->buildSignature($request->getPathInfo(), 'POST', $token, strval($timestamp), $bodyRaw);

        $valid = hash_equals($signature ?? '', $expected) || hash_equals($signature ?? '', $expectedAlt);

        Event::dispatch(new BrivaPaymentTenantNotified($request->all(), [
            'Authorization' => $token,
            'X-TIMESTAMP' => $timestamp,
            'X-SIGNATURE' => $signature,
        ], $valid, $tenant));

        return response()->json([
            'responseCode' => $valid ? '2003400' : '4013401',
            'responseMessage' => $valid ? 'Successful' : 'Unauthorized. Verify Client Secret Fail.',
        ]);
    }
}
