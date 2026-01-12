@php
    use App\Domain\Identity\Services\ImpersonationService;
    $impersonationService = app(ImpersonationService::class);
@endphp

@if ($impersonationService->isImpersonating())
    @php
        $impersonator = $impersonationService->getImpersonator();
    @endphp
    <div class="fixed top-0 inset-x-0 z-[100]">
        <div class="bg-gradient-to-r from-amber-500 to-orange-500 text-white px-4 py-2 shadow-lg">
            <div class="max-w-7xl mx-auto flex items-center justify-between gap-4">
                <div class="flex items-center gap-3">
                    <svg class="w-5 h-5 animate-pulse" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" 
                            d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
                    </svg>
                    <span class="text-sm font-medium">
                        You are impersonating <strong>{{ auth()->user()->name }}</strong> 
                        ({{ auth()->user()->email }})
                    </span>
                </div>
                <form action="{{ route('impersonate.stop') }}" method="POST" class="flex items-center gap-2">
                    @csrf
                    <span class="text-xs text-white/80 hidden sm:inline">
                        Return as {{ $impersonator?->name }}
                    </span>
                    <button type="submit" 
                        class="inline-flex items-center gap-2 px-4 py-1.5 bg-white/20 hover:bg-white/30 rounded-full text-sm font-semibold transition-colors">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                        </svg>
                        Stop Impersonating
                    </button>
                </form>
            </div>
        </div>
    </div>
    {{-- Add padding to body to account for the banner --}}
    <style>
        body { padding-top: 52px !important; }
    </style>
@endif
