<?php

namespace App\Livewire\Wishlist;

use App\Helpers\Popcorn;
use Flux\Flux;
use LivewireUI\Modal\ModalComponent;

class UpdateWishlist extends ModalComponent
{
    public string $uuid = '';

    public string $name = '';

    public bool $is_favorite = false;

    public function mount(string $uuid): void
    {
        $this->uuid = $uuid;

        $response = Popcorn::get('wishlists/'.$uuid);
        $wishlist = $response['data'] ?? abort(404);

        $this->name = $wishlist->name;
        $this->is_favorite = $wishlist->is_favorite ?? false;
    }

    public function update(): void
    {
        $this->validate([
            'name' => ['required', 'string', 'max:255'],
            'is_favorite' => ['boolean'],
        ]);

        $data['data'] = [
            'name' => $this->name,
            'is_favorite' => $this->is_favorite,
        ];

        $wishlist = Popcorn::patch('wishlists/'.$this->uuid, $data);

        $this->dispatch('data-updated', $wishlist['data']);

        Flux::toast(
            text: __('Wishlist updated successfully'),
            variant: 'success',
        );

        $this->closeModal();
    }

    public function render()
    {
        return view('livewire.wishlist.update-wishlist');
    }
}
