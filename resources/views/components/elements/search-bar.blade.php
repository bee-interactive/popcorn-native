@props([
    'layout',
])

@isset(session('app-user')['tmdb_token'])
    @php
        $wishlists = App\Helpers\Popcorn::get('wishlists');
        $hasWishlists = !empty($wishlists['data']);
    @endphp
    @if(!$hasWishlists)
        <flux:callout icon="exclamation-triangle">
            <flux:callout.heading>{{ __('You don’t have any list yet') }}</flux:callout.heading>
            <flux:callout.text>{{ __('Create your first one by clicking on the button below') }}</flux:callout.text>

            <x-slot name="actions">
                <flux:button onclick="Livewire.dispatch('openModal', { component: 'wishlist.create-wishlist' })">{{ __('Create a list') }}</flux:button>
            </x-slot>
        </flux:callout>
    @else
        <livewire:search.search-bar layout="{{ $layout }}" />
    @endif
@else
    <x-elements.configure-token />
@endisset
