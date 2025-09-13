
<?php

namespace Elgibor\BriQris\Http\Controllers;

use Illuminate\Routing\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Event;
use Elgibor\BriQris\Support\Signature;
use Elgibor\BriQris\Events\QrisPaymentNotified;

class NotificationController extends Controller
{
    public function handle(Request $request, Signature $sig)
    {
        $headers = [
            'Authorization' => $request->header('Authorization'),
            'X-SIGNATURE'   => $request->header('X-SIGNATURE'),
            'X-TIMESTAMP'   => $request->header('X-TIMESTAMP'),
            'X-PARTNER-ID'  => $request->header('X-PARTNER-ID'),
            'X-EXTERNAL-ID' => $request->header('X-EXTERNAL-ID'),
        ];

        // Optional verify if public key is provided
        $clientId = config('bri_qris.client_id');
        $stringToSign = $clientId . '|' . ($headers['X-TIMESTAMP'] ?? '');
        $valid = $sig->verifyRsa($stringToSign, $headers['X-SIGNATURE'] ?? '');

        $payload = $request->all();
        Event::dispatch(new QrisPaymentNotified($payload, $headers, $valid));

        return response()->json([
            'responseCode' => '2005200',
            'responseMessage' => 'Successful',
        ]);
    }
}
