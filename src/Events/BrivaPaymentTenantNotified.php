<?php

namespace ESolution\BriPayments\Events;

class BrivaPaymentTenantNotified
{
    public function __construct(
        public array $payload,
        public array $headers,
        public bool $validSignature,
        public string $tenant,
    ){}
}
