<?php

namespace Database\Factories\Domain\Feedback;

use App\Domain\Feedback\Models\FeatureRequest;
use App\Enums\FeatureCategory;
use App\Enums\FeatureStatus;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Domain\Feedback\Models\FeatureRequest>
 */
class FeatureRequestFactory extends Factory
{
    protected $model = FeatureRequest::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'title' => fake()->sentence(4),
            'slug' => fake()->unique()->slug(3),
            'description' => fake()->paragraph(),
            'status' => FeatureStatus::Pending,
            'category' => fake()->randomElement(FeatureCategory::cases()),
            'is_public' => true,
            'votes_count' => 0,
            'released_at' => null,
        ];
    }

    public function planned(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => FeatureStatus::Planned,
        ]);
    }

    public function inProgress(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => FeatureStatus::InProgress,
        ]);
    }

    public function completed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => FeatureStatus::Completed,
            'released_at' => now(),
        ]);
    }

    public function private(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_public' => false,
        ]);
    }
}
