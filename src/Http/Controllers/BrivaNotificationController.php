<?php

namespace ESolution\BriPayments\Http\Controllers;

use Illuminate\Routing\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Event;
use ESolution\BriPayments\Support\NonSnapSignature;
use ESolution\BriPayments\Events\BrivaPaymentNotified;

class BrivaNotificationController extends Controller
{
    public function handle(Request $request, NonSnapSignature $sig)
    {
        $token = $request->header('Authorization');
        $timestamp = $request->header('BRI-Timestamp');
        $signature = $request->header('BRI-Signature');

        $absoluteUrl = $request->fullUrl();
        $bodyRaw = json_encode($request->all(), JSON_UNESCAPED_SLASHES);
        $expected = $sig->buildSignature($absoluteUrl, 'POST', $token, $timestamp, $bodyRaw);
        $expectedAlt = $sig->buildSignature($request->getPathInfo(), 'POST', $token, $timestamp, $bodyRaw);

        $valid = hash_equals($signature ?? '', $expected) || hash_equals($signature ?? '', $expectedAlt);

        Event::dispatch(new BrivaPaymentNotified($request->all(), [
            'Authorization' => $token,
            'BRI-Timestamp' => $timestamp,
            'BRI-Signature' => $signature,
        ], $valid));

        return response()->json([
            'responseCode' => $valid ? '0000' : '0102',
            'responseDescription' => $valid ? 'Success' : 'Invalid Signature',
        ]);
    }
}
