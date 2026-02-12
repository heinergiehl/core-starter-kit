<x-filament-panels::page>
    <form wire:submit.prevent="save">
        {{ $this->form }}

        @php
            $templates = config('template.templates', []);
            $selectedTemplate = (string) data_get($this->data, 'template', config('template.active', 'default'));
            $currentLocale = app()->getLocale();
        @endphp

        <div style="margin-top: 1.5rem; border: 1px solid rgba(148, 163, 184, 0.2); border-radius: 0.75rem; padding: 1rem; background: rgba(15, 23, 42, 0.02);">
            <div style="display: flex; flex-direction: column; gap: 0.25rem;">
                <h3 style="margin: 0; font-size: 1rem; font-weight: 700;">Template Gallery</h3>
                <p style="margin: 0; color: #64748b; font-size: 0.875rem;">
                    Templates affect customer-facing pages. Interface color overrides can intentionally make templates look more alike.
                </p>
            </div>

            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 0.75rem; margin-top: 0.875rem;">
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

                    <div style="border: 1px solid {{ $isSelected ? '#6366F1' : 'rgba(148, 163, 184, 0.35)' }}; border-radius: 0.75rem; padding: 0.75rem; background: {{ $isSelected ? 'rgba(99, 102, 241, 0.06)' : '#ffffff' }};">
                        <div style="display: flex; align-items: center; justify-content: space-between; gap: 0.5rem;">
                            <strong style="font-size: 0.9rem;">{{ $name }}</strong>
                            @if ($isSelected)
                                <span style="font-size: 0.75rem; color: #4338ca; font-weight: 600;">Selected</span>
                            @endif
                        </div>

                        <p style="margin: 0.35rem 0 0; font-size: 0.78rem; color: #64748b; line-height: 1.35;">
                            {{ $description }}
                        </p>

                        <div style="display: flex; align-items: center; gap: 0.375rem; margin-top: 0.65rem;">
                            <span title="Primary" style="display: inline-block; width: 0.9rem; height: 0.9rem; border-radius: 999px; background: {{ $primary }}; border: 1px solid rgba(15, 23, 42, 0.12);"></span>
                            <span title="Secondary" style="display: inline-block; width: 0.9rem; height: 0.9rem; border-radius: 999px; background: {{ $secondary }}; border: 1px solid rgba(15, 23, 42, 0.12);"></span>
                            <span title="Accent" style="display: inline-block; width: 0.9rem; height: 0.9rem; border-radius: 999px; background: {{ $accent }}; border: 1px solid rgba(15, 23, 42, 0.12);"></span>
                        </div>

                        <p style="margin: 0.55rem 0 0; font-size: 0.72rem; color: #475569;">
                            {{ $fontSans }} / {{ $fontDisplay }}
                        </p>

                        <div style="display: flex; gap: 0.5rem; margin-top: 0.65rem; flex-wrap: wrap;">
                            <button
                                type="button"
                                wire:click="$set('data.template', '{{ $key }}')"
                                style="border: 1px solid rgba(99, 102, 241, 0.35); background: #ffffff; color: #4338ca; border-radius: 0.5rem; padding: 0.35rem 0.55rem; font-size: 0.72rem; font-weight: 600; cursor: pointer;"
                            >
                                Select
                            </button>
                            <a
                                href="{{ $previewUrl }}"
                                target="_blank"
                                rel="noopener noreferrer"
                                style="display: inline-block; border: 1px solid rgba(148, 163, 184, 0.4); color: #334155; border-radius: 0.5rem; padding: 0.35rem 0.55rem; font-size: 0.72rem; font-weight: 600; text-decoration: none;"
                            >
                                Preview
                            </a>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>

        <div style="margin-top: 1.5rem; display: flex; flex-wrap: wrap; gap: 0.5rem; justify-content: flex-start;">
            <x-filament::button
                type="button"
                color="gray"
                outlined
                wire:click="resetInterfaceColors"
                wire:loading.attr="disabled"
                wire:target="resetInterfaceColors"
            >
                Reset interface colors
            </x-filament::button>

            <x-filament::button
                type="button"
                color="gray"
                outlined
                wire:click="resetEmailColors"
                wire:loading.attr="disabled"
                wire:target="resetEmailColors"
            >
                Reset email colors
            </x-filament::button>

            <x-filament::button 
                type="submit"
                wire:loading.attr="disabled"
            >
                <span wire:loading.remove wire:target="save">Save changes</span>
                <svg wire:loading wire:target="save" class="inline w-4 h-4 mr-2 -ml-1 text-white animate-spin" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                </svg>
                <span wire:loading wire:target="save">Saving...</span>
            </x-filament::button>
        </div>
    </form>
</x-filament-panels::page>
