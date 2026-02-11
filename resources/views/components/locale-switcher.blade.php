@props(['class' => ''])

@php
    $locales = config('saas.locales.supported', ['en' => 'English']);
    $current = app()->getLocale();
@endphp

<form method="POST" action="{{ route('locale.update') }}" class="{{ $class }}" data-submit-lock>
    @csrf
    <input type="hidden" name="redirect" value="{{ url()->full() }}">
    <label class="sr-only" for="locale-switcher">{{ __('Language') }}</label>
    <select
        id="locale-switcher"
        name="locale"
        onchange="this.form.submit()"
        class="appearance-none rounded-full border border-ink/15 bg-surface/50 pl-3 pr-8 py-2 text-xs font-semibold text-ink/70 transition hover:border-ink/30 hover:text-ink hover:bg-surface cursor-pointer bg-no-repeat bg-[length:16px_16px] bg-[right_8px_center]"
        style="background-image: url('data:image/svg+xml,%3Csvg xmlns=%27http://www.w3.org/2000/svg%27 fill=%27none%27 viewBox=%270 0 20 20%27%3E%3Cpath stroke=%27%236b7280%27 stroke-linecap=%27round%27 stroke-linejoin=%27round%27 stroke-width=%271.5%27 d=%27M6 8l4 4 4-4%27/%3E%3C/svg%3E')"
    >
        @foreach ($locales as $code => $label)
            <option value="{{ $code }}" @selected($code === $current)>{{ $label }}</option>
        @endforeach
    </select>
</form>
