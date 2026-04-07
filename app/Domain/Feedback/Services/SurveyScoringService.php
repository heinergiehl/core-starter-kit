<?php

namespace App\Domain\Feedback\Services;

use App\Domain\Feedback\Models\Survey;
use App\Enums\SurveyQuestionType;
use Illuminate\Validation\Rule;

class SurveyScoringService
{
    /**
     * @return array<string, array<int, mixed>>
     */
    public function validationRules(Survey $survey): array
    {
        $rules = [
            'answers' => ['required', 'array'],
        ];

        foreach ($survey->questions ?? [] as $question) {
            $key = (string) ($question['key'] ?? '');
            $type = SurveyQuestionType::tryFrom((string) ($question['type'] ?? ''));

            if ($key === '' || ! $type) {
                continue;
            }

            $required = ! empty($question['required']) ? ['required'] : ['nullable'];
            $ruleKey = "answers.{$key}";

            $rules[$ruleKey] = match ($type) {
                SurveyQuestionType::ShortText => [...$required, 'string', 'max:255'],
                SurveyQuestionType::LongText => [...$required, 'string', 'max:5000'],
                SurveyQuestionType::YesNo => [...$required, Rule::in(['yes', 'no'])],
                SurveyQuestionType::Rating, SurveyQuestionType::Nps => [
                    ...$required,
                    'integer',
                    'min:'.(int) ($question['min_value'] ?? ($type === SurveyQuestionType::Nps ? 0 : 1)),
                    'max:'.(int) ($question['max_value'] ?? ($type === SurveyQuestionType::Nps ? 10 : 5)),
                ],
                SurveyQuestionType::SingleChoice => [
                    ...$required,
                    Rule::in($this->optionValues($question)),
                ],
                SurveyQuestionType::MultipleChoice => [...$required, 'array', 'min:1'],
            };

            if ($type === SurveyQuestionType::MultipleChoice) {
                $rules["{$ruleKey}.*"] = [Rule::in($this->optionValues($question))];
            }
        }

        return $rules;
    }

    /**
     * @param  array<string, mixed>  $answers
     * @return array{answers: array<string, mixed>, score: ?int, max_score: ?int, score_percent: ?float}
     */
    public function score(Survey $survey, array $answers): array
    {
        $normalizedAnswers = [];
        $totalScore = 0;
        $totalMaxScore = 0;

        foreach ($survey->questions ?? [] as $question) {
            $key = (string) ($question['key'] ?? '');
            $type = SurveyQuestionType::tryFrom((string) ($question['type'] ?? ''));

            if ($key === '' || ! $type || ! array_key_exists($key, $answers)) {
                continue;
            }

            $answer = $answers[$key];
            $normalizedAnswers[$key] = $answer;

            [$score, $maxScore] = $this->scoreQuestion($type, $question, $answer);

            if ($score === null || $maxScore === null) {
                continue;
            }

            $weight = $this->questionWeight($question);

            if ($weight === 0) {
                continue;
            }

            $totalScore += $score * $weight;
            $totalMaxScore += $maxScore * $weight;
        }

        if ($totalMaxScore === 0) {
            return [
                'answers' => $normalizedAnswers,
                'score' => null,
                'max_score' => null,
                'score_percent' => null,
            ];
        }

        return [
            'answers' => $normalizedAnswers,
            'score' => $totalScore,
            'max_score' => $totalMaxScore,
            'score_percent' => round(($totalScore / $totalMaxScore) * 100, 2),
        ];
    }

    /**
     * @param  array<string, mixed>  $question
     * @return array{0: ?int, 1: ?int}
     */
    private function scoreQuestion(SurveyQuestionType $type, array $question, mixed $answer): array
    {
        return match ($type) {
            SurveyQuestionType::Rating, SurveyQuestionType::Nps => $this->scoreRangeQuestion($type, $question, $answer),
            SurveyQuestionType::YesNo => [
                (string) $answer === 'yes'
                    ? (int) ($question['yes_score'] ?? 1)
                    : (int) ($question['no_score'] ?? 0),
                max(
                    (int) ($question['yes_score'] ?? 1),
                    (int) ($question['no_score'] ?? 0)
                ),
            ],
            SurveyQuestionType::SingleChoice => $this->scoreSingleChoice($question, $answer),
            SurveyQuestionType::MultipleChoice => $this->scoreMultipleChoice($question, $answer),
            default => [null, null],
        };
    }

    /**
     * @param  array<string, mixed>  $question
     * @return array{0: int, 1: int}
     */
    private function scoreRangeQuestion(SurveyQuestionType $type, array $question, mixed $answer): array
    {
        $minValue = (int) ($question['min_value'] ?? ($type === SurveyQuestionType::Nps ? 0 : 1));
        $maxValue = (int) ($question['max_value'] ?? ($type === SurveyQuestionType::Nps ? 10 : 5));
        $selectedValue = max(min((int) $answer, $maxValue), $minValue);

        return [
            max($selectedValue - $minValue, 0),
            max($maxValue - $minValue, 0),
        ];
    }

    /**
     * @param  array<string, mixed>  $question
     * @return array{0: ?int, 1: ?int}
     */
    private function scoreSingleChoice(array $question, mixed $answer): array
    {
        $options = collect($question['options'] ?? []);
        $selected = $options->firstWhere('value', (string) $answer);

        if (! is_array($selected)) {
            return [null, null];
        }

        $maxScore = (int) $options->max(fn ($option) => (int) data_get($option, 'score', 0));

        return [(int) data_get($selected, 'score', 0), $maxScore];
    }

    /**
     * @param  array<string, mixed>  $question
     * @return array{0: ?int, 1: ?int}
     */
    private function scoreMultipleChoice(array $question, mixed $answer): array
    {
        if (! is_array($answer)) {
            return [null, null];
        }

        $options = collect($question['options'] ?? []);
        $selected = $options->whereIn('value', $answer);
        $score = (int) $selected->sum(fn ($option) => (int) data_get($option, 'score', 0));
        $maxScore = (int) $options
            ->map(fn ($option) => max((int) data_get($option, 'score', 0), 0))
            ->sum();

        return [$score, $maxScore];
    }

    /**
     * @param  array<string, mixed>  $question
     * @return array<int, string>
     */
    private function optionValues(array $question): array
    {
        return collect($question['options'] ?? [])
            ->pluck('value')
            ->filter(fn ($value) => is_string($value) && $value !== '')
            ->values()
            ->all();
    }

    /**
     * @param  array<string, mixed>  $question
     */
    private function questionWeight(array $question): int
    {
        return max(0, (int) ($question['weight'] ?? 1));
    }
}
