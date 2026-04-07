<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HealthCheckTest extends TestCase
{
    use RefreshDatabase;

    public function test_health_check_returns_healthy_status(): void
    {
        $response = $this->getJson('/up');

        $response->assertOk()
            ->assertJsonStructure([
                'status',
                'checks' => ['database', 'cache'],
                'timestamp',
            ])
            ->assertJson([
                'status' => 'healthy',
                'checks' => [
                    'database' => true,
                    'cache' => true,
                ],
            ]);
    }

    public function test_health_check_is_publicly_accessible(): void
    {
        $this->getJson('/up')->assertOk();
    }
}
