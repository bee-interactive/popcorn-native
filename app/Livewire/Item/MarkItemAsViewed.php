<?php

namespace App\Livewire\Item;

use App\Helpers\Popcorn;
use Flux\Flux;
use LivewireUI\Modal\ModalComponent;

class MarkItemAsViewed extends ModalComponent
{
    public string $uuid;

    public mixed $item;

    public string $name;

    public string $media_type;

    public ?string $synopsis = null;

    public ?string $backdrop_path = null;

    public ?string $poster_path = null;

    public ?string $release_date = null;

    public ?string $note = null;

    public function mount(string $uuid): void
    {
        $this->uuid = $uuid;

        $this->item = Popcorn::get('items/'.$uuid);

        if (! $this->item->has('data')) {
            abort(404, 'Item not found');
        }

        $data = $this->item->get('data');

        $this->name = $data->name;
        $this->media_type = $data->media_type;
        $this->synopsis = $data->synopsis;
        $this->backdrop_path = $data->backdrop_path;
        $this->poster_path = $data->poster_path;
        $this->release_date = $data->release_date;
        $this->note = $data->note;
    }

    public function save(): void
    {
        Popcorn::patch('items/'.$this->uuid, [
            'data' => [
                'name' => $this->name,
                'media_type' => $this->media_type,
                'synopsis' => $this->synopsis,
                'backdrop_path' => $this->backdrop_path,
                'poster_path' => $this->poster_path,
                'release_date' => $this->release_date,
                'watched' => true,
                'note' => $this->note,
            ],
        ]);

        $this->dispatch('mark-as-viewed')->to(Item::class);

        Flux::toast(
            text: __('The element has been marked as viewed successfully'),
            variant: 'success',
        );

        $this->closeModal();
    }
}
