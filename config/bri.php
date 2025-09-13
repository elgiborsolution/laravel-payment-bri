
<?php
return [
    'base_url' => env('BRI_BASE_URL', 'https://sandbox.partner.api.bri.co.id'),
    'common' => [
        'client_id'     => env('BRI_CLIENT_ID'),
        'client_secret' => env('BRI_CLIENT_SECRET'),
    ],
    'qris' => [
        'partner_id'  => env('BRI_SNAP_PARTNER_ID'),
        'channel_id'  => env('BRI_SNAP_CHANNEL_ID', '95221'),
        'merchant_id' => env('BRI_SNAP_MERCHANT_ID'),
        'terminal_id' => env('BRI_SNAP_TERMINAL_ID'),
        'private_key_path' => env('BRI_SNAP_PRIVATE_KEY_PATH'),
        'public_key_path'  => env('BRI_SNAP_PUBLIC_KEY_PATH'),
        'timeout'     => env('BRI_SNAP_TIMEOUT', 30),
        'notify' => ['enabled' => true,'uri' => 'bri/qris/notify','middleware' => ['api'],],
    ],
    'briva' => [
        'institution_code' => env('BRI_BRIVA_INSTITUTION_CODE'),
        'briva_no'         => env('BRI_BRIVA_NUMBER'),
        'timeout'          => env('BRI_BRIVA_TIMEOUT', 30),
        'notify' => ['enabled' => true,'uri' => 'bri/briva/notify','middleware' => ['api'],],
    ],
];
