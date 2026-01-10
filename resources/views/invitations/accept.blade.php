<x-guest-layout>
    <div class="mx-auto max-w-xl px-6 py-12">
        <div class="glass-panel rounded-3xl p-8 w-full max-w-lg relative">
            <p class="text-xs uppercase tracking-[0.3em] text-ink/50">Workspace invite</p>
            <h1 class="mt-3 text-3xl font-display text-ink">
                Join {{ $invitation->team?->name ?? 'the workspace' }}
            </h1>
            <p class="mt-3 text-sm text-ink/70">
                You were invited as <span class="font-semibold text-ink">{{ $invitation->role }}</span>.
                This invite expires {{ $invitation->expires_at?->diffForHumans() ?? 'soon' }}.
            </p>

            @if($errors->has('invitation'))
                <div class="mt-6 rounded-2xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">
                    {{ $errors->first('invitation') }}
                </div>
            @endif

            <div class="mt-8">
                @auth
                    @if($canAccept)
                        <form method="POST" action="{{ route('invitations.store', $invitation->token) }}">
                            @csrf
                            <x-primary-button>
                                Accept invitation
                            </x-primary-button>
                        </form>
                    @else
                        <div class="rounded-2xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-700">
                            Please sign in with {{ $invitation->email }} to accept this invite.
                        </div>
                    @endif
                @else
                    <a href="{{ route('login') }}" class="inline-flex items-center rounded-full bg-primary px-6 py-3 text-sm font-semibold text-white shadow-lg shadow-primary/30 transition hover:bg-primary/90">
                        Sign in to accept
                    </a>
                @endauth
            </div>
        </div>
    </div>
</x-guest-layout>
