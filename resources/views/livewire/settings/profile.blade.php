<section class="w-full">
    @include('partials.settings-heading')

    <x-settings.layout :heading="__('Profile')" :subheading="__('Update your name and email address')">
        <form wire:submit="updateProfileInformation" class="my-6 w-full space-y-6">
            <flux:input wire:model="name" :label="__('Name')" type="text" required autofocus autocomplete="name" />

            <flux:input wire:model="username" description="{{ __('Username must be at least 4 characters, may only contain letters, numbers, and underscores.') }}" :label="__('Username')" type="text" required autofocus autocomplete="username" />

            <div>
                <flux:input wire:model="email" :label="__('Email')" type="email" required autocomplete="email" />
            </div>

            <div>
                <flux:textarea rows="auto" resize="none" wire:model="description" label="{{ __('Short bio') }}" />
            </div>

            <div>
                <flux:checkbox wire:model.lazy="public_profile" label="{{ __('Activate public profile') }}" />
            </div>

            <div>
                <flux:select variant="listbox" wire:model="language" searchable label="Interface language" placeholder="Choose your language">
                    <flux:select.option value="en">{{ __('English') }}</flux:select.option>
                    <flux:select.option value="fr">{{ __('French') }}</flux:select.option>
                </flux:select>
            </div>

            <livewire:users.avatar />

            <div class="flex items-center gap-4">
                <div class="flex items-center justify-end">
                    <flux:button variant="primary" type="submit" class="w-full">{{ __('Save') }}</flux:button>
                </div>

                <x-action-message class="me-3" on="profile-updated">
                    {{ __('Saved.') }}
                </x-action-message>
            </div>
        </form>

        <livewire:settings.delete-user-form />
    </x-settings.layout>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/cropperjs/1.6.1/cropper.min.js" integrity="sha512-9KkIqdfN7ipEW6B6k+Aq20PV31bjODg4AA52W+tYtAE0jE0kMx49bjJ3FgvS56wzmyfMUHbQ4Km2b7l9+Y/+Eg==" crossorigin="anonymous" referrerpolicy="no-referrer"></script>
</section>
