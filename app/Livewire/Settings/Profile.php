<?php

namespace App\Livewire\Settings;

use App\Helpers\Popcorn;
use Flux\Flux;
use Livewire\Component;

class Profile extends Component
{
    public string $uuid;

    public ?string $tmdb_token;

    public string $name = '';

    public string $username = '';

    public string $email = '';

    public ?string $language = '';

    public ?string $description = '';

    public bool $public_profile = false;

    public ?string $profile_picture = '';

    /**
     * Mount the component.
     */
    public function mount(): void
    {
        $this->uuid = session('app-user')['uuid'];

        $this->tmdb_token = session('app-user')['tmdb_token'];

        $this->name = session('app-user')['name'];

        $this->username = session('app-user')['username'];

        $this->email = session('app-user')['email'];

        $this->language = session('app-user')['language'];

        $this->description = session('app-user')['description'];

        $this->public_profile = session('app-user')['public_profile'];

        $this->profile_picture = session('app-user')['profile_picture'];
    }

    /**
     * Update the profile information for the currently authenticated user.
     */
    public function updateProfileInformation(): void
    {
        $datas = $this->validate([
            'name' => ['required', 'string', 'max:255'],
            'username' => ['required', 'string', 'max:255'],
            'public_profile' => ['boolean'],
            'language' => ['in:fr,en'],
            'description' => ['max:500'],
            'email' => ['required', 'string', 'lowercase', 'email', 'max:255'],
        ]);

        try {
            $user = Popcorn::patch('users/'.$this->uuid, ['data' => $datas]);

            if (! $user->has('data')) {
                Flux::toast(
                    text: __('Something went wrong. Please try again'),
                    variant: 'error',
                );

                return;
            }

            $data = $user->get('data');

            session(['app-user' => [
                'uuid' => $this->uuid,
                'name' => $data->name,
                'username' => $data->username,
                'description' => $data->description,
                'language' => $data->language,
                'email' => $data->email,
                'tmdb_token' => $this->tmdb_token,
                'public_profile' => $this->public_profile,
                'profile_picture' => $data->profile_picture,
            ]]);

            cookie()->queue(cookie('locale', $data->language, 120000));
        } catch (\Exception) {
            Flux::toast(
                text: __('Something went wrong. Please try again'),
                variant: 'error',
            );
        }

        $this->dispatch('profile-updated');
    }
}
