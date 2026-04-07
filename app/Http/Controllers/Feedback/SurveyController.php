<?php

namespace App\Http\Controllers\Feedback;

use App\Domain\Feedback\Models\Survey;
use App\Domain\Feedback\Models\SurveyResponse;
use App\Domain\Feedback\Services\SurveyScoringService;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SurveyController extends Controller
{
    public function show(Request $request, string $locale, Survey $survey): View|RedirectResponse
    {
        if (! $survey->acceptsResponsesAt()) {
            abort(404);
        }

        if ($survey->requires_auth && ! $request->user()) {
            session(['url.intended' => $request->fullUrl()]);

            return redirect()->route('login');
        }

        return view('surveys.show', [
            'survey' => $survey,
            'alreadySubmitted' => $this->alreadySubmitted($request, $survey),
        ]);
    }

    public function submit(
        Request $request,
        string $locale,
        Survey $survey,
        SurveyScoringService $scoringService,
    ): RedirectResponse {
        if (! $survey->acceptsResponsesAt()) {
            abort(404);
        }

        if ($survey->requires_auth && ! $request->user()) {
            session(['url.intended' => route('surveys.show', [
                'locale' => $locale,
                'survey' => $survey,
            ])]);

            return redirect()->route('login');
        }

        if ($this->alreadySubmitted($request, $survey)) {
            return back()->withErrors([
                'survey' => __('You have already submitted this survey.'),
            ]);
        }

        $validated = $request->validate($scoringService->validationRules($survey));
        $score = $scoringService->score($survey, $validated['answers'] ?? []);

        SurveyResponse::query()->create([
            'survey_id' => $survey->id,
            'user_id' => $request->user()?->id,
            'answers' => $score['answers'],
            'score' => $score['score'],
            'max_score' => $score['max_score'],
            'score_percent' => $score['score_percent'],
            'submitted_at' => now(),
            'locale' => $locale,
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ]);

        $request->session()->put($this->submissionSessionKey($survey), true);

        return redirect()->route('surveys.show', [
            'locale' => $locale,
            'survey' => $survey,
        ])->with('survey_submitted', true);
    }

    private function alreadySubmitted(Request $request, Survey $survey): bool
    {
        if ($survey->allow_multiple_submissions) {
            return false;
        }

        $user = $request->user();
        if ($user && $survey->hasUserResponse($user)) {
            return true;
        }

        if ($this->hasGuestResponseForRequest($request, $survey)) {
            return true;
        }

        return (bool) $request->session()->get($this->submissionSessionKey($survey), false);
    }

    private function submissionSessionKey(Survey $survey): string
    {
        return "survey_submitted.{$survey->id}";
    }

    private function hasGuestResponseForRequest(Request $request, Survey $survey): bool
    {
        if ($request->user()) {
            return false;
        }

        $ipAddress = $request->ip();

        if (blank($ipAddress)) {
            return false;
        }

        $responseQuery = $survey->responses()
            ->whereNull('user_id')
            ->where('ip_address', $ipAddress);

        $userAgent = trim((string) $request->userAgent());

        if ($userAgent !== '') {
            $responseQuery->where('user_agent', $userAgent);
        }

        return $responseQuery->exists();
    }
}
