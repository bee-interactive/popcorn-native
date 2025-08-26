<div>
    <div>
        <div class="p-4">
            <flux:heading size="lg">{{ __('Save :item for later', ['item' => ($this->result['title'] ?? $this->result['name'])]) }}</flux:heading>
        </div>
    </div>

    <div class="p-4">
        <div class="space-y-6">
            <div>
                <flux:select 
                    label="{{ __('Choose a list and save this entry.') }}" 
                    wire:model.live="wishlist" 
                    variant="listbox" 
                    placeholder="{{ __('Select a list...') }}"
                    :error="$errors->has('wishlist')"
                    required>
                    @foreach(App\Helpers\Popcorn::get('wishlists')['data'] as $wishlist)
                        <flux:select.option value="{{ $wishlist->uuid }}">{{ $wishlist->name }}</flux:select.option>
                    @endforeach
                </flux:select>
                @error('wishlist')
                    <div class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</div>
                @enderror
            </div>

            <div>
                <flux:textarea 
                    wire:model="note" 
                    rows="auto" 
                    label="{{ __('Add a note to this entry') }}" 
                    resize="none" 
                    placeholder="{{ __('Optional note...') }}"
                />
            </div>
        </div>
    </div>

    <div class="p-4 rounded-b border-t flex-wrap bg-white dark:border-zinc-600 dark:bg-zinc-950 flex items-center justify-between">
        <flux:button wire:click="$dispatch('closeModal')" variant="filled">{{ __('Cancel') }}</flux:button>

        <flux:button 
            wire:click="save" 
            variant="primary"
            wire:loading.attr="disabled"
            :disabled="empty($wishlist)">
            <span wire:loading.remove>{{ __('Save') }}</span>
            <span wire:loading>{{ __('Saving...') }}</span>
        </flux:button>
    </div>
</div>
