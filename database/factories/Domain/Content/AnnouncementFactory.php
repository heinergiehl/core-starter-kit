<?php

namespace Database\Factories\Domain\Content;

use App\Domain\Content\Models\Announcement;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Domain\Content\Models\Announcement>
 */
class AnnouncementFactory extends Factory
{
    protected $model = Announcement::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'title' => fake()->sentence(4),
            'message' => fake()->sentence(10),
            'type' => fake()->randomElement(['info', 'warning', 'success', 'danger']),
            'link_text' => fake()->optional()->word(),
            'link_url' => fake()->optional()->url(),
            'is_active' => true,
            'starts_at' => null,
            'ends_at' => null,
            'is_dismissible' => true,
        ];
    }

    /**
     * Indicate that the announcement is inactive.
     */
    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => false,
        ]);
    }

    /**
     * Indicate that the announcement is scheduled for the future.
     */
    public function scheduled(): static
    {
        return $this->state(fn (array $attributes) => [
            'starts_at' => now()->addDay(),
            'ends_at' => now()->addWeek(),
        ]);
    }

    /**
     * Indicate that the announcement is a warning.
     */
    public function warning(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => 'warning',
        ]);
    }
}
