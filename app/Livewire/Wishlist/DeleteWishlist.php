<?php

namespace App\Livewire\Wishlist;

use App\Helpers\Popcorn;
use Flux\Flux;
use LivewireUI\Modal\ModalComponent;
use Override;

class DeleteWishlist extends ModalComponent
{
    public string $uuid = '';

    public string $name = '';

    public function mount(string $uuid): void
    {
        $this->uuid = $uuid;

        $response = Popcorn::get('wishlists/'.$uuid);

        if (! $response->has('data')) {
            abort(404);
        }

        $wishlist = $response->get('data');

        $this->name = $wishlist->name;
    }

    public function delete(): void
    {
        $response = Popcorn::delete('wishlists/'.$this->uuid);

        if ($response->has('error')) {
            Flux::toast(
                text: __('Error deleting wishlist'),
                variant: 'danger',
            );

            return;
        }

        Popcorn::invalidateUserCache();

        Flux::toast(
            text: __('Wishlist deleted successfully'),
            variant: 'success',
        );

        $this->closeModal();

        $this->dispatch('wishlist-deleted');
    }

    public function render()
    {
        return view('livewire.wishlist.delete-wishlist');
    }

    #[Override]
    public static function modalMaxWidth(): string
    {
        return 'md';
    }
}
