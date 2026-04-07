<?php

namespace App\Enums;

enum UsageLimitBehavior: string
{
    case BillOverage = 'bill_overage';
    case Block = 'block';

    public function label(): string
    {
        return match ($this) {
            self::BillOverage => 'Bill overages',
            self::Block => 'Block at limit',
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::BillOverage => 'Charge a recurring base fee, include usage in the plan, and bill overages when the allowance is exceeded.',
            self::Block => 'Charge a recurring base fee with included usage and stop new usage once the allowance is exhausted until the next cycle or an upgrade.',
        };
    }

    public function allowsOverageBilling(): bool
    {
        return $this === self::BillOverage;
    }

    public function blocksUsage(): bool
    {
        return $this === self::Block;
    }
}
