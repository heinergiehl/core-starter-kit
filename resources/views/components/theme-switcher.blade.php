<div x-data="{
    theme: 'system',
    isDark: false,
    init() {
        if (!window.themeManager) {
            return;
        }

        this.theme = window.themeManager.getStoredTheme();
        this.isDark = window.themeManager.isDarkTheme(this.theme);
        window.addEventListener('theme:changed', (event) => {
            this.theme = event.detail.theme;
            this.isDark = event.detail.isDark;
        });
    },
    applyTheme(val) {
        window.themeManager?.applyTheme(val);
    },
    toggle() {
        window.themeManager?.toggleTheme();
    }
}" class="relative flex items-center">
    <button 
        @click="toggle()" 
        class="rounded-full p-2 text-ink/60 transition hover:bg-ink/5 hover:text-ink focus:outline-none focus:ring-2 focus:ring-primary/50"
        :title="isDark ? 'Switch to Light Mode' : 'Switch to Dark Mode'"
    >
        <!-- Sun Icon (Show in Dark Mode) -->
        <svg x-show="isDark" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" style="display: none;">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 3v1m0 16v1m9-9h-1M4 12H3m15.364 6.364l-.707-.707M6.343 6.343l-.707-.707m12.728 0l-.707.707M6.343 17.657l-.707.707M16 12a4 4 0 11-8 0 4 4 0 018 0z" />
        </svg>
        <!-- Moon Icon (Show in Light Mode) -->
        <svg x-show="!isDark" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20.354 15.354A9 9 0 018.646 3.646 9.003 9.003 0 0012 21a9.003 9.003 0 008.354-5.646z" />
        </svg>
    </button>
</div>
