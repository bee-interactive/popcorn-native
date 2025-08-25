<x-layouts.app :title="$item->name . ' ' . __('- Popcorn')">
    <div>
        <flux:heading size="xl" level="1">{{ $item->name }}</flux:heading>

        <div class="mt-4">
            <div class="grid max-w-4xl sm:grid-cols-2 gap-4 lg:gap-12">
                <div class="relative">
                    @if($item->poster_path)
                        <img class="shadow-lg rounded w-full" src="https://image.tmdb.org/t/p/w400{{ $item->poster_path }}" alt="">
                    @else
                        <img class="shadow-lg rounded w-full" src="{{ asset('img/placeholder.jpg') }}" alt="">
                    @endif

                    @if($item->watched)
                        <div class="absolute z-10 top-2 right-2">
                            <flux:button variant="ghost" size="sm" class="!text-green-400 bg-green-500 fill-green-400" icon="check-circle"></flux:button>
                        </div>
                    @endif

                    <div>
                        <div class="mt-4 flex space-x-4 justify-start">
                            @if(!$item->watched)
                                <div class="flex justify-center">
                                    <flux:button tooltip="{{ __('Mark as viewed') }}" onclick="Livewire.dispatch('openModal', { component: 'item.mark-item-as-viewed', arguments: { uuid: '{{ $item->uuid }}' }})" size="sm" icon="check-circle"></flux:button>
                                </div>
                            @endif
                        </div>
                    </div>
                </div>

                <div class="relative">
                    @if($item->synopsis)
                        <div class="prose">
                            <strong>Synopsis: </strong>
                            <div>{!! $item->synopsis !!}</div>
                        </div>
                    @endif

                    @if($item->note)
                        <div class="prose mt-12">
                            <strong>Note: </strong>
                            <div>{!! $item->note !!}</div>
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>
</x-layouts.app>
