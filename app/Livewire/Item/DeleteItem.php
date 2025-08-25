<?php

namespace App\Livewire\Item;

use App\Helpers\Popcorn;
use Flux\Flux;
use LivewireUI\Modal\ModalComponent;
use Override;

class DeleteItem extends ModalComponent
{
    public string $uuid;

    #[Override]
    public static function modalMaxWidth(): string
    {
        return 'md';
    }

    public function mount(string $uuid): void
    {
        $this->uuid = $uuid;
    }

    public function delete(): void
    {
        Popcorn::delete('items/'.$this->uuid);

        $this->dispatch('data-updated');

        Flux::toast(
            text: __('The element has been deleted successfully'),
            variant: 'success',
        );

        $this->closeModal();
    }
}
