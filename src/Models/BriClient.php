<?php

namespace ESolution\BriPayments\Models;

class BriClient extends BriBaseModel
{
    protected $table = 'bri_clients';

    protected $fillable = [
        'name',
        'tenant_id',
        'client_id',
        'client_secret',
        'public_key',
        'private_key',
        'base_url',
        'qris_partner_id',
        'qris_channel_id',
        'qris_merchant_id',
        'qris_terminal_id',
        'qris_public_key',
        'briva_partner_service_id',
        'briva_partner_id',
        'briva_channel_id',
        'briva_public_key',
    ];
}
