<section class="w-full">
    @include('partials.settings-heading')

    <x-settings.layout :heading="__('Cache')" :subheading="__('Manage your application cache')">
        <div class="space-y-6">
            <flux:text>
                {{ __('The cache stores temporary data to make the app faster. If you are experiencing issues with outdated information or want to free up storage space, you can clear the cache.') }}
            </flux:text>
            
            <flux:text>
                {{ __('Clearing the cache will remove all temporarily stored data including:') }}
            </flux:text>
            
            <ul class="list-disc pl-6 space-y-1 text-sm text-zinc-600 dark:text-zinc-400">
                <li>{{ __('Search results') }}</li>
                <li>{{ __('Trending content') }}</li>
                <li>{{ __('Movie and TV show details') }}</li>
                <li>{{ __('Wishlist data') }}</li>
                <li>{{ __('User profiles') }}</li>
            </ul>
            
            <flux:text class="text-sm text-zinc-600 dark:text-zinc-400">
                {{ __('Note: This will not log you out or delete any of your personal data.') }}
            </flux:text>
            
            <flux:button wire:click="clearCache" variant="danger" wire:loading.attr="disabled">
                <span wire:loading.remove>{{ __('Clear Cache') }}</span>
                <span wire:loading>{{ __('Clearing...') }}</span>
            </flux:button>
        </div>
    </x-settings.layout>
</section>