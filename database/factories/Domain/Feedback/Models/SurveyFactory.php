<?php

namespace Database\Factories\Domain\Feedback\Models;

use App\Domain\Feedback\Models\Survey;
use App\Enums\SurveyStatus;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<Survey>
 */
class SurveyFactory extends Factory
{
    protected $model = Survey::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'title' => fake()->sentence(3),
            'slug' => fake()->unique()->slug(3),
            'description' => fake()->sentence(),
            'status' => SurveyStatus::Published,
            'is_public' => true,
            'requires_auth' => false,
            'allow_multiple_submissions' => false,
            'submit_label' => 'Submit',
            'success_title' => 'Thanks for your feedback',
            'success_message' => 'Your response has been recorded.',
            'starts_at' => now()->subDay(),
            'ends_at' => now()->addWeek(),
            'questions' => [
                [
                    'key' => 'recommend',
                    'type' => 'nps',
                    'label' => 'How likely are you to recommend this?',
                    'required' => true,
                    'min_value' => 0,
                    'max_value' => 10,
                ],
            ],
        ];
    }
}
