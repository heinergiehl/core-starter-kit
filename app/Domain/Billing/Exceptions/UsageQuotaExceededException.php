<?php

namespace App\Domain\Billing\Exceptions;

use App\Domain\Billing\Data\UsageQuotaStatus;

class UsageQuotaExceededException extends BillingException
{
    public function __construct(
        string $message = 'Usage quota exceeded.',
        protected ?UsageQuotaStatus $quotaStatus = null,
        string $errorCode = 'BILLING_USAGE_QUOTA_EXCEEDED',
        int $code = 0,
        ?\Throwable $previous = null
    ) {
        parent::__construct($message, $errorCode, $code, $previous);
    }

    public function quotaStatus(): ?UsageQuotaStatus
    {
        return $this->quotaStatus;
    }

    public static function fromStatus(UsageQuotaStatus $quotaStatus): self
    {
        $includedUnits = $quotaStatus->includedUnits ?? 0;
        $remainingUnits = $quotaStatus->remainingUnits ?? 0;

        return new self(
            "Included usage limit reached for meter [{$quotaStatus->meterKey}]. Allowed: {$includedUnits}, remaining: {$remainingUnits}.",
            $quotaStatus
        );
    }
}
