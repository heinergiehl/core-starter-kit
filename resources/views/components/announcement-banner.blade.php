@php
    $announcements = \App\Domain\Content\Models\Announcement::allActive();
    $dismissedIds = session('dismissed_announcements', []);
@endphp

@foreach($announcements as $announcement)
    @if(!in_array($announcement->id, $dismissedIds))
        <div 
            data-announcement-banner
            class="border-b {{ $announcement->getTypeClasses() }}"
        >
            <div class="mx-auto max-w-7xl px-4 py-3 sm:px-6 lg:px-8">
                <div class="flex items-center justify-between gap-4">
                    <div class="flex items-center gap-3">
                        <svg class="h-5 w-5 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            {!! $announcement->getTypeIcon() !!}
                        </svg>
                        <div class="flex-1">
                            <p class="text-sm font-medium">
                                {{ $announcement->title }}
                                @if($announcement->message)
                                    <span class="font-normal opacity-80">— {{ $announcement->message }}</span>
                                @endif
                            </p>
                        </div>
                    </div>
                    
                    <div class="flex items-center gap-2">
                        @if($announcement->link_url)
                            <a 
                                href="{{ $announcement->link_url }}" 
                                class="rounded-full px-3 py-1 text-xs font-semibold bg-white/20 hover:bg-white/30 transition"
                            >
                                {{ $announcement->link_text ?: __('Learn more') }}
                            </a>
                        @endif
                        
                        @if($announcement->is_dismissible)
                            <form method="POST" action="{{ route('announcements.dismiss', $announcement) }}" class="inline" data-submit-lock>
                                @csrf
                                <button 
                                    type="submit" 
                                    class="rounded-full p-1 hover:bg-white/20 transition"
                                >
                                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                                    </svg>
                                </button>
                            </form>
                        @endif
                    </div>
                </div>
            </div>
        </div>
    @endif
@endforeach
