<?php

namespace ESolution\BriPayments\Models;

class BriVaPaymentLog extends BriBaseModel
{
    protected $table = 'bri_va_payment_logs';

    protected $fillable = [
        'client_id',
        'tenant_id',
        'reff_id',
        'customer_no',
        'customer_name',
        'bri_va_number',
        'amount',
        'status',
        'external_id',
        'expired_at',
        'request_payload',
        'response_payload',
        'callback_payload',
        'paid_at',
    ];

    protected $casts = [
        'request_payload' => 'array',
        'response_payload' => 'array',
        'callback_payload' => 'array',
        'expired_at' => 'datetime',
        'paid_at' => 'datetime',
        'amount' => 'decimal:2',
    ];
}
