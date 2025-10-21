<?php

namespace ESolution\BriPayments\Http\Middleware;


use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class AuthB2BMiddleware
{
    public function handle(Request $request, Closure $next)
    {
        $token = $request->header('Authorization');

        if (!$token || !str_starts_with($token, 'Bearer ')) {

            return response()->json([
                'responseCode' => '4003401',
                'responseMessage' => 'Invalid Field Format',
            ], 400);
        }

        $token = str_replace('Bearer ', '', $token);
        $tokenData = DB::table('bri_access_tokens')
            ->where('token', $token)
            ->where('expires_at', '>', now())
            ->first();

        if (!$tokenData) {
            return response()->json([
                'responseCode' => '4013400',
                'responseMessage' => 'Unauthorized. Verify Token Auth.',
            ], 401);
        }


        $dataClient = DB::table('bri_clients')
            ->where('id', $tokenData->client_id)
            ->first();

        $request->merge(['client' => (array) $dataClient]);

        return $next($request);
    }
}
