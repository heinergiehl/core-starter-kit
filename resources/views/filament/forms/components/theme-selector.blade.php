<div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-5 gap-4" x-data="{ selected: $wire.entangle('data.template') }">
    @foreach($themes as $key => $theme)
        <div 
            @click="selected = '{{ $key }}'"
            :class="selected === '{{ $key }}' ? 'ring-2 ring-primary-500 ring-offset-2 dark:ring-offset-gray-900' : 'hover:ring-2 hover:ring-gray-300 dark:hover:ring-gray-600 hover:ring-offset-2 dark:hover:ring-offset-gray-900'"
            class="relative cursor-pointer rounded-xl overflow-hidden transition-all duration-200 group"
        >
            {{-- Theme Preview Card --}}
            <div 
                class="aspect-[4/3] p-3 flex flex-col"
                style="background-color: {{ $theme['bg'] }}"
            >
                {{-- Color Bar --}}
                <div class="flex gap-1 mb-2">
                    @foreach($theme['colors'] as $color)
                        <div 
                            class="h-2 flex-1 rounded-full"
                            style="background-color: {{ $color }}"
                        ></div>
                    @endforeach
                </div>
                
                {{-- Fake UI Elements --}}
                <div class="flex-1 flex flex-col gap-1.5">
                    <div class="h-2 w-3/4 rounded opacity-40" style="background: linear-gradient(90deg, {{ $theme['colors'][0] }}, {{ $theme['colors'][1] }})"></div>
                    <div class="h-1.5 w-1/2 rounded bg-white/20"></div>
                    <div class="h-1.5 w-2/3 rounded bg-white/10"></div>
                </div>
                
                {{-- Fake Button --}}
                <div 
                    class="mt-auto h-4 w-1/2 rounded-md self-center"
                    style="background: linear-gradient(90deg, {{ $theme['colors'][0] }}, {{ $theme['colors'][1] }})"
                ></div>
            </div>
            
            {{-- Theme Info --}}
            <div class="p-3 bg-white dark:bg-gray-800 border-t border-gray-200 dark:border-gray-700">
                <div class="flex items-center justify-between">
                    <h4 class="font-semibold text-sm text-gray-900 dark:text-white">{{ $theme['name'] }}</h4>
                    <div 
                        x-show="selected === '{{ $key }}'"
                        class="w-5 h-5 rounded-full bg-primary-500 flex items-center justify-center"
                    >
                        <svg class="w-3 h-3 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7"></path>
                        </svg>
                    </div>
                </div>
                <p class="text-xs text-gray-500 dark:text-gray-400 mt-0.5">{{ $theme['tagline'] }}</p>
            </div>
            
            {{-- Hover Overlay --}}
            <div class="absolute inset-0 bg-white/5 opacity-0 group-hover:opacity-100 transition-opacity pointer-events-none"></div>
        </div>
    @endforeach
</div>
