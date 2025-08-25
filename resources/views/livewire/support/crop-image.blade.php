<div>
    <div>
        <div class="p-4">
            <flux:heading size="lg">{{ __('Profile picture') }}</flux:heading>

            <flux:text>{{ __('Crop your image below to a square format') }}</flux:text>
        </div>
    </div>

    <div class="p-4 w-full">
        <div class="max-h-[500px] w-full relative" wire:ignore x-data="{
            init() {
                const cropper = new Cropper(this.$refs.image, {
                    aspectRatio: {{ $minWidth }}/{{ $minHeight }},
                    autoCropArea: 1,
                    viewMode: 1,
                    responsive: true,
                    minContainerHeight: 500,
                    minCropBoxWidth: 200,
                    minCropBoxHeight: 200,
                    crop(event) {
                        @this.set('x', event.detail.x)
                        @this.set('y', event.detail.y)
                        @this.set('width', event.detail.width)
                        @this.set('height', event.detail.height)
                    }
                });
            }
        }">
            <img x-ref="image" id="image" src="{{ $decoded_image }}" class="w-full" alt="Image">
        </div>
    </div>

    <div class="p-4 rounded-b border-t flex-wrap bg-white dark:border-zinc-600 dark:bg-zinc-950 flex items-center justify-between">
        <flux:button class="select-none" wire:click="$dispatch('closeModal')" variant="filled">{{ __('Cancel') }}</flux:button>

        <flux:button class="select-none" wire:click="save" variant="primary">{{ __('Save') }}</flux:button>
    </div>
</div>
