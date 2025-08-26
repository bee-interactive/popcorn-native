<?php

namespace App\Livewire\Search;

use App\Helpers\Popcorn;
use Flux\Flux;
use Livewire\Attributes\Validate;
use LivewireUI\Modal\ModalComponent;

class SaveForLater extends ModalComponent
{
    public $result;

    #[Validate('required|string', message: ['required' => 'Please select a list'])]
    public string $wishlist = '';

    public string $note = '';

    public function mount($result): void
    {
        $this->result = $result;
    }

    public function save(): void
    {
        $this->validate();
        
        $data['data'] = [
            'wishlist_uuid' => $this->wishlist,
            'media_type' => $this->result['media_type'],
            'name' => ($this->result['title'] ?? $this->result['name']),
            'synopsis' => ($this->result['overview'] ?? null),
            'backdrop_path' => ($this->result['backdrop_path'] ?? null),
            'poster_path' => ($this->result['poster_path'] ?? $this->result['profile_path'] ?? null),
            'release_date' => (empty($this->result['release_date']) ? null : $this->result['release_date']),
            'note' => $this->note,
            'watched' => false,
        ];

        try {
            Popcorn::post('items', $data);
            
            $this->dispatch('data-updated');

            Flux::toast(
                text: __('The item was saved successfully'),
                variant: 'success',
            );

            $this->closeModal();
        } catch (\Exception $e) {
            Flux::toast(
                text: __('An error occurred while saving the item'),
                variant: 'danger',
            );
        }
    }
}
