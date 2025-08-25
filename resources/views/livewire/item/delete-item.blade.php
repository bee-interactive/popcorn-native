<div>
    <div>
        <div class="p-4">
            <flux:heading size="lg">{{ __('Are you sure?') }}</flux:heading>
        </div>
    </div>

    <div class="p-4">
        <flux:text>{{ __('The item will be permanently deleted and cannot be recovered') }}</flux:text>
    </div>

    <div class="p-4 rounded-b border-t flex-wrap bg-white dark:border-zinc-600 dark:bg-zinc-950 flex items-center justify-between">
        <flux:button variant="filled" wire:click.prevent="$dispatch('closeModal')">{{ __('Cancel') }}</flux:button>

        <flux:button variant="danger" autofocus wire:click="delete">
            {{ __('Yes, delete') }}
        </flux:button>
    </div>
</div>
