<?php

namespace App\Livewire\Wishlist;

use App\Helpers\Popcorn;
use Livewire\Attributes\On;
use Livewire\Component;

class UserWishlists extends Component
{
    #[On('data-updated')]
    public function render()
    {
        $wishlists = Popcorn::get('wishlists');

        return view('livewire.wishlist.user-wishlists', [
            'wishlists' => $wishlists,
        ]);
    }
}
