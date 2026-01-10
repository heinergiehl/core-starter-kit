@props(['class' => ''])

@php
    $locales = config('saas.locales.supported', ['en' => 'English']);
    $current = app()->getLocale();
@endphp

<form method="POST" action="{{ route('locale.update') }}" class="{{ $class }}">
    @csrf
    <input type="hidden" name="redirect" value="{{ url()->current() }}">
    <label class="sr-only" for="locale-switcher">{{ __('Language') }}</label>
    <select
        id="locale-switcher"
        name="locale"
        onchange="this.form.submit()"
        class="rounded-full border border-ink/15 bg-surface/50 px-3 py-2 text-xs font-semibold text-ink/70 transition hover:border-ink/30 hover:text-ink hover:bg-surface"
    >
        @foreach ($locales as $code => $label)
            <option value="{{ $code }}" @selected($code === $current)>{{ $label }}</option>
        @endforeach
    </select>
</form>
