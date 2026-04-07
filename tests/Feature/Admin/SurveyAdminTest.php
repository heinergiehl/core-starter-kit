<?php

namespace Tests\Feature\Admin;

use App\Domain\Feedback\Models\Survey;
use App\Domain\Feedback\Models\SurveyResponse;
use App\Filament\Admin\Resources\SurveyResponses\Pages\ViewSurveyResponse;
use App\Filament\Admin\Resources\Surveys\Pages\CreateSurvey;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class SurveyAdminTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_create_a_scored_survey_with_weighted_questions(): void
    {
        $this->authenticateAdmin();

        $component = Livewire::test(CreateSurvey::class)
            ->fillForm([
                'title' => 'Customer Fit Survey',
                'slug' => 'customer-fit-survey',
                'status' => 'published',
                'is_public' => true,
                'allow_multiple_submissions' => false,
                'submit_label' => 'Send feedback',
                'success_title' => 'Thanks for the input',
                'success_message' => 'We use these answers to improve the product.',
            ]);

        $component
            ->set('data.questions', [
                'recommend-question' => [
                    'label' => 'How likely are you to recommend us?',
                    'key' => 'recommend',
                    'type' => 'nps',
                    'required' => true,
                    'weight' => 2,
                    'min_value' => 0,
                    'max_value' => 10,
                    'min_label' => 'Not likely',
                    'max_label' => 'Very likely',
                ],
                'segment-question' => [
                    'label' => 'Which profile fits best?',
                    'key' => 'segment',
                    'type' => 'single_choice',
                    'required' => true,
                    'weight' => 3,
                    'options' => [
                        'founder-option' => [
                            'label' => 'Founder',
                            'value' => 'founder',
                            'score' => 5,
                        ],
                        'agency-option' => [
                            'label' => 'Agency',
                            'value' => 'agency',
                            'score' => 3,
                        ],
                    ],
                ],
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $survey = Survey::query()->first();

        $this->assertNotNull($survey);
        $this->assertSame('customer-fit-survey', $survey->slug);
        $this->assertTrue($survey->is_public);
        $this->assertSame('Thanks for the input', $survey->success_title);
        $this->assertSame(
            ['recommend', 'segment'],
            collect($survey->questions)->pluck('key')->values()->all(),
        );
    }

    public function test_admin_can_review_survey_response_details(): void
    {
        $this->authenticateAdmin();

        $survey = Survey::factory()->create([
            'title' => 'Retention Survey',
        ]);

        $response = SurveyResponse::factory()->create([
            'survey_id' => $survey->id,
            'answers' => [
                'recommend' => 9,
                'segment' => 'founder',
            ],
            'score' => 18,
            'max_score' => 20,
            'score_percent' => 90.0,
            'locale' => 'en',
        ]);

        Livewire::test(ViewSurveyResponse::class, ['record' => $response->getRouteKey()])
            ->assertSeeText('Retention Survey')
            ->assertSeeText('18/20')
            ->assertSeeText('recommend')
            ->assertSeeText('founder');
    }

    private function authenticateAdmin(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);

        $this->actingAs($admin);
        Filament::setCurrentPanel('admin');
        Filament::bootCurrentPanel();
    }
}
