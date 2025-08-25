<section class="w-full">
    @include('partials.settings-heading')

    <x-settings.layout :heading="__('Update tmdb token')" :subheading="__('You’ll need to sign up for a movie database account in order to get a token.')">
        <flux:link class="text-sm underline underline-offset-4" href="https://www.themoviedb.org/" target="_blank">Open an account</flux:link>

        <form wire:submit="updateToken" class="mt-6 space-y-6">
            <flux:input
                wire:model="token"
                :label="__('Your TMDB token')"
                type="text"
                required
                autocomplete="current-token"
            />

            <div class="flex items-center gap-4">
                <div class="flex items-center justify-end">
                    <flux:button variant="primary" type="submit" class="w-full">{{ __('Save') }}</flux:button>
                </div>

                <x-action-message class="me-3" on="token-updated">
                    {{ __('Saved.') }}
                </x-action-message>
            </div>
        </form>
    </x-settings.layout>
</section>
