<div 
    x-data="{ submitting: false }"
    x-on:login-failed.window="submitting = false"
>
    {{-- Session Status --}}
    @if (session('status'))
        <div class="mb-4 font-medium text-sm text-green-600">
            {{ session('status') }}
        </div>
    @endif

    <form method="POST" action="{{ route('login.store') }}" wire:submit="login" x-on:submit="submitting = true">
        @csrf
        <fieldset :disabled="submitting" x-bind:class="{ 'opacity-60': submitting }">
            {{-- Email Address --}}
            <div>
                <x-input-label for="email" :value="__('Email')" />
                <x-text-input 
                    wire:model="email" 
                    id="email" 
                    class="block mt-1 w-full" 
                    type="email" 
                    required 
                    autofocus 
                    autocomplete="username" 
                />
                <x-input-error :messages="$errors->get('email')" class="mt-2" />
            </div>

            {{-- Password --}}
            <div class="mt-4">
                <x-input-label for="password" :value="__('Password')" />
                <x-text-input 
                    wire:model="password" 
                    id="password" 
                    class="block mt-1 w-full"
                    type="password"
                    required 
                    autocomplete="current-password" 
                />
                <x-input-error :messages="$errors->get('password')" class="mt-2" />
            </div>

            {{-- Remember Me --}}
            <div class="block mt-4">
                <label for="remember_me" class="inline-flex items-center">
                    <input 
                        wire:model="remember" 
                        id="remember_me" 
                        type="checkbox" 
                        class="rounded border-ink/20 text-primary shadow-sm focus:ring-primary/40"
                    >
                    <span class="ms-2 text-sm text-ink/60">{{ __('Remember me') }}</span>
                </label>
            </div>

            <div class="flex items-center justify-end mt-4">
                @if (Route::has('password.request'))
                    <a 
                        class="underline text-sm text-ink/70 hover:text-ink rounded-md focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-primary/40" 
                        href="{{ route('password.request') }}"
                        x-bind:class="{ 'pointer-events-none': submitting }"
                    >
                        {{ __('Forgot your password?') }}
                    </a>
                @endif

                <button 
                    type="submit"
                    class="ms-3 min-w-[120px] inline-flex items-center justify-center gap-2 px-4 py-2 bg-primary border border-transparent rounded-xl font-semibold text-sm text-white tracking-wide hover:bg-primary/90 focus:bg-primary/90 active:bg-primary focus:outline-none focus:ring-2 focus:ring-primary/50 focus:ring-offset-2 transition ease-in-out duration-150 disabled:opacity-100 disabled:cursor-not-allowed"
                >
                    <svg x-show="submitting" x-cloak class="animate-spin h-4 w-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                    </svg>
                    <span x-text="submitting ? '{{ __('Logging in...') }}' : '{{ __('Log in') }}'"></span>
                </button>
            </div>
        </fieldset>
    </form>
    
    <x-social-auth-buttons x-bind:class="{ 'pointer-events-none opacity-50': submitting }" />
</div>
