
<?php

namespace ESolution\BriPayments\Events;

class BrivaPaymentNotified
{
    public function __construct(
        public array $payload,
        public array $headers,
        public bool $validSignature
    ){}
}
