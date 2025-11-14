<?php

namespace App\Livewire\Users;

use App\Helpers\Popcorn;
use Flux\Flux;
use Illuminate\Validation\ValidationException;
use Livewire\Component;
use Livewire\WithFileUploads;
use Spatie\Image\Image;

class Avatar extends Component
{
    public string $uuid;

    use WithFileUploads;

    public $width = 300;

    public $height = 300;

    public $avatar;

    public ?string $avatarBase64 = null;

    public $tmdb_token;

    public $public_profile;

    public function mount(): void
    {
        $this->uuid = session('app-user')['uuid'];

        $this->tmdb_token = session('app-user')['tmdb_token'];

        $this->public_profile = session('app-user')['public_profile'];
    }

    public function saveAvatar(): void
    {
        if ($this->avatarBase64 === null || $this->avatarBase64 === '' || $this->avatarBase64 === '0') {
            throw ValidationException::withMessages([
                'avatarBase64' => 'No image provided.',
            ]);
        }

        // Match and extract base64 image data
        if (in_array(preg_match('/^data:image\/(jpeg|jpg|png);base64,/', $this->avatarBase64, $matches), [0, false], true)) {
            throw ValidationException::withMessages([
                'avatarBase64' => 'The image must be a JPG, JPEG or PNG.',
            ]);
        }

        $extension = strtolower($matches[1]);
        $base64Image = substr($this->avatarBase64, strpos($this->avatarBase64, ',') + 1);
        $decodedImage = base64_decode($base64Image, true);

        if ($decodedImage === false) {
            throw ValidationException::withMessages([
                'avatarBase64' => 'The image could not be decoded.',
            ]);
        }

        $maxSizeInBytes = 10 * 1024 * 1024;

        if (strlen($decodedImage) > $maxSizeInBytes) {
            throw ValidationException::withMessages([
                'avatarBase64' => 'The image must not be greater than 10MB.',
            ]);
        }

        $this->dispatch('openModal', 'support.crop-image', [
            'temp_image' => 'avatar-uuid.'.$extension,
            'uuid' => 'avatar-uuid',
            'user_uuid' => $this->uuid,
            'field' => 'avatar',
            'width' => $this->width,
            'height' => $this->height,
            'decoded_image' => $this->avatarBase64,
        ]);
    }

    public function delete(): void
    {
        $user = Popcorn::post('users/'.$this->uuid.'/avatar/delete');

        if (! $user->has('data')) {
            Flux::toast(
                text: __('An error occurred. Please try again.'),
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

        $this->dispatch('data-updated');
        cache()->flush();

        Flux::toast(
            text: __('The image has been deleted'),
            variant: 'success',
        );
    }
}
