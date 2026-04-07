@extends('layouts.marketing')

@section('title', $survey->title . ' - ' . ($appBrandName ?? config('app.name', 'SaaS Kit')))
@section('meta_description', $survey->description ?: __('Share your feedback through this survey.'))

@php
    use App\Enums\SurveyQuestionType;
@endphp

@section('content')
    <section class="py-12">
        <div class="mx-auto max-w-4xl">
            <div class="glass-panel rounded-[32px] px-6 py-8 md:px-10 md:py-10">
                <div class="flex flex-col gap-4">
                    <div class="inline-flex w-fit items-center gap-2 rounded-full border border-primary/20 bg-primary/10 px-3 py-1 text-xs font-semibold uppercase tracking-[0.2em] text-primary">
                        {{ __('Survey') }}
                    </div>
                    <div class="space-y-3">
                        <h1 class="font-display text-4xl font-bold text-ink sm:text-5xl">{{ $survey->title }}</h1>
                        @if ($survey->description)
                            <p class="max-w-3xl text-base text-ink/65 sm:text-lg">{{ $survey->description }}</p>
                        @endif
                    </div>
                </div>

                @if ($alreadySubmitted || session('survey_submitted'))
                    <div class="mt-8 rounded-3xl border border-emerald-500/20 bg-emerald-500/10 px-6 py-8 text-center">
                        <h2 class="font-display text-2xl font-bold text-ink">
                            {{ $survey->success_title ?: __('Thanks for your feedback') }}
                        </h2>
                        <p class="mt-3 text-sm text-ink/65 sm:text-base">
                            {{ $survey->success_message ?: __('Your response has been recorded.') }}
                        </p>
                    </div>
                @else
                    @if ($errors->has('survey'))
                        <div class="mt-8 rounded-2xl border border-rose-500/20 bg-rose-500/10 px-4 py-3 text-sm text-rose-600">
                            {{ $errors->first('survey') }}
                        </div>
                    @endif

                    <form method="POST" action="{{ route('surveys.submit', ['locale' => request()->route('locale') ?? app()->getLocale(), 'survey' => $survey]) }}" class="mt-8 space-y-8" data-submit-lock>
                        @csrf

                        @foreach ($survey->questions ?? [] as $question)
                            @php
                                $key = (string) ($question['key'] ?? '');
                                $type = SurveyQuestionType::tryFrom((string) ($question['type'] ?? ''));
                                $options = is_array($question['options'] ?? null) ? $question['options'] : [];
                                $oldValue = old("answers.{$key}");
                                $minValue = (int) ($question['min_value'] ?? ($type === SurveyQuestionType::Nps ? 0 : 1));
                                $maxValue = (int) ($question['max_value'] ?? ($type === SurveyQuestionType::Nps ? 10 : 5));
                                $rangeGridClasses = $type === SurveyQuestionType::Nps
                                    ? 'grid grid-cols-5 gap-2 sm:grid-cols-6 lg:grid-cols-11'
                                    : 'grid grid-cols-5 gap-2';
                                $usesGroupedInputs = in_array($type, [
                                    SurveyQuestionType::YesNo,
                                    SurveyQuestionType::SingleChoice,
                                    SurveyQuestionType::MultipleChoice,
                                    SurveyQuestionType::Rating,
                                    SurveyQuestionType::Nps,
                                ], true);
                            @endphp

                            @if ($key !== '' && $type)
                                <div class="rounded-[28px] border border-ink/10 bg-surface/40 p-6">
                                    <div class="space-y-2">
                                        @if ($usesGroupedInputs)
                                            <p id="question-label-{{ $key }}" class="block text-lg font-semibold text-ink">
                                                {{ $question['label'] ?? $key }}
                                                @if (!empty($question['required']))
                                                    <span class="text-primary">*</span>
                                                @endif
                                            </p>
                                        @else
                                            <label class="block text-lg font-semibold text-ink" for="question-{{ $key }}">
                                                {{ $question['label'] ?? $key }}
                                                @if (!empty($question['required']))
                                                    <span class="text-primary">*</span>
                                                @endif
                                            </label>
                                        @endif
                                        @if (!empty($question['help_text']))
                                            <p class="text-sm text-ink/55">{{ $question['help_text'] }}</p>
                                        @endif
                                    </div>

                                    <div class="mt-5">
                                        @if ($type === SurveyQuestionType::ShortText)
                                            <input
                                                id="question-{{ $key }}"
                                                name="answers[{{ $key }}]"
                                                type="text"
                                                value="{{ is_string($oldValue) ? $oldValue : '' }}"
                                                placeholder="{{ $question['placeholder'] ?? '' }}"
                                                class="w-full rounded-2xl border border-ink/10 bg-surface/60 px-4 py-3 text-sm text-ink shadow-sm transition focus:border-primary/40 focus:outline-none focus:ring-2 focus:ring-primary/20"
                                            >
                                        @elseif ($type === SurveyQuestionType::LongText)
                                            <textarea
                                                id="question-{{ $key }}"
                                                name="answers[{{ $key }}]"
                                                rows="5"
                                                placeholder="{{ $question['placeholder'] ?? '' }}"
                                                class="w-full rounded-2xl border border-ink/10 bg-surface/60 px-4 py-3 text-sm text-ink shadow-sm transition focus:border-primary/40 focus:outline-none focus:ring-2 focus:ring-primary/20"
                                            >{{ is_string($oldValue) ? $oldValue : '' }}</textarea>
                                        @elseif ($type === SurveyQuestionType::YesNo)
                                            <div class="grid gap-3 sm:grid-cols-2" role="group" aria-labelledby="question-label-{{ $key }}">
                                                @foreach (['yes' => __('Yes'), 'no' => __('No')] as $value => $label)
                                                    <label class="flex cursor-pointer items-center gap-3 rounded-2xl border border-ink/10 bg-surface/50 px-4 py-3 transition hover:border-primary/30">
                                                        <input type="radio" name="answers[{{ $key }}]" value="{{ $value }}" class="text-primary focus:ring-primary/20" @checked($oldValue === $value)>
                                                        <span class="text-sm font-medium text-ink">{{ $label }}</span>
                                                    </label>
                                                @endforeach
                                            </div>
                                        @elseif ($type === SurveyQuestionType::SingleChoice)
                                            <div class="grid gap-3" role="group" aria-labelledby="question-label-{{ $key }}">
                                                @foreach ($options as $option)
                                                    <label class="flex cursor-pointer items-center gap-3 rounded-2xl border border-ink/10 bg-surface/50 px-4 py-3 transition hover:border-primary/30">
                                                        <input type="radio" name="answers[{{ $key }}]" value="{{ $option['value'] ?? '' }}" class="text-primary focus:ring-primary/20" @checked($oldValue === ($option['value'] ?? null))>
                                                        <span class="text-sm font-medium text-ink">{{ $option['label'] ?? $option['value'] ?? '' }}</span>
                                                    </label>
                                                @endforeach
                                            </div>
                                        @elseif ($type === SurveyQuestionType::MultipleChoice)
                                            @php
                                                $selectedValues = is_array($oldValue) ? $oldValue : [];
                                            @endphp
                                            <div class="grid gap-3" role="group" aria-labelledby="question-label-{{ $key }}">
                                                @foreach ($options as $option)
                                                    @php
                                                        $value = (string) ($option['value'] ?? '');
                                                    @endphp
                                                    <label class="flex cursor-pointer items-center gap-3 rounded-2xl border border-ink/10 bg-surface/50 px-4 py-3 transition hover:border-primary/30">
                                                        <input type="checkbox" name="answers[{{ $key }}][]" value="{{ $value }}" class="rounded border-ink/20 text-primary focus:ring-primary/20" @checked(in_array($value, $selectedValues, true))>
                                                        <span class="text-sm font-medium text-ink">{{ $option['label'] ?? $value }}</span>
                                                    </label>
                                                @endforeach
                                            </div>
                                        @elseif ($type === SurveyQuestionType::Rating || $type === SurveyQuestionType::Nps)
                                            <div class="{{ $rangeGridClasses }}" role="group" aria-labelledby="question-label-{{ $key }}">
                                                @for ($value = $minValue; $value <= $maxValue; $value++)
                                                    <label class="cursor-pointer">
                                                        <input type="radio" name="answers[{{ $key }}]" value="{{ $value }}" class="sr-only peer" @checked((string) $oldValue === (string) $value)>
                                                        <span class="flex h-11 items-center justify-center rounded-2xl border border-ink/10 bg-surface/50 text-sm font-semibold text-ink transition peer-checked:border-primary/50 peer-checked:bg-primary peer-checked:text-white hover:border-primary/30">
                                                            {{ $value }}
                                                        </span>
                                                    </label>
                                                @endfor
                                            </div>
                                            @if (!empty($question['min_label']) || !empty($question['max_label']))
                                                <div class="mt-3 flex items-center justify-between gap-4 text-xs font-medium uppercase tracking-[0.18em] text-ink/45">
                                                    <span>{{ $question['min_label'] ?? '' }}</span>
                                                    <span class="text-right">{{ $question['max_label'] ?? '' }}</span>
                                                </div>
                                            @endif
                                        @endif
                                    </div>

                                    <x-input-error :messages="$errors->get('answers.' . $key)" class="mt-3" />
                                </div>
                            @endif
                        @endforeach

                        <div class="flex flex-wrap items-center gap-4">
                            <button type="submit" class="btn-primary">
                                {{ $survey->submit_label ?: __('Submit') }}
                            </button>
                        </div>
                    </form>
                @endif
            </div>
        </div>
    </section>
@endsection
