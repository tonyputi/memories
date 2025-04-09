<x-filament-panels::page>
    <div x-data="{
        observe() {
            const observer = new IntersectionObserver((entries) => {
                entries.forEach(entry => {
                    if (entry.isIntersecting) {
                        @this.loadMore()
                    }
                })
            })
    
            observer.observe(this.$el)
        }
    }" class="space-y-8">
        @foreach ($this->media as $date => $dateMedia)
            <div class="space-y-4">
                <h2 class="text-xl font-bold">{{ \Carbon\Carbon::parse($date)->format('d F Y') }}</h2>

                <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-4 xl:grid-cols-5 gap-4">
                    @foreach ($dateMedia as $medium)
                        <div class="relative group aspect-square overflow-hidden rounded-lg shadow-sm hover:shadow-lg transition-all duration-300"
                            x-data="{ isPlaying: false }" @click="if (!isPlaying) $refs.video?.play()">
                            @if (str_starts_with($medium->type, 'video/'))
                                <div class="w-full h-full bg-black">
                                    <video x-ref="video"
                                        src="{{ $medium->disk->storage()->temporaryUrl($medium->path, now()->addHour()) }}"
                                        poster="{{ $medium->disk->storage()->url($medium->path) }}?thumb=1"
                                        class="w-full h-full object-contain" controls preload="none"
                                        @play="isPlaying = true" @pause="isPlaying = false"></video>
                                    <div class="absolute inset-0 flex items-center justify-center pointer-events-none"
                                        x-show="!isPlaying">
                                        <svg class="w-16 h-16 text-white opacity-80" fill="currentColor"
                                            viewBox="0 0 24 24">
                                            <path d="M8 5v14l11-7z" />
                                        </svg>
                                    </div>
                                </div>
                            @else
                                <img src="{{ $medium->disk->storage()->temporaryUrl($medium->path, now()->addHour()) }}"
                                    alt="{{ $medium->name }}"
                                    class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-300"
                                    loading="lazy" />
                            @endif

                            <div class="absolute inset-0 bg-gradient-to-t from-black/50 to-transparent opacity-0 group-hover:opacity-100 transition-opacity duration-300"
                                x-show="!isPlaying">
                                <div class="absolute bottom-0 left-0 right-0 p-3 text-white">
                                    <div class="flex items-center gap-2">
                                        <p class="text-sm font-medium truncate flex-1">{{ $medium->name }}</p>
                                        @if (str_starts_with($medium->type, 'video/'))
                                            <span class="text-xs opacity-75">
                                                <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 24 24">
                                                    <path d="M8 5v14l11-7z" />
                                                </svg>
                                            </span>
                                        @endif
                                    </div>
                                    <div class="flex items-center gap-2 mt-1 text-xs opacity-75">
                                        @if ($medium->meta->get('camera.make'))
                                            <span>{{ $medium->meta->get('camera.make') }}
                                                {{ $medium->meta->get('camera.model') }}</span>
                                        @endif
                                        @if ($medium->meta->get('width'))
                                            <span>{{ $medium->meta->get('width') }}x{{ $medium->meta->get('height') }}</span>
                                        @endif
                                        @if (str_starts_with($medium->type, 'video/'))
                                            <span>{{ $medium->type }}</span>
                                        @endif
                                    </div>
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        @endforeach

        <div x-init="observe" class="flex justify-center p-4">
            <div class="animate-spin rounded-full h-8 w-8 border-b-2 border-primary-500"></div>
        </div>
    </div>
</x-filament-panels::page>
