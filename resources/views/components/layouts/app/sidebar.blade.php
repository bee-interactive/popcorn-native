<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="dark">
    <head>
        @include('partials.head')
    </head>
    <body class="min-h-screen bg-white dark:bg-zinc-800">
        <div class="absolute top-0 left-0 right-0" style="height: 30px; -webkit-app-region: drag;">
            <!-- Your Custom Title Content -->
        </div>

        <flux:sidebar sticky stashable class="border-r border-zinc-200 bg-zinc-50 dark:border-zinc-700 dark:bg-zinc-900">
            <flux:sidebar.toggle class="lg:hidden !bg-zinc-100 dark:!bg-black" icon="x-mark" />

            <div class="pt-6">
                <a href="{{ route('dashboard') }}" class="mr-5 flex items-center space-x-2" wire:navigate>
                    <x-app-logo />
                </a>
            </div>

            <flux:navlist variant="outline" class="space-y-2">
                <flux:navlist.group :heading="__('Your popcorn space')" class="grid">
                    <flux:navlist.item icon="home" :href="route('dashboard')" :current="request()->routeIs('dashboard')" wire:navigate>{{ __('Dashboard') }}</flux:navlist.item>
                </flux:navlist.group>

                <flux:navlist.item icon="film" :href="route('trending.index')" :current="request()->routeIs('trending.index')" wire:navigate>{{ __('Trending') }}</flux:navlist.item>

                <div class="mt-2">
                    <flux:navlist.item icon="heart" :href="route('viewed.index')" :current="request()->routeIs('viewed.index')" wire:navigate>{{ __('Viewed') }}</flux:navlist.item>
                </div>

                <div>
                    <flux:navlist.item icon="users" :href="route('feed.index')" :current="request()->routeIs('feed.index')" wire:navigate>{{ __('Feed') }}</flux:navlist.item>
                </div>

                <flux:navlist.group expandable heading="{{ __('My lists') }}" class="grid mt-2">
                    <div>
                        <livewire:wishlist.user-wishlists />
                    </div>

                    <flux:button onclick="Livewire.dispatch('openModal', { component: 'wishlist.create-wishlist' })" class="cursor-pointer" icon="plus" size="sm" variant="ghost">{{ __('add list') }}</flux:button>
                </flux:navlist.group>
            </flux:navlist>

            <flux:spacer />

            <flux:dropdown position="bottom" class="hidden sm:block" align="start">
                @isset(session('app-user')['profile_picture'])
                    <flux:profile
                        :name="session('app-user')['name']"
                        avatar="{{ session('app-user')['profile_picture'] }}"
                        icon-trailing="chevrons-up-down"
                    />
                @endisset

                <flux:menu class="w-[220px]">
                    <flux:menu.radio.group>
                        <div class="p-0 text-sm font-normal">
                            <div class="flex items-center gap-2 px-1 py-1.5 text-left text-sm">
                                <span class="relative flex h-8 w-8 shrink-0 overflow-hidden rounded-lg">
                                    @isset(session('app-user')['profile_picture'])
                                        <flux:profile
                                            :name="session('app-user')['name']"
                                            avatar="{{ session('app-user')['profile_picture'] }}"
                                        />
                                    @endisset
                                </span>

                                <div class="grid flex-1 text-left text-sm leading-tight">
                                    @isset(session('app-user')['name'])
                                        <span class="truncate font-semibold">{{ session('app-user')['name'] }}</span>
                                        <span class="truncate text-xs">{{ session('app-user')['email'] }}</span>
                                    @endif
                                </div>
                            </div>
                        </div>
                    </flux:menu.radio.group>

                    <flux:menu.separator />

                    <flux:menu.radio.group>
                        @isset(session('app-user')['name'])
                            <flux:menu.item :href="route('profile.show', ['username' => session('app-user')['username']])" icon="user">{{ __('Public profile') }}</flux:menu.item>
                        @endif
                    </flux:menu.radio.group>

                    <flux:menu.separator />

                    <flux:menu.radio.group>
                        <flux:menu.item :href="route('settings.profile')" icon="cog" wire:navigate>{{ __('Settings') }}</flux:menu.item>
                    </flux:menu.radio.group>

                    <flux:menu.separator />

                    <form method="POST" action="{{ route('logout') }}" class="w-full">
                        @csrf
                        <flux:menu.item as="button" type="submit" icon="arrow-right-start-on-rectangle" class="w-full">
                            {{ __('Log out') }}
                        </flux:menu.item>
                    </form>
                </flux:menu>
            </flux:dropdown>
        </flux:sidebar>

        <flux:header class="lg:hidden">
            <flux:sidebar.toggle class="lg:hidden dark:bg-black/20" icon="bars-2" inset="left" />

            <a href="{{ route('dashboard') }}" class="mr-5 ml-2 flex items-center space-x-2" wire:navigate>
                <x-app-logo />
            </a>

            <flux:spacer />

            <flux:dropdown position="top" align="end">
                @isset(session('app-user')['profile_picture'])
                    <flux:profile
                        icon-trailing="chevron-down"
                        avatar="{{ session('app-user')['profile_picture'] }}"
                    />
                @endisset

                <flux:menu>
                    <flux:menu.radio.group>
                        <div class="p-0 text-sm font-normal">
                            <div class="flex items-center gap-2 px-1 py-1.5 text-left text-sm">
                                <span class="relative flex h-8 w-8 shrink-0 overflow-hidden rounded-lg">
                                    @isset(session('app-user')['name'])
                                        <span
                                            class="flex h-full w-full items-center justify-center rounded-lg bg-neutral-200 text-black dark:bg-neutral-700 dark:text-white"
                                        >
                                            <img src="{{ session('app-user')['profile_picture'] }}" alt="{{ session('app-user')['name'] }}">
                                        </span>
                                    @endif
                                </span>

                                <div class="grid flex-1 text-left text-sm leading-tight">
                                    @isset(session('app-user')['name'])
                                        <span class="truncate font-semibold">{{ session('app-user')['name'] }}</span>
                                        <span class="truncate text-xs">{{ session('app-user')['email'] }}</span>
                                    @endif
                                </div>
                            </div>
                        </div>
                    </flux:menu.radio.group>

                    <flux:menu.separator />

                    <flux:menu.radio.group>
                        @isset(session('app-user')['name'])
                            <flux:menu.item :href="route('profile.show', ['username' => session('app-user')['username']])" icon="user">{{ __('Public profile') }}</flux:menu.item>
                        @endif
                    </flux:menu.radio.group>

                    <flux:menu.separator />

                    <flux:menu.radio.group>
                        <flux:menu.item :href="route('settings.profile')" icon="cog" wire:navigate>{{ __('Settings') }}</flux:menu.item>
                    </flux:menu.radio.group>

                    <flux:menu.separator />

                    <form method="POST" action="{{ route('logout') }}" class="w-full">
                        @csrf
                        <flux:menu.item as="button" type="submit" icon="arrow-right-start-on-rectangle" class="w-full">
                            {{ __('Log out') }}
                        </flux:menu.item>
                    </form>
                </flux:menu>
            </flux:dropdown>
        </flux:header>

        {{ $slot }}

        @fluxScripts

        @livewire('wire-elements-modal')

        <flux:toast position="bottom right" />
    </body>
</html>
