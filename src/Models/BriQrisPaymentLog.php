<?php

namespace ESolution\BriPayments\Models;

class BriQrisPaymentLog extends BriBaseModel
{
    protected $table = 'bri_qris_payment_logs';
    protected $primaryKey = 'id';
    public $incrementing = true;

    protected $fillable = [
        'client_id',
        'tenant_id',
        'reff_id',
        'qris_invoice_no',
        'qris_content',
        'amount',
        'status',
        'bri_reference_no',
        'request_payload',
        'response_payload',
        'callback_payload',
        'expired_at',
        'paid_at',
    ];

    protected $casts = [
        'request_payload' => 'array',
        'response_payload' => 'array',
        'callback_payload' => 'array',
        'expired_at'       => 'datetime',
        'paid_at'          => 'datetime',
        'amount'           => 'decimal:2',
    ];

}
