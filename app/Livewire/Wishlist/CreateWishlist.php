<?php

namespace App\Livewire\Wishlist;

use App\Helpers\Popcorn;
use Flux\Flux;
use LivewireUI\Modal\ModalComponent;

class CreateWishlist extends ModalComponent
{
    public string $name = '';

    public bool $is_favorite = false;

    public function save(): void
    {
        $this->validate([
            'name' => ['required', 'string', 'max:255'],
            'is_favorite' => ['boolean'],
        ]);

        $data['data'] = [
            'name' => $this->name,
            'is_favorite' => $this->is_favorite,
        ];

        $wishlist = Popcorn::post('wishlists', $data);

        if (! $wishlist->has('data')) {
            Flux::toast(
                text: __('An error occurred. Please try again.'),
                variant: 'error',
            );

            return;
        }

        $this->name = '';
        $this->is_favorite = false;

        $this->dispatch('data-updated', $wishlist->get('data'));

        Flux::toast(
            text: __('Wishlist created successfully'),
            variant: 'success',
        );

        $this->closeModal();
    }
}
