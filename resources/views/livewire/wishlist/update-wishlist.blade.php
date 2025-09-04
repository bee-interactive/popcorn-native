<div>
    <div>
        <div class="p-4">
            <flux:heading size="lg">{{ __('Edit list') }}</flux:heading>
        </div>
    </div>

    <div class="p-4">
        <div class="space-y-6">
            <div>
                <flux:input wire:model="name" label="{{ __('Wishlist name') }}" placeholder="{{ __('Name / topic') }}" />
            </div>

            <div class="flex items-center">
                <flux:checkbox wire:model="is_favorite" label="{{ __('Add to favorites') }}" />
            </div>
        </div>
    </div>

    <div class="p-4 rounded-b border-t bg-white dark:border-zinc-600 dark:bg-zinc-950 flex-wrap flex items-center justify-between">
        <div>
            <flux:button variant="danger" icon="trash" onclick="Livewire.dispatch('openModal', { component: 'wishlist.delete-wishlist', arguments: { uuid: '{{ $uuid }}' } })"></flux:button>
        </div>

        <div class="space-x-2">
            <flux:button variant="filled" wire:click.prevent="$dispatch('closeModal')">{{ __('Cancel') }}</flux:button>

            <flux:button variant="primary" autofocus wire:click="update">
                {{ __('Update') }}
            </flux:button>
        </div>
    </div>
</div>
