<div>
    <div>
        <div class="p-4">
            <flux:heading size="lg">{{ __('Mark :item as viewed', ['item' => $name]) }}</flux:heading>
        </div>
    </div>

    <div class="p-4">
        <div class="space-y-6">
            <div>
                <flux:textarea wire:model="note" rows="auto" label="{{ __('Add a note to this entry') }}" resize="none" />
            </div>
        </div>
    </div>

    <div class="p-4 rounded-b border-t bg-white dark:border-zinc-600 dark:bg-zinc-950 flex-wrap flex items-center justify-between">
        <flux:button variant="filled" wire:click.prevent="$dispatch('closeModal')">{{ __('Cancel') }}</flux:button>

        <flux:button variant="primary" autofocus wire:click="save">
            {{ __('Save') }}
        </flux:button>
    </div>
</div>
