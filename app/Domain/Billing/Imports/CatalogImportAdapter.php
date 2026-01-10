<?php

namespace App\Domain\Billing\Imports;

interface CatalogImportAdapter
{
    public function provider(): string;

    /**
     * @return array{
     *     items: array<int, array<string, mixed>>,
     *     warnings: array<int, string>
     * }
     */
    public function fetch(): array;
}
