<?php

namespace App\Livewire\Settings;

use App\Helpers\Popcorn;
use Illuminate\Validation\ValidationException;
use Livewire\Component;

class Tmdb extends Component
{
    public ?string $token = '';

    /**
     * Mount the component.
     */
    public function mount(): void
    {
        $this->token = session('app-user')['tmdb_token'];
    }

    /**
     * Update the password for the currently authenticated user.
     */
    public function updateToken(): void
    {
        try {
            $validated = $this->validate([
                'token' => ['required', 'string'],
            ]);
        } catch (ValidationException $e) {
            $this->reset('token');

            throw $e;
        }

        $user = Popcorn::post('users/tmdb-token', [
            'tmdb_token' => $validated['token'],
        ]);

        $user = Popcorn::post('users/me');

        session(['app-user' => [
            'uuid' => $user['data']->uuid,
            'name' => $user['data']->name,
            'username' => $user['data']->username,
            'description' => $user['data']->description,
            'language' => $user['data']->language,
            'email' => $user['data']->email,
            'public_profile' => $user['data']->public_profile,
            'tmdb_token' => $user['data']->tmdb_token,
            'profile_picture' => $user['data']->profile_picture,
        ]]);

        $this->dispatch('token-updated');
    }
}
