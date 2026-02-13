@props([
    'mobilePosition' => 'top-4 inset-x-4',
    'position' => 'sm:top-4 sm:right-4 sm:inset-x-auto',
])

{{--
    Livewire Toast Listener
    
    Listens for 'notify' events from Livewire components and displays toasts.
    Include this once in your layout.
--}}

<div
    x-data="{
        toasts: [],
        add(message, type = 'success') {
            const id = Date.now();
            this.toasts.push({ id, message, type });
            setTimeout(() => this.remove(id), 5000);
        },
        remove(id) {
            this.toasts = this.toasts.filter(t => t.id !== id);
        },
        init() {
            @if (session()->has('success')) this.add(@js(session('success')), 'success'); @endif
            @if (session()->has('error')) this.add(@js(session('error')), 'error'); @endif
            @if (session()->has('warning')) this.add(@js(session('warning')), 'warning'); @endif
            @if (session()->has('info')) this.add(@js(session('info')), 'info'); @endif
        }
    }"
    x-on:notify.window="add($event.detail.message, $event.detail.type)"
    class="fixed {{ $mobilePosition }} {{ $position }} z-[200] flex flex-col gap-3 pointer-events-none"
    aria-live="polite"
>
    <template x-for="toast in toasts" :key="toast.id">
        <div
            x-show="true"
            x-transition:enter="transition ease-out duration-300"
            x-transition:enter-start="opacity-0 translate-x-8"
            x-transition:enter-end="opacity-100 translate-x-0"
            x-transition:leave="transition ease-in duration-200"
            x-transition:leave-start="opacity-100 translate-x-0"
            x-transition:leave-end="opacity-0 translate-x-8"
            class="pointer-events-auto flex items-center gap-3 rounded-xl px-4 py-3 shadow-lg backdrop-blur-md border min-w-[280px] max-w-[400px]"
            :class="{
                'bg-emerald-500/10 border-emerald-500/20 text-emerald-700 dark:text-emerald-300': toast.type === 'success',
                'bg-red-500/10 border-red-500/20 text-red-700 dark:text-red-300': toast.type === 'error',
                'bg-amber-500/10 border-amber-500/20 text-amber-700 dark:text-amber-300': toast.type === 'warning',
                'bg-blue-500/10 border-blue-500/20 text-blue-700 dark:text-blue-300': toast.type === 'info'
            }"
        >
            {{-- Icon --}}
            <div class="shrink-0">
                <template x-if="toast.type === 'success'">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                    </svg>
                </template>
                <template x-if="toast.type === 'error'">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                    </svg>
                </template>
                <template x-if="toast.type === 'warning'">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
                    </svg>
                </template>
                <template x-if="toast.type === 'info'">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                    </svg>
                </template>
            </div>
            
            {{-- Message --}}
            <span class="text-sm font-medium flex-1" x-text="toast.message"></span>
            
            {{-- Close Button --}}
            <button 
                @click="remove(toast.id)" 
                class="shrink-0 opacity-60 hover:opacity-100 transition-opacity"
            >
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                </svg>
            </button>
        </div>
    </template>
</div>
