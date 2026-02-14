<div class="relative flex items-center" data-theme-switcher>
    <button
        type="button"
        data-theme-toggle
        class="rounded-full p-2 text-ink/60 transition hover:bg-ink/5 hover:text-ink focus:outline-none focus:ring-2 focus:ring-primary/50"
        title="{{ __('Toggle theme') }}"
    >
        <!-- Sun Icon (show in dark mode) -->
        <svg data-theme-icon-dark class="hidden h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 3v1m0 16v1m9-9h-1M4 12H3m15.364 6.364l-.707-.707M6.343 6.343l-.707-.707m12.728 0l-.707.707M6.343 17.657l-.707.707M16 12a4 4 0 11-8 0 4 4 0 018 0z" />
        </svg>
        <!-- Moon Icon (show in light mode) -->
        <svg data-theme-icon-light class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20.354 15.354A9 9 0 018.646 3.646 9.003 9.003 0 0012 21a9.003 9.003 0 008.354-5.646z" />
        </svg>
    </button>
</div>

@once
    <script>
        (() => {
            const roots = () => Array.from(document.querySelectorAll('[data-theme-switcher]'));

            const syncRoot = (root) => {
                const toggle = root.querySelector('[data-theme-toggle]');
                const darkIcon = root.querySelector('[data-theme-icon-dark]');
                const lightIcon = root.querySelector('[data-theme-icon-light]');
                if (!toggle || !darkIcon || !lightIcon) {
                    return;
                }

                const isDark = window.themeManager?.isDarkTheme(window.themeManager.getStoredTheme()) ?? false;
                darkIcon.classList.toggle('hidden', !isDark);
                lightIcon.classList.toggle('hidden', isDark);
                toggle.title = isDark ? @json(__('Switch to Light Mode')) : @json(__('Switch to Dark Mode'));
            };

            const setupRoot = (root) => {
                if (root.dataset.themeSwitcherReady === '1') {
                    syncRoot(root);
                    return;
                }

                const toggle = root.querySelector('[data-theme-toggle]');
                if (!toggle) {
                    return;
                }

                root.dataset.themeSwitcherReady = '1';

                toggle.addEventListener('click', () => {
                    window.themeManager?.toggleTheme();
                    syncRoot(root);
                });

                syncRoot(root);
            };

            const setupAll = () => {
                roots().forEach(setupRoot);
            };

            window.addEventListener('theme:changed', () => {
                roots().forEach(syncRoot);
            });

            if (document.readyState === 'loading') {
                document.addEventListener('DOMContentLoaded', setupAll);
            } else {
                setupAll();
            }

            document.addEventListener('livewire:navigated', setupAll);
        })();
    </script>
@endonce
