<section class="w-full">
    @include('partials.settings-heading')

    <x-settings.layout :heading="__('Account')" :subheading="__('Manage your account settings')">
        <livewire:settings.delete-user-form />
    </x-settings.layout>
</section>