@extends('layouts.marketing')

@php
    use Illuminate\Support\Str;
@endphp

@section('title', __('Roadmap') . ' - ' . ($appBrandName ?? config('app.name', 'SaaS Kit')))
@section('meta_description', __('A transparent roadmap driven by feedback from the SaaS Kit community.'))

@section('content')
    @php
        $statusLabels = [
            'planned' => __('Planned'),
            'in_progress' => __('In progress'),
            'complete' => __('Complete'),
        ];
    @endphp

    <section class="py-10">
        <div class="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
            <div>
                <p class="text-sm font-semibold uppercase tracking-[0.2em] text-secondary">{{ __('Roadmap') }}</p>
                <h1 class="mt-3 font-display text-4xl">{{ __('Vote on what ships next') }}</h1>
            </div>
            <p class="text-sm text-ink/70">{{ __('Share feedback and see what is planned, in progress, or complete.') }}</p>
        </div>

        <div class="mt-6 flex flex-wrap items-center gap-3 text-sm">
            <a href="{{ route('roadmap') }}" class="rounded-full border px-4 py-2 {{ $status ? 'border-ink/10 text-ink/60' : 'border-primary/40 bg-primary/10 text-primary' }}">{{ __('All') }}</a>
            @foreach ($statuses as $filter)
                <a href="{{ route('roadmap', ['status' => $filter]) }}" class="rounded-full border px-4 py-2 {{ $status === $filter ? 'border-primary/40 bg-primary/10 text-primary' : 'border-ink/10 text-ink/60' }}">
                    {{ $statusLabels[$filter] ?? Str::headline($filter) }}
                </a>
            @endforeach
        </div>

        @if (session('status'))
            <div class="mt-6 rounded-2xl border border-primary/20 bg-primary/5 px-4 py-3 text-sm text-primary">
                {{ session('status') }}
            </div>
        @endif

        <div class="mt-8 grid gap-6 lg:grid-cols-[2fr_1fr]">
            <div class="space-y-4">
                @forelse ($requests as $request)
                    <article class="glass-panel rounded-3xl p-6 relative group transition hover:border-primary/30">
                        <div class="flex items-start justify-between gap-4">
                            <div>
                                <p class="text-xs uppercase tracking-[0.2em] text-ink/40">
                                    {{ $request->category ?: __('General') }}
                                </p>
                                <h3 class="mt-2 text-xl font-semibold text-ink">{{ $request->title }}</h3>
                                @if ($request->description)
                                    <p class="mt-2 text-sm text-ink/70">{{ $request->description }}</p>
                                @endif
                            </div>
                            <span class="rounded-full border border-ink/5 bg-surface-highlight/10 px-3 py-1 text-xs font-semibold text-ink/70">
                                {{ $statusLabels[$request->status] ?? Str::headline($request->status) }}
                            </span>
                        </div>
                            <div class="mt-4 flex flex-wrap items-center justify-between gap-4 text-sm">
                                <div class="flex items-center gap-4 text-ink/60">
                                    <span>{{ __(':count votes', ['count' => $request->votes_count]) }}</span>
                                    <span>{{ __('Submitted :time', ['time' => $request->created_at->diffForHumans()]) }}</span>
                                </div>
                                @auth
                                    <form method="POST" action="{{ route('roadmap.vote', $request) }}">
                                        @csrf
                                        <button type="submit" class="rounded-full border border-primary/30 px-4 py-2 text-sm font-semibold text-primary transition hover:border-primary/50 hover:bg-primary/5">
                                            {{ in_array($request->id, $votedIds, true) ? __('Voted') : __('Vote') }}
                                        </button>
                                    </form>
                                @else
                                    <a href="{{ route('login') }}" class="rounded-full border border-ink/10 px-4 py-2 text-sm font-semibold text-ink/60 transition hover:text-ink">
                                        {{ __('Sign in to vote') }}
                                    </a>
                                @endauth
                            </div>
                        </article>
                    @empty
                        <div class="glass-panel rounded-3xl p-10 text-center text-sm text-ink/70">
                            {{ __('No roadmap items yet.') }}
                        </div>
                    @endforelse

                <div class="mt-6">
                    {{ $requests->links() }}
                </div>
            </div>

            <aside class="glass-panel rounded-3xl p-6 h-fit">
                <h3 class="text-lg font-semibold text-ink">{{ __('Submit feedback') }}</h3>
                <p class="mt-2 text-sm text-ink/60">{{ __('Share a concise feature request to guide the roadmap.') }}</p>

                @auth
                    <form method="POST" action="{{ route('roadmap.store') }}" class="mt-4 space-y-4">
                        @csrf
                        <div>
                            <x-input-label for="title" :value="__('Title')" />
                            <x-text-input id="title" class="mt-1 block w-full bg-surface/50" type="text" name="title" value="{{ old('title') }}" required />
                            <x-input-error :messages="$errors->get('title')" class="mt-2" />
                        </div>
                        <div>
                            <x-input-label for="category" :value="__('Category')" />
                            <x-text-input id="category" class="mt-1 block w-full bg-surface/50" type="text" name="category" value="{{ old('category') }}" />
                        </div>
                        <div>
                            <x-input-label for="description" :value="__('Details')" />
                            <textarea id="description" name="description" rows="4" class="mt-1 w-full rounded-xl border border-ink/10 bg-surface/50 px-3 py-2 text-sm text-ink/80 focus:border-primary/40 focus:outline-none focus:ring-2 focus:ring-primary/20 placeholder-ink/30 transition shadow-sm">{{ old('description') }}</textarea>
                        </div>
                        <x-primary-button class="w-full justify-center">
                            {{ __('Submit request') }}
                        </x-primary-button>
                    </form>
                @else
                    <div class="mt-4 rounded-2xl border border-dashed border-ink/20 bg-surface/30 px-4 py-4 text-sm text-ink/70">
                        <p>{{ __('Sign in to submit or vote on roadmap items.') }}</p>
                        <a href="{{ route('login') }}" class="mt-3 inline-flex rounded-full border border-ink/10 px-4 py-2 text-sm font-semibold text-ink/60 transition hover:text-ink hover:bg-surface">{{ __('Sign in') }}</a>
                    </div>
                @endauth
            </aside>
        </div>
    </section>
@endsection
