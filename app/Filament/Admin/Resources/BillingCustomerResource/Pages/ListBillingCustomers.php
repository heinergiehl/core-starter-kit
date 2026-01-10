<?php

namespace App\Filament\Admin\Resources\BillingCustomerResource\Pages;

use App\Filament\Admin\Resources\BillingCustomerResource;
use Filament\Resources\Pages\ListRecords;

class ListBillingCustomers extends ListRecords
{
    protected static string $resource = BillingCustomerResource::class;
}
