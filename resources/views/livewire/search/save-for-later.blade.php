<div>
    <div>
        <div class="p-4">
            <flux:heading size="lg">{{ __('Save :item for later', ['item' => ($this->result['title'] ?? $this->result['name'])]) }}</flux:heading>
        </div>
    </div>

    <div class="p-4">
        <div class="space-y-6">
            <div>
                <flux:select label="{{ __('Choose a list and save this entry.') }}" wire:model="wishlist" variant="listbox" placeholder="Choose wishlist...">
                    @foreach(App\Helpers\Popcorn::get('wishlists')['data'] as $wishlist)
                        <flux:select.option value="{{ $wishlist->uuid }}">{{ $wishlist->name }}</flux:select.option>
                    @endforeach
                </flux:select>
            </div>

            <div>
                <flux:textarea wire:model="note" rows="auto" label="{{ __('Add a note to this entry') }}" resize="none" />
            </div>
        </div>
    </div>

    <div class="p-4 rounded-b border-t flex-wrap bg-white dark:border-zinc-600 dark:bg-zinc-950 flex items-center justify-between">
        <flux:button wire:click="$dispatch('closeModal')" variant="filled">{{ __('Cancel') }}</flux:button>

        <flux:button wire:click="save" variant="primary">{{ __('Save') }}</flux:button>
    </div>
</div>
