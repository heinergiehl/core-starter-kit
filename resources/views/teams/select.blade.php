<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-ink leading-tight">
            {{ __('Select a workspace') }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="mx-auto max-w-5xl sm:px-6 lg:px-8">
            <div class="glass-panel rounded-3xl p-8 w-full max-w-md relative">
                <p class="text-sm text-ink/70">
                    {{ __('Choose the team you want to work in. You can switch anytime.') }}
                </p>

                <div class="mt-6 grid gap-4">
                    @forelse ($teams as $team)
                        <form method="POST" action="{{ route('teams.switch', $team) }}" class="rounded-2xl border border-ink/10 bg-white/90 px-5 py-4">
                            @csrf
                            <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                                <div>
                                    <p class="text-base font-semibold text-ink">{{ $team->name }}</p>
                                    <p class="text-sm text-ink/60">{{ __('Owner: :name', ['name' => $team->owner?->name]) }}</p>
                                </div>
                                <x-primary-button type="submit">{{ __('Switch') }}</x-primary-button>
                            </div>
                        </form>
                    @empty
                        <div class="rounded-2xl border border-dashed border-ink/20 bg-white/60 px-5 py-6 text-sm text-ink/70">
                            {{ __('You are not part of any teams yet. Create one from the dashboard or ask for an invite.') }}
                        </div>
                    @endforelse
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
