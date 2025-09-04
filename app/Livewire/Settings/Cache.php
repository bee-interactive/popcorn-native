<?php

namespace App\Livewire\Settings;

use App\Helpers\Popcorn;
use Flux\Flux;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('components.layouts.app')]
class Cache extends Component
{
    public function clearCache(): void
    {
        Popcorn::invalidateUserCache();

        Flux::toast(
            text: __('Cache cleared successfully'),
            variant: 'success',
        );
    }

    public function render()
    {
        return view('livewire.settings.cache');
    }
}
