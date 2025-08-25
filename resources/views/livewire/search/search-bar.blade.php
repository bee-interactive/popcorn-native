<div>
    <flux:command>
        <flux:command.input clearable wire:model.live="query" icon:trailing="magnifying-glass" placeholder="{{ __('Search for a movie / tv show') }}" />

        @if($results)
            <flux:command.items>
                @foreach($results as $result)
                    @if($result['media_type'] === 'movie')
                        <flux:command.item wire:click="save({{ json_encode($result) }})" class="h-auto">
                            <div class="flex space-x-4 items-start">
                                <div class="w-20">
                                    @if($result['poster_path'])
                                        <img src="https://image.tmdb.org/t/p/w200{{ $result['poster_path'] }}" alt="">
                                    @else
                                        <img src="{{ asset('img/placeholder.jpg') }}" alt="">
                                    @endif
                                </div>

                                <div class="flex flex-col h-full justify-between space-y-2 lg:space-y-4">
                                    <div>
                                        <div>
                                            <flux:badge size="sm" color="lime">film</flux:badge>
                                        </div>
                                        <strong>{{ $result['title'] }}</strong>
                                    </div>
                                </div>
                            </div>
                        </flux:command.item>
                    @elseif($result['media_type'] === 'person')
                        <flux:command.item wire:click="save({{ json_encode($result) }})" class="h-auto">
                            <div class="flex space-x-4 items-start">
                                <div class="w-20">
                                    @if($result['profile_path'])
                                        <img src="https://image.tmdb.org/t/p/w200{{ $result['profile_path'] }}" alt="">
                                    @else
                                        <img src="{{ asset('img/placeholder.jpg') }}" alt="">
                                    @endif
                                </div>

                                <div class="flex flex-col justify-between space-y-2 lg:space-y-4">
                                    <div>
                                        <div>
                                            <flux:badge size="sm" color="teal">person</flux:badge>
                                        </div>
                                        <strong>{{ $result['name'] }}</strong>
                                    </div>
                                </div>
                            </div>
                        </flux:command.item>
                    @elseif($result['media_type'] === 'tv')
                        <flux:command.item wire:click="save({{ json_encode($result) }})" class="h-auto">
                            <div class="flex space-x-4 items-start">
                                <div class="w-20">
                                    @if($result['poster_path'])
                                        <img src="https://image.tmdb.org/t/p/w200{{ $result['poster_path'] }}" alt="">
                                    @else
                                        <img src="{{ asset('img/placeholder.jpg') }}" alt="">
                                    @endif
                                </div>

                                <div class="flex flex-col justify-between space-y-2 lg:space-y-4">
                                    <div>
                                        <div>
                                            <flux:badge size="sm" color="orange">tv</flux:badge>
                                        </div>
                                        <strong>{{ $result['name'] }}</strong>
                                    </div>
                                </div>
                            </div>
                        </flux:command.item>
                    @endif
                @endforeach
            </flux:command.items>
        @endif
    </flux:command>

    @php
        $items = App\Helpers\Popcorn::get('items');
        $hasItems = !empty($items['data']);
    @endphp
    @if($hasItems && $layout !== 'minimal')
        <div class="mt-12">
            <flux:separator text="{{ __('Recents additions') }}" />

            <div class="grid grid-cols-2 gap-2 gap-y-6 lg:gap-y-4 sm:grid-cols-3 md:grid-cols-4 xl:grid-cols-5 2xl:grid-cols-6 lg:gap-4 pt-4">
                @foreach($items['data'] as $item)
                    @php
                        // Handle both array and object formats
                        $uuid = is_array($item) ? ($item['uuid'] ?? 'item-'.uniqid()) : ($item->uuid ?? 'item-'.uniqid());
                    @endphp
                    <livewire:item.item :item="$item" :key="$uuid" />
                @endforeach
            </div>
        </div>
    @endif
</div>
