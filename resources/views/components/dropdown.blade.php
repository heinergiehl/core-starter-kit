@props(['align' => 'right', 'width' => '48', 'contentClasses' => 'py-2 bg-surface border border-ink/10 shadow-xl shadow-black/10'])

@php
$alignmentClasses = match ($align) {
    'left' => 'ltr:origin-top-left rtl:origin-top-right start-0',
    'top' => 'origin-top',
    default => 'ltr:origin-top-right rtl:origin-top-left end-0',
};

$width = match ($width) {
    '48' => 'w-48',
    default => $width,
};

$dropdownId = 'dropdown-'.\Illuminate\Support\Str::ulid();
@endphp

<div class="relative z-[320] pointer-events-auto" data-dropdown>
    <div
        data-dropdown-trigger
        aria-haspopup="true"
        aria-expanded="false"
        aria-controls="{{ $dropdownId }}"
    >
        {{ $trigger }}
    </div>

    <div
        id="{{ $dropdownId }}"
        data-dropdown-panel
        class="absolute z-[330] mt-2 hidden {{ $width }} rounded-xl {{ $alignmentClasses }}"
    >
        <div class="rounded-xl {{ $contentClasses }}">
            {{ $content }}
        </div>
    </div>
</div>

@once
    <script>
        (() => {
            const roots = () => Array.from(document.querySelectorAll('[data-dropdown]'));
            const getParts = (root) => ({
                trigger: root.querySelector('[data-dropdown-trigger]'),
                panel: root.querySelector('[data-dropdown-panel]'),
            });

            const closeDropdown = (root) => {
                const { trigger, panel } = getParts(root);
                if (!trigger || !panel) {
                    return;
                }

                panel.classList.add('hidden');
                trigger.setAttribute('aria-expanded', 'false');
            };

            const openDropdown = (root) => {
                const { trigger, panel } = getParts(root);
                if (!trigger || !panel) {
                    return;
                }

                panel.classList.remove('hidden');
                trigger.setAttribute('aria-expanded', 'true');
            };

            const toggleDropdown = (root) => {
                const { panel } = getParts(root);
                if (!panel) {
                    return;
                }

                if (panel.classList.contains('hidden')) {
                    roots().forEach((candidate) => {
                        if (candidate !== root) {
                            closeDropdown(candidate);
                        }
                    });
                    openDropdown(root);
                    return;
                }

                closeDropdown(root);
            };

            const setupDropdown = (root) => {
                if (root.dataset.dropdownReady === '1') {
                    return;
                }

                const { trigger, panel } = getParts(root);
                if (!trigger || !panel) {
                    return;
                }

                root.dataset.dropdownReady = '1';

                trigger.addEventListener('click', (event) => {
                    event.preventDefault();
                    event.stopPropagation();
                    toggleDropdown(root);
                });

                trigger.addEventListener('keydown', (event) => {
                    if (event.key === 'Enter' || event.key === ' ') {
                        event.preventDefault();
                        toggleDropdown(root);
                    }

                    if (event.key === 'Escape') {
                        closeDropdown(root);
                    }
                });

                panel.addEventListener('click', (event) => {
                    if (event.target.closest('a, button')) {
                        closeDropdown(root);
                    }
                });
            };

            const setupAll = () => {
                roots().forEach(setupDropdown);
            };

            document.addEventListener('click', (event) => {
                roots().forEach((root) => {
                    if (!root.contains(event.target)) {
                        closeDropdown(root);
                    }
                });
            });

            document.addEventListener('keydown', (event) => {
                if (event.key !== 'Escape') {
                    return;
                }

                roots().forEach(closeDropdown);
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
