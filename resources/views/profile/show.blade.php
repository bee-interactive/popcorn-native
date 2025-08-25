<x-layouts.public :title="$user->name . ' (@'. $user->username .') - Popcorn'">
    @include('partials.page-heading')

    <div class="flex mt-12 h-full w-full flex-1 flex-col gap-4">
        <div class="grid auto-rows-min gap-4">
            <div class="text-center flex flex-col justify-center pb-4">
                <div class="mx-auto mb-2">
                    <img src="{{ $user->profile_picture }}" alt="{{ $user->name }}" class="rounded-full h-20 w-20">
                </div>
                <flux:heading size="xl">{{ $user->name }}</flux:heading>

                <div class="max-w-md mx-auto">
                    <flux:text>&#64;{{ $user->username }}</flux:text>
                    <flux:text class="mt-2">{{ $user->description }}</flux:text>
                </div>
            </div>

            @if($user->items)
                <flux:separator text="{{ __('Collection') }}" />

                <div>
                    <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-5 2xl:grid-cols-6 gap-2 gap-y-6 lg:gap-y-4">
                        @foreach($user->items as $item)
                            @if($item->poster_path)
                                <img class="shadow-lg rounded w-full h-full" src="https://image.tmdb.org/t/p/w400{{ $item->poster_path }}" alt="">
                            @else
                                <img class="shadow-lg rounded w-full h-full" src="{{ asset('img/placeholder.jpg') }}" alt="">
                            @endif
                        @endforeach
                    </div>
                </div>
            @else
                <flux:separator text="{{ __('No items yet') }}" />
            @endif
        </div>
    </div>
</x-layouts.public>
