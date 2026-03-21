<x-filament-panels::page>
    <form wire:submit.prevent="save" class="space-y-6">
        {{ $this->form }}

        @php
            $templates = config('template.templates', []);
            $selectedTemplate = (string) data_get($this->data, 'template', config('template.active', 'default'));
            $currentLocale = app()->getLocale();
        @endphp

        <x-filament::section
            heading="Template Gallery"
            description="Templates affect customer-facing pages. Interface color overrides can intentionally make templates feel more alike."
        >
            <div class="grid gap-4" style="grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));">
                @foreach ($templates as $key => $template)
                    @php
                        $name = $template['name'] ?? \Illuminate\Support\Str::headline($key);
                        $description = $template['description'] ?? '';
                        $palette = $template['palette'] ?? [];
                        $primary = $palette['primary'] ?? '#6366F1';
                        $secondary = $palette['secondary'] ?? '#A855F7';
                        $accent = $palette['accent'] ?? '#EC4899';
                        $fonts = $template['fonts'] ?? [];
                        $fontSans = $fonts['sans'] ?? 'System UI';
                        $fontDisplay = $fonts['display'] ?? 'System UI';
                        $isSelected = $selectedTemplate === $key;
                        $previewUrl = route('home', [
                            'locale' => $currentLocale,
                            'template_preview' => $key,
                        ]);
                    @endphp

                    <article @class([
                        'rounded-xl border border-gray-200 bg-white p-4 shadow-sm ring-1 ring-gray-950/5 transition duration-150 dark:border-white/10 dark:bg-gray-900 dark:ring-white/10',
                        'bg-gray-50 ring-2 ring-primary-600 dark:bg-white/5 dark:ring-primary-500' => $isSelected,
                        'hover:bg-gray-50 dark:hover:bg-white/5' => ! $isSelected,
                    ])>
                        <div class="flex items-start justify-between gap-3">
                            <div class="min-w-0">
                                <h3 class="text-sm font-semibold text-gray-950 dark:text-white">{{ $name }}</h3>
                                <p class="mt-1 text-sm leading-6 text-gray-600 dark:text-gray-400">
                                    {{ $description }}
                                </p>
                            </div>

                            @if ($isSelected)
                                <x-filament::badge color="primary">
                                    Selected
                                </x-filament::badge>
                            @endif
                        </div>

                        <div class="mt-4 flex items-center gap-2">
                            <span title="Primary" class="inline-block size-4 rounded-full border border-gray-950/10 dark:border-white/10" style="background-color: {{ $primary }}"></span>
                            <span title="Secondary" class="inline-block size-4 rounded-full border border-gray-950/10 dark:border-white/10" style="background-color: {{ $secondary }}"></span>
                            <span title="Accent" class="inline-block size-4 rounded-full border border-gray-950/10 dark:border-white/10" style="background-color: {{ $accent }}"></span>
                        </div>

                        <p class="mt-3 text-xs font-medium uppercase text-gray-500 dark:text-gray-400">
                            {{ $fontSans }} / {{ $fontDisplay }}
                        </p>

                        <div class="mt-4 flex flex-wrap items-center gap-2">
                            @if ($isSelected)
                                <x-filament::button
                                    type="button"
                                    size="sm"
                                    disabled
                                >
                                    Selected
                                </x-filament::button>
                            @else
                                <x-filament::button
                                    type="button"
                                    size="sm"
                                    color="gray"
                                    outlined
                                    wire:click="$set('data.template', '{{ $key }}')"
                                >
                                    Select
                                </x-filament::button>
                            @endif

                            <x-filament::button
                                tag="a"
                                size="sm"
                                color="gray"
                                outlined
                                href="{{ $previewUrl }}"
                                target="_blank"
                            >
                                Preview
                            </x-filament::button>
                        </div>
                    </article>
                @endforeach
            </div>
        </x-filament::section>

        <x-admin.settings-form-actions message="Reset controls only update this form until you save.">
            <x-filament::button
                type="button"
                color="gray"
                outlined
                wire:click="resetInterfaceColors"
            >
                Reset interface colors
            </x-filament::button>

            <x-filament::button
                type="button"
                color="gray"
                outlined
                wire:click="resetEmailColors"
            >
                Reset email colors
            </x-filament::button>
        </x-admin.settings-form-actions>
    </form>
</x-filament-panels::page>
