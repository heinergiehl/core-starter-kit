<?php

namespace App\Domain\Organization\Enums;

enum TeamRole: string
{
    case Owner = 'owner';
    case Admin = 'admin';
    case Member = 'member';
    case Billing = 'billing';
}
