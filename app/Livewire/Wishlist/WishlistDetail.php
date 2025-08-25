<?php

namespace App\Livewire\Wishlist;

use App\Helpers\Popcorn;
use Livewire\Attributes\On;
use Livewire\Component;

class WishlistDetail extends Component
{
    public string $uuid;

    public $wishlist;

    public function mount(string $uuid): void
    {
        $this->uuid = $uuid;
        $this->loadWishlist();
    }

    #[On('data-updated')]
    public function refreshData(): void
    {
        $this->loadWishlist(false);
    }

    private function loadWishlist(bool $useCache = true): void
    {
        $response = Popcorn::get('wishlists/'.$this->uuid, null, null, $useCache);
        $this->wishlist = $response['data'] ?? abort(404);
    }

    public function render()
    {
        return view('livewire.wishlist.wishlist-detail');
    }
}
