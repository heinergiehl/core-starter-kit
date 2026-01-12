@props(['disabled' => false])

<input @disabled($disabled) {{ $attributes->merge(['class' => 'border-ink/20 focus:border-primary focus:ring-primary/40 rounded-md shadow-sm bg-surface/80 text-ink placeholder:text-ink/40 transition disabled:opacity-50 disabled:cursor-not-allowed disabled:bg-ink/5']) }}>
