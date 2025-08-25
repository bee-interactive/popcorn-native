<x-layouts.app :title="__('Trending')">
    <div>
        <flux:heading size="xl" level="1">{{ __('Trending') }}</flux:heading>
        <flux:text class="mt-2">{{ __('Discover items that are currently trending') }}</flux:text>

        <div class="mt-4">
            <x-elements.minimized-search-bar />
        </div>

        <div class="mt-12">
            <flux:separator text="{{ __('Elements') }}" />

            <div class="grid grid-cols-2 gap-2 gap-y-6 lg:gap-y-4 sm:grid-cols-3 md:grid-cols-4 xl:grid-cols-5 2xl:grid-cols-6 lg:gap-4 pt-4">
                @foreach($results as $result)
                    <div class="relative" x-data="{ visible: false }" @mouseover="visible = true" @mouseleave="visible = false">
                        <div class="relative h-full">
                            <div x-show="visible" x-cloak x-transition:enter="transition ease-out duration-300 opacity-0"
                            x-transition:enter-start="opacity-0"
                            x-transition:enter-end="opacity-100"
                            x-transition:leave="transition ease-in duration-200 opacity-100"
                            x-transition:leave-start="opacity-100"
                            x-transition:leave-end="opacity-0" class="absolute rounded flex flex-col justify-between bg-slate-600/90 inset-0 p-4 z-20">
                                <div>
                                    <h3 class="transition-all font-semibold duration-200 leading-4 text-white">
                                        {{ $result['title'] ?? $result['name'] ?? '' }}
                                    </h3>
                                    
                                    @if(!empty($result['overview']))
                                        <div class="mt-4 border-t border-white/60 pt-4">
                                            <p class="text-white text-sm">{{ str($result['overview'])->limit(80) }}</p>
                                        </div>
                                    @endif
                                </div>
                                
                                <div>
                                    <div class="flex justify-center">
                                        <flux:button 
                                            tooltip="{{ __('Add to list') }}" 
                                            onclick="Livewire.dispatch('openModal', { component: 'search.save-for-later', arguments: { result: {{ json_encode($result) }} }})" 
                                            size="sm" 
                                            icon="plus"
                                            variant="primary">
                                        </flux:button>
                                    </div>
                                </div>
                            </div>
                            
                            @isset($result['poster_path'])
                                <img class="shadow-lg rounded w-full h-full" src="https://image.tmdb.org/t/p/w400{{ $result['poster_path'] }}" alt="{{ $result['title'] ?? $result['name'] ?? '' }}">
                            @else
                                <img class="shadow-lg rounded w-full h-full" src="{{ asset('img/placeholder.jpg') }}" alt="{{ $result['title'] ?? $result['name'] ?? '' }}">
                            @endisset
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    </div>
</x-layouts.app>
