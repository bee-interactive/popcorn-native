<div>
    <div>
        <div class="p-4">
            <flux:heading size="lg">{{ __('Delete list') }}: {{ $name }}</flux:heading>
        </div>
    </div>

    <div class="p-4">
        <div class="space-y-4">
            <flux:text>
                {{ __('Are you sure you want to delete this list?') }}
            </flux:text>

            <flux:text>
                {{ __('This action cannot be undone. All items in this list will be permanently removed.') }}
            </flux:text>
        </div>
    </div>

    <div class="p-4 rounded-b border-t bg-white dark:border-zinc-600 dark:bg-zinc-950 flex-wrap flex items-center justify-end space-x-2">
        <flux:button variant="filled" wire:click.prevent="$dispatch('closeModal')">{{ __('Cancel') }}</flux:button>

        <flux:button variant="danger" wire:click="delete">
            {{ __('Delete list') }}
        </flux:button>
    </div>
</div>
