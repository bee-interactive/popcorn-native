<x-layouts.app :title="__('Viewed')">
    <flux:heading size="xl" level="1">{{ __('Viewed items') }}</flux:heading>

    <div class="mt-4">
        <x-elements.minimized-search-bar />
    </div>

    <div class="mt-12">
        @if($items)
            <flux:separator text="{{ __('Collection') }}" />

            <div>
                <div class="grid grid-cols-2 gap-2 gap-y-6 lg:gap-y-4 sm:grid-cols-3 md:grid-cols-4 xl:grid-cols-5 2xl:grid-cols-6 lg:gap-4 pt-4">
                    @foreach($items as $item)
                        <livewire:item.item :item="$item" :key="$item->uuid" />
                    @endforeach
                </div>
            </div>
        @else
            <flux:separator text="{{ __('No items yet') }}" />
        @endif
    </div>
</x-layouts.app>
