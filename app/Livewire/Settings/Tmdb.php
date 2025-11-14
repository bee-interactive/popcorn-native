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

        Popcorn::post('users/tmdb-token', [
            'tmdb_token' => $validated['token'],
        ]);

        $user = Popcorn::post('users/me');

        if (! $user->has('data')) {
            throw ValidationException::withMessages([
                'token' => __('Could not verify token. Please try again.'),
            ]);
        }

        $data = $user->get('data');

        session(['app-user' => [
            'uuid' => $data->uuid,
            'name' => $data->name,
            'username' => $data->username,
            'description' => $data->description,
            'language' => $data->language,
            'email' => $data->email,
            'public_profile' => $data->public_profile,
            'tmdb_token' => $data->tmdb_token,
            'profile_picture' => $data->profile_picture,
        ]]);

        $this->dispatch('token-updated');
    }
}
