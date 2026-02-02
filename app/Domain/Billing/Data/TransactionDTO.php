<?php

namespace App\Domain\Billing\Data;

class TransactionDTO
{
    public function __construct(
        public string $id,
        public string $url,
        public ?string $status = null
    ) {}
}
