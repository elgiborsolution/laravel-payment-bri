<?php

namespace ESolution\BriPayments\Events;

class QrisPaymentNotified
{
    public function __construct(
        public array $payload,
        public array $headers,
        public bool $validSignature
    ){}
}
