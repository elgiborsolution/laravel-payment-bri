
<?php

return [
    'base_url'      => env('BRI_QRIS_BASE_URL', 'https://sandbox.partner.api.bri.co.id'),
    'client_id'     => env('BRI_QRIS_CLIENT_ID'),
    'client_secret' => env('BRI_QRIS_CLIENT_SECRET'),
    'private_key_path' => env('BRI_QRIS_PRIVATE_KEY_PATH'),
    'public_key_path'  => env('BRI_QRIS_PUBLIC_KEY_PATH'),
    'partner_id'    => env('BRI_QRIS_PARTNER_ID'),
    'channel_id'    => env('BRI_QRIS_CHANNEL_ID', '95221'),
    'merchant_id'   => env('BRI_QRIS_MERCHANT_ID'),
    'terminal_id'   => env('BRI_QRIS_TERMINAL_ID'),
    'timeout'       => env('BRI_QRIS_TIMEOUT', 30),
    'notify_route'  => [
        'enabled' => true,
        'uri'     => 'bri/qris/notify',
        'middleware' => ['api'],
    ],
];
