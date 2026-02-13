@props([
    'mobilePosition' => 'top-4 inset-x-4',
    'position' => 'sm:top-4 sm:right-4 sm:inset-x-auto',
])

@php
    $initialToasts = collect(['success', 'error', 'warning', 'info'])
        ->filter(fn (string $type): bool => session()->has($type))
        ->map(fn (string $type): array => [
            'type' => $type,
            'message' => (string) session($type),
        ])
        ->values()
        ->all();

    $rootId = 'toast-root-'.\Illuminate\Support\Str::ulid();
@endphp

<div
    id="{{ $rootId }}"
    data-toast-root
    data-initial-toasts='@json($initialToasts)'
    class="fixed {{ $mobilePosition }} {{ $position }} z-[200] flex flex-col gap-3 pointer-events-none"
    aria-live="polite"
></div>

@once
    <script>
        (() => {
            if (window.__appToastManager) {
                return;
            }

            const toastTypes = {
                success: {
                    container: 'bg-emerald-500/10 border-emerald-500/20 text-emerald-700 dark:text-emerald-300',
                    icon: '<svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>',
                },
                error: {
                    container: 'bg-red-500/10 border-red-500/20 text-red-700 dark:text-red-300',
                    icon: '<svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>',
                },
                warning: {
                    container: 'bg-amber-500/10 border-amber-500/20 text-amber-700 dark:text-amber-300',
                    icon: '<svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>',
                },
                info: {
                    container: 'bg-blue-500/10 border-blue-500/20 text-blue-700 dark:text-blue-300',
                    icon: '<svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>',
                },
            };

            const getRoots = () => Array.from(document.querySelectorAll('[data-toast-root]'));

            const showToast = (root, message, type = 'success') => {
                const normalizedType = toastTypes[type] ? type : 'success';
                const normalizedMessage = String(message || '').trim();
                if (!normalizedMessage) {
                    return;
                }

                const config = toastTypes[normalizedType];
                const toast = document.createElement('div');
                toast.className = `pointer-events-auto flex items-center gap-3 rounded-xl px-4 py-3 shadow-lg backdrop-blur-md border min-w-[280px] max-w-[400px] transition ease-out duration-300 opacity-0 translate-x-8 ${config.container}`;

                const iconWrap = document.createElement('div');
                iconWrap.className = 'shrink-0';
                iconWrap.innerHTML = config.icon;
                toast.appendChild(iconWrap);

                const text = document.createElement('span');
                text.className = 'text-sm font-medium flex-1';
                text.textContent = normalizedMessage;
                toast.appendChild(text);

                const close = document.createElement('button');
                close.type = 'button';
                close.className = 'shrink-0 opacity-60 hover:opacity-100 transition-opacity';
                close.innerHTML = '<svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>';
                toast.appendChild(close);

                const remove = () => {
                    toast.classList.add('opacity-0', 'translate-x-8');
                    toast.classList.remove('opacity-100', 'translate-x-0');
                    window.setTimeout(() => toast.remove(), 200);
                };

                close.addEventListener('click', remove);
                root.appendChild(toast);

                window.requestAnimationFrame(() => {
                    toast.classList.remove('opacity-0', 'translate-x-8');
                    toast.classList.add('opacity-100', 'translate-x-0');
                });

                window.setTimeout(remove, 5000);
            };

            const notifyAllRoots = (message, type = 'success') => {
                getRoots().forEach((root) => showToast(root, message, type));
            };

            const initializeRoot = (root) => {
                if (root.dataset.toastInitialized === '1') {
                    return;
                }

                root.dataset.toastInitialized = '1';

                let initialToasts = [];
                try {
                    initialToasts = JSON.parse(root.dataset.initialToasts || '[]');
                } catch (_error) {
                    initialToasts = [];
                }

                if (!Array.isArray(initialToasts)) {
                    return;
                }

                initialToasts.forEach((toast) => {
                    if (!toast || typeof toast !== 'object') {
                        return;
                    }

                    showToast(root, toast.message, toast.type || 'success');
                });
            };

            const initializeAllRoots = () => {
                getRoots().forEach(initializeRoot);
            };

            window.addEventListener('notify', (event) => {
                notifyAllRoots(event?.detail?.message || '', event?.detail?.type || 'success');
            });

            if (document.readyState === 'loading') {
                document.addEventListener('DOMContentLoaded', initializeAllRoots);
            } else {
                initializeAllRoots();
            }

            document.addEventListener('livewire:navigated', initializeAllRoots);

            window.__appToastManager = {
                notify: notifyAllRoots,
            };
        })();
    </script>
@endonce
