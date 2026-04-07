<?php

namespace Database\Factories\Domain\Feedback\Models;

use App\Domain\Feedback\Models\Survey;
use App\Domain\Feedback\Models\SurveyResponse;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<SurveyResponse>
 */
class SurveyResponseFactory extends Factory
{
    protected $model = SurveyResponse::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'survey_id' => Survey::factory(),
            'user_id' => User::factory(),
            'answers' => ['recommend' => 8],
            'score' => 8,
            'max_score' => 10,
            'score_percent' => 80,
            'submitted_at' => now(),
            'locale' => 'en',
            'ip_address' => '127.0.0.1',
            'user_agent' => 'PHPUnit',
        ];
    }
}
