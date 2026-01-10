<section>
    <header>
        <h2 class="text-lg font-medium text-ink">
            {{ __('Two-Factor Authentication') }}
        </h2>
        <p class="mt-1 text-sm text-ink/60">
            {{ __('Add additional security to your account using two-factor authentication.') }}
        </p>
    </header>

    @php
        $user = auth()->user();
        $twoFactor = $user->twoFactorAuth;
        $isEnabled = $twoFactor?->isEnabled();
        $isPending = $twoFactor && !$isEnabled;
    @endphp

    <div class="mt-6">
        @if($isEnabled)
            {{-- 2FA is enabled --}}
            <div class="flex items-center gap-3 p-4 rounded-xl bg-green-500/10 border border-green-500/20">
                <svg class="w-6 h-6 text-green-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z" />
                </svg>
                <div>
                    <p class="font-medium text-green-700 dark:text-green-400">{{ __('Two-factor authentication is enabled.') }}</p>
                    <p class="text-sm text-green-600/70 dark:text-green-500/70">{{ __('Your account is protected with an authenticator app.') }}</p>
                </div>
            </div>

            {{-- Show backup codes if just regenerated --}}
            @if(session('show_backup_codes') && $twoFactor->backup_codes)
                <div class="mt-6 p-4 rounded-xl bg-amber-500/10 border border-amber-500/20">
                    <p class="font-medium text-amber-700 dark:text-amber-400 mb-3">{{ __('Save these backup codes in a secure location:') }}</p>
                    <div class="grid grid-cols-2 gap-2 font-mono text-sm">
                        @foreach($twoFactor->backup_codes as $code)
                            <div class="px-3 py-1 bg-surface rounded border border-ink/10">{{ $code }}</div>
                        @endforeach
                    </div>
                    <p class="mt-3 text-xs text-amber-600/70 dark:text-amber-500/70">
                        {{ __('Each code can only be used once. Store them safely.') }}
                    </p>
                </div>
            @endif

            {{-- Regenerate backup codes --}}
            <form method="POST" action="{{ route('two-factor.regenerate') }}" class="mt-6">
                @csrf
                <div class="flex items-end gap-4">
                    <div class="flex-1">
                        <x-input-label for="regen_password" :value="__('Password')" />
                        <x-text-input id="regen_password" name="password" type="password" class="mt-1 block w-full" placeholder="{{ __('Confirm password to regenerate') }}" />
                    </div>
                    <x-secondary-button type="submit">
                        {{ __('Regenerate Backup Codes') }}
                    </x-secondary-button>
                </div>
            </form>

            {{-- Disable 2FA --}}
            <form method="POST" action="{{ route('two-factor.disable') }}" class="mt-6 pt-6 border-t border-ink/10">
                @csrf
                @method('DELETE')
                <div class="flex items-end gap-4">
                    <div class="flex-1">
                        <x-input-label for="disable_password" :value="__('Password')" />
                        <x-text-input id="disable_password" name="password" type="password" class="mt-1 block w-full" placeholder="{{ __('Confirm password to disable') }}" />
                    </div>
                    <x-danger-button type="submit">
                        {{ __('Disable 2FA') }}
                    </x-danger-button>
                </div>
            </form>

        @elseif($isPending)
            {{-- 2FA setup pending confirmation --}}
            <div class="p-4 rounded-xl bg-amber-500/10 border border-amber-500/20 mb-6">
                <p class="font-medium text-amber-700 dark:text-amber-400">{{ __('Scan this QR code with your authenticator app:') }}</p>
            </div>

            <div class="flex justify-center p-6 bg-white rounded-xl border border-ink/10">
                @php
                    $qrUri = $twoFactor->getQrCodeUri($user->email, config('app.name'));
                @endphp
                <img src="https://api.qrserver.com/v1/create-qr-code/?size=200x200&data={{ urlencode($qrUri) }}" alt="QR Code" class="w-48 h-48" />
            </div>

            <p class="mt-4 text-sm text-ink/60 text-center">
                {{ __('Or enter this code manually:') }}
                <code class="block mt-2 font-mono text-lg tracking-widest text-ink">{{ $twoFactor->getDecryptedSecret() }}</code>
            </p>

            <form method="POST" action="{{ route('two-factor.confirm') }}" class="mt-6">
                @csrf
                <x-input-label for="code" :value="__('Enter code from your app')" />
                <x-text-input 
                    id="code" 
                    name="code" 
                    type="text" 
                    class="mt-1 block w-full text-center tracking-[0.5em] font-mono text-lg" 
                    inputmode="numeric"
                    pattern="[0-9]*"
                    maxlength="6"
                    placeholder="000000"
                    autofocus
                />
                <x-input-error :messages="$errors->get('code')" class="mt-2" />

                <div class="flex justify-end mt-4">
                    <x-primary-button>
                        {{ __('Confirm & Enable') }}
                    </x-primary-button>
                </div>
            </form>

        @else
            {{-- 2FA not enabled --}}
            <div class="flex items-center gap-3 p-4 rounded-xl bg-ink/5 border border-ink/10">
                <svg class="w-6 h-6 text-ink/40" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" />
                </svg>
                <div>
                    <p class="font-medium text-ink">{{ __('Two-factor authentication is not enabled.') }}</p>
                    <p class="text-sm text-ink/60">{{ __('When enabled, you will need to provide a code from your authenticator app when logging in.') }}</p>
                </div>
            </div>

            <form method="POST" action="{{ route('two-factor.enable') }}" class="mt-6">
                @csrf
                <x-primary-button>
                    {{ __('Enable Two-Factor Authentication') }}
                </x-primary-button>
            </form>
        @endif
    </div>
</section>
