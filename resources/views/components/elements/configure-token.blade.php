<flux:callout icon="shield-check" color="blue" inline>
    <flux:callout.heading>{{ __('The Movie Database token is required') }}</flux:callout.heading>

    <flux:callout.text>{{ __('In order to make this application work and search for films and tv shows, you must configure a API token on the Movie Database plateform.') }}</flux:callout.text>

    <x-slot name="actions" class="@md:h-full flex justify-between pr-4 m-0!">
        <flux:button target="_blank" type="link" href="https://www.themoviedb.org/" >Get my token -></flux:button>

        <flux:link href="{{ route('settings.tmdb') }}" class="text-sm">I have my token</flux:link>
    </x-slot>
</flux:callout>
