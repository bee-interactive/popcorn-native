<?php

namespace App\Livewire\Item;

use Livewire\Attributes\On;
use Livewire\Component;

class Item extends Component
{
    public mixed $item;

    public function mount(mixed $item): void
    {
        $this->item = $item;
    }

    #[On('mark-as-viewed')]
    public function render()
    {
        return view('livewire.item.item', [
            'item' => $this->item,
        ]);
    }
}
