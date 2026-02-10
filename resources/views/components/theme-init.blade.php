<script>
    (() => {
        const storageKey = 'theme';
        const mediaQuery = window.matchMedia('(prefers-color-scheme: dark)');

        const getStoredTheme = () => localStorage.getItem(storageKey) || 'system';

        const isDarkTheme = (theme = getStoredTheme()) => {
            return theme === 'dark' || (theme === 'system' && mediaQuery.matches);
        };

        const applyTheme = (theme = getStoredTheme()) => {
            localStorage.setItem(storageKey, theme);

            const isDark = isDarkTheme(theme);
            document.documentElement.classList.toggle('dark', isDark);

            window.dispatchEvent(new CustomEvent('theme:changed', {
                detail: { theme, isDark },
            }));

            return isDark;
        };

        const toggleTheme = () => {
            return applyTheme(isDarkTheme() ? 'light' : 'dark');
        };

        window.themeManager = {
            storageKey,
            mediaQuery,
            getStoredTheme,
            isDarkTheme,
            applyTheme,
            toggleTheme,
        };

        mediaQuery.addEventListener('change', () => {
            if (getStoredTheme() === 'system') {
                applyTheme('system');
            }
        });

        applyTheme(getStoredTheme());
    })();
</script>
