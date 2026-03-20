<?php

namespace Database\Factories\Domain\Audit\Models;

use App\Domain\Audit\Models\ActivityLog;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Domain\Audit\Models\ActivityLog>
 */
class ActivityLogFactory extends Factory
{
    protected $model = ActivityLog::class;

    public function definition(): array
    {
        return [
            'category' => 'auth',
            'event' => 'auth.login_succeeded',
            'description' => 'Example activity log entry.',
            'actor_id' => null,
            'subject_type' => null,
            'subject_id' => null,
            'ip_address' => fake()->ipv4(),
            'user_agent' => fake()->userAgent(),
            'metadata' => [
                'source' => 'factory',
            ],
            'created_at' => now(),
        ];
    }
}
