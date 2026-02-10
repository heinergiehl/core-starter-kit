<x-filament-widgets::widget>
    @once
        <style>
            .ss-qa-stack {
                display: grid;
                gap: 0.75rem;
            }

            .ss-qa-primary,
            .ss-qa-secondary {
                display: grid;
                gap: 0.5rem;
            }

            .ss-qa-divider {
                border-top: 1px solid rgba(148, 163, 184, 0.24);
                margin: 0.25rem 0 0.15rem;
            }

            .ss-qa-card {
                align-items: flex-start;
                background: #ffffff;
                border: 1px solid #e2e8f0;
                border-radius: 0.75rem;
                color: inherit;
                display: flex;
                gap: 0.625rem;
                padding: 0.75rem;
                text-decoration: none;
                transition: border-color 140ms ease, box-shadow 140ms ease, transform 140ms ease;
            }

            .ss-qa-card:hover {
                border-color: #93c5fd;
                box-shadow: 0 8px 20px rgba(15, 23, 42, 0.08);
                transform: translateY(-1px);
            }

            .ss-qa-card--primary {
                background: linear-gradient(180deg, rgba(59, 130, 246, 0.06), rgba(59, 130, 246, 0.01));
            }

            .ss-qa-icon-wrap {
                align-items: center;
                background: #eff6ff;
                border-radius: 0.625rem;
                color: #2563eb;
                display: inline-flex;
                flex-shrink: 0;
                height: 1.875rem;
                justify-content: center;
                margin-top: 0.1rem;
                width: 1.875rem;
            }

            .ss-qa-icon {
                height: 1rem;
                width: 1rem;
            }

            .ss-qa-copy {
                min-width: 0;
            }

            .ss-qa-label {
                color: #0f172a;
                display: block;
                font-size: 0.875rem;
                font-weight: 600;
                line-height: 1.25;
            }

            .ss-qa-desc {
                color: #64748b;
                display: block;
                font-size: 0.75rem;
                line-height: 1.35;
                margin-top: 0.18rem;
            }

            .ss-qa-chevron {
                color: #94a3b8;
                flex-shrink: 0;
                height: 0.875rem;
                margin-left: auto;
                margin-top: 0.25rem;
                width: 0.875rem;
            }

            .ss-qa-secondary-head {
                color: #94a3b8;
                font-size: 0.6875rem;
                font-weight: 700;
                letter-spacing: 0.08em;
                margin-bottom: 0.1rem;
                text-transform: uppercase;
            }

            .ss-qa-row {
                align-items: flex-start;
                border-radius: 0.625rem;
                color: inherit;
                display: flex;
                gap: 0.625rem;
                padding: 0.5rem 0.55rem;
                text-decoration: none;
                transition: background-color 120ms ease;
            }

            .ss-qa-row:hover {
                background: #f8fafc;
            }

            .ss-qa-row-icon {
                color: #475569;
                flex-shrink: 0;
                height: 0.95rem;
                margin-top: 0.2rem;
                width: 0.95rem;
            }

            .ss-qa-row-label {
                color: #1e293b;
                display: block;
                font-size: 0.82rem;
                font-weight: 600;
                line-height: 1.2;
            }

            .ss-qa-row-desc {
                color: #64748b;
                display: block;
                font-size: 0.72rem;
                line-height: 1.3;
                margin-top: 0.18rem;
            }

            .dark .ss-qa-divider {
                border-top-color: rgba(148, 163, 184, 0.2);
            }

            .dark .ss-qa-card {
                background: rgba(15, 23, 42, 0.4);
                border-color: rgba(148, 163, 184, 0.24);
            }

            .dark .ss-qa-card--primary {
                background: linear-gradient(180deg, rgba(59, 130, 246, 0.18), rgba(30, 41, 59, 0.15));
            }

            .dark .ss-qa-card:hover {
                border-color: rgba(96, 165, 250, 0.58);
                box-shadow: 0 8px 20px rgba(2, 6, 23, 0.45);
            }

            .dark .ss-qa-icon-wrap {
                background: rgba(59, 130, 246, 0.22);
                color: #93c5fd;
            }

            .dark .ss-qa-label {
                color: #f8fafc;
            }

            .dark .ss-qa-desc,
            .dark .ss-qa-row-desc {
                color: #94a3b8;
            }

            .dark .ss-qa-row:hover {
                background: rgba(148, 163, 184, 0.12);
            }

            .dark .ss-qa-row-icon {
                color: #93c5fd;
            }

            .dark .ss-qa-row-label {
                color: #f1f5f9;
            }
        </style>
    @endonce

    <x-filament::section
        heading="Quick Actions"
        description="Common operator workflows."
    >
        <div class="ss-qa-stack">
            <div class="ss-qa-primary">
                @foreach ($primaryActions as $action)
                    <a
                        href="{{ $action['url'] }}"
                        class="ss-qa-card ss-qa-card--primary"
                    >
                        <span class="ss-qa-icon-wrap">
                            <x-filament::icon :icon="$action['icon']" class="ss-qa-icon" />
                        </span>

                        <span class="ss-qa-copy">
                            <span class="ss-qa-label">{{ $action['label'] }}</span>
                            <span class="ss-qa-desc">{{ $action['description'] }}</span>
                        </span>

                        <x-filament::icon icon="heroicon-m-chevron-right" class="ss-qa-chevron" />
                    </a>
                @endforeach
            </div>

            <div class="ss-qa-divider"></div>

            <div class="ss-qa-secondary">
                <p class="ss-qa-secondary-head">Operations</p>

                @foreach ($secondaryActions as $action)
                    <a href="{{ $action['url'] }}" class="ss-qa-row">
                        <x-filament::icon :icon="$action['icon']" class="ss-qa-row-icon" />

                        <span class="ss-qa-copy">
                            <span class="ss-qa-row-label">{{ $action['label'] }}</span>
                            <span class="ss-qa-row-desc">{{ $action['description'] }}</span>
                        </span>
                    </a>
                @endforeach
            </div>
        </div>
    </x-filament::section>
</x-filament-widgets::widget>
