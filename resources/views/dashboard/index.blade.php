<x-layouts.app :title="__('Dashboard')">
    <div class="flex h-full w-full flex-1 flex-col gap-4 rounded-xl">
        <div class="grid auto-rows-min gap-4">
            <div>
                <x-elements.search-bar :layout="'maximal'" />
            </div>
        </div>
    </div>
</x-layouts.app>
