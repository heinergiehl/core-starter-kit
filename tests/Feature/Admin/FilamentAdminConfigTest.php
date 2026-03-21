<?php

namespace Tests\Feature\Admin;

use Tests\TestCase;

class FilamentAdminConfigTest extends TestCase
{
    public function test_filament_loading_indicators_are_shown_without_delay(): void
    {
        $this->assertSame('none', config('filament.livewire_loading_delay'));
    }
}
