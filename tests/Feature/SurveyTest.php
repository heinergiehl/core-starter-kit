<?php

namespace Tests\Feature;

use App\Domain\Feedback\Models\Survey;
use App\Domain\Feedback\Models\SurveyResponse;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SurveyTest extends TestCase
{
    use RefreshDatabase;

    public function test_public_survey_renders(): void
    {
        $survey = Survey::factory()->create([
            'slug' => 'product-fit',
            'questions' => [
                [
                    'key' => 'recommend',
                    'type' => 'nps',
                    'label' => 'How likely are you to recommend us?',
                    'required' => true,
                    'min_value' => 0,
                    'max_value' => 10,
                ],
            ],
        ]);

        $response = $this->get(route('surveys.show', ['locale' => 'en', 'survey' => $survey]));

        $response->assertStatus(200);
        $response->assertSee('How likely are you to recommend us?');
    }

    public function test_guest_can_submit_public_survey_and_score_is_stored(): void
    {
        $survey = Survey::factory()->create([
            'questions' => [
                [
                    'key' => 'recommend',
                    'type' => 'nps',
                    'label' => 'How likely are you to recommend us?',
                    'required' => true,
                    'min_value' => 0,
                    'max_value' => 10,
                ],
                [
                    'key' => 'segment',
                    'type' => 'single_choice',
                    'label' => 'Which best describes you?',
                    'required' => true,
                    'options' => [
                        ['label' => 'Founder', 'value' => 'founder', 'score' => 5],
                        ['label' => 'Hobbyist', 'value' => 'hobbyist', 'score' => 2],
                    ],
                ],
            ],
        ]);

        $response = $this->post(route('surveys.submit', ['locale' => 'en', 'survey' => $survey]), [
            'answers' => [
                'recommend' => 8,
                'segment' => 'founder',
            ],
        ]);

        $response->assertRedirect(route('surveys.show', ['locale' => 'en', 'survey' => $survey]));
        $this->assertDatabaseHas('survey_responses', [
            'survey_id' => $survey->id,
            'score' => 13,
            'max_score' => 15,
        ]);
    }

    public function test_weighted_question_scores_are_applied_to_survey_totals(): void
    {
        $survey = Survey::factory()->create([
            'questions' => [
                [
                    'key' => 'onboarding',
                    'type' => 'rating',
                    'label' => 'How strong was the onboarding?',
                    'required' => true,
                    'weight' => 2,
                    'min_value' => 1,
                    'max_value' => 5,
                    'min_label' => 'Weak',
                    'max_label' => 'Excellent',
                ],
                [
                    'key' => 'ideal_customer',
                    'type' => 'yes_no',
                    'label' => 'Does this feel like a strong fit?',
                    'required' => true,
                    'weight' => 3,
                    'yes_score' => 4,
                    'no_score' => 0,
                ],
            ],
        ]);

        $response = $this->post(route('surveys.submit', ['locale' => 'en', 'survey' => $survey]), [
            'answers' => [
                'onboarding' => 4,
                'ideal_customer' => 'yes',
            ],
        ]);

        $response->assertRedirect(route('surveys.show', ['locale' => 'en', 'survey' => $survey]));
        $this->assertDatabaseHas('survey_responses', [
            'survey_id' => $survey->id,
            'score' => 18,
            'max_score' => 20,
        ]);
    }

    public function test_auth_required_survey_redirects_guests_to_login(): void
    {
        $survey = Survey::factory()->create([
            'requires_auth' => true,
        ]);

        $response = $this->get(route('surveys.show', ['locale' => 'en', 'survey' => $survey]));

        $response->assertRedirect(route('login'));
    }

    public function test_authenticated_user_can_only_submit_once_when_multiple_submissions_are_disabled(): void
    {
        $survey = Survey::factory()->create([
            'allow_multiple_submissions' => false,
        ]);
        $user = User::factory()->create();

        SurveyResponse::factory()->create([
            'survey_id' => $survey->id,
            'user_id' => $user->id,
        ]);

        $response = $this->actingAs($user)->post(route('surveys.submit', ['locale' => 'en', 'survey' => $survey]), [
            'answers' => [
                'recommend' => 9,
            ],
        ]);

        $response->assertSessionHasErrors('survey');
        $this->assertSame(1, SurveyResponse::query()->where('survey_id', $survey->id)->count());
    }

    public function test_guest_cannot_submit_twice_from_the_same_device_when_multiple_submissions_are_disabled(): void
    {
        $survey = Survey::factory()->create([
            'allow_multiple_submissions' => false,
        ]);

        $server = [
            'REMOTE_ADDR' => '203.0.113.10',
            'HTTP_USER_AGENT' => 'ShipSolid Browser Test',
        ];

        $this->withServerVariables($server)->post(route('surveys.submit', ['locale' => 'en', 'survey' => $survey]), [
            'answers' => [
                'recommend' => 9,
            ],
        ])->assertRedirect(route('surveys.show', ['locale' => 'en', 'survey' => $survey]));

        $this->app['session']->flush();

        $response = $this->withServerVariables($server)->post(route('surveys.submit', ['locale' => 'en', 'survey' => $survey]), [
            'answers' => [
                'recommend' => 8,
            ],
        ]);

        $response->assertSessionHasErrors('survey');
        $this->assertSame(1, SurveyResponse::query()->where('survey_id', $survey->id)->count());
    }
}
