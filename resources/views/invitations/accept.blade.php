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
                    @if($userExists)
                        <div class="rounded-2xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-700">
                            Please sign in with {{ $invitation->email }} to accept this invite.
                        </div>
                        <a href="{{ route('login') }}" class="mt-4 inline-flex items-center rounded-full bg-primary px-6 py-3 text-sm font-semibold text-white shadow-lg shadow-primary/30 transition hover:bg-primary/90">
                            Sign in to accept
                        </a>
                    @else
                        <form method="POST" action="{{ route('invitations.register', $invitation->token) }}" class="space-y-4">
                            @csrf
                            <div>
                                <label for="name" class="text-sm text-ink/70">Full name</label>
                                <x-text-input id="name" name="name" type="text" class="mt-1 block w-full" :value="old('name')" required autofocus />
                                <x-input-error :messages="$errors->get('name')" class="mt-2" />
                            </div>
                            <div>
                                <label for="email" class="text-sm text-ink/70">Email</label>
                                <x-text-input id="email" type="email" class="mt-1 block w-full" :value="$invitation->email" disabled />
                            </div>
                            <div>
                                <label for="password" class="text-sm text-ink/70">Password</label>
                                <x-text-input id="password" name="password" type="password" class="mt-1 block w-full" required />
                                <x-input-error :messages="$errors->get('password')" class="mt-2" />
                            </div>
                            <div>
                                <label for="password_confirmation" class="text-sm text-ink/70">Confirm password</label>
                                <x-text-input id="password_confirmation" name="password_confirmation" type="password" class="mt-1 block w-full" required />
                                <x-input-error :messages="$errors->get('password_confirmation')" class="mt-2" />
                            </div>
                            <x-primary-button>
                                Set password & accept
                            </x-primary-button>
                        </form>
                    @endif
                @endauth
            </div>
        </div>
    </div>
</x-guest-layout>
