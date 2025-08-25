<x-layouts.app :title="__('Page Not Found')">
    <flux:heading size="lg">{{ __('Page Not found!') }}</flux:heading>
    <flux:text class="mt-2">{{ __('The page or item you are looking for does not exist.') }}</flux:text>

    <div class="mt-6">
        <flux:button variant="primary" href="/dashboard">{{ __('Go Home') }}</flux:button>
    </div>
</x-layouts.app>
