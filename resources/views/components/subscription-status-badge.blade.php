@props(['status'])

@php
    $value = $status instanceof \BackedEnum ? $status->value : $status;
    $statusColor = match($value) {
        'active' => 'text-emerald-500 bg-emerald-500/10 border-emerald-500/20',
        'trialing' => 'text-blue-500 bg-blue-500/10 border-blue-500/20',
        'past_due' => 'text-amber-500 bg-amber-500/10 border-amber-500/20',
        'canceled' => 'text-red-500 bg-red-500/10 border-red-500/20',
        default => 'text-ink/50 bg-surface/5 border-ink/10'
    };
    
    $label = $status instanceof \Filament\Support\Contracts\HasLabel ? $status->getLabel() : ucfirst($status ?? 'Free');
@endphp

<div {{ $attributes->merge(['class' => "inline-flex items-center px-3 py-1 rounded-full text-sm font-medium border {$statusColor}"]) }}>
    {{ $label }}
</div>
