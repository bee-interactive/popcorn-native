<?php

namespace App\Livewire\Support;

use App\Helpers\Popcorn;
use Flux\Flux;
use Illuminate\Support\Facades\Storage;
use Livewire\WithFileUploads;
use LivewireUI\Modal\ModalComponent;
use Override;

class CropImage extends ModalComponent
{
    use WithFileUploads;

    public $user_uuid;

    public $field;

    public $image;

    public $temp_image;

    public string $uuid;

    public string $tmdb_token;

    public bool $public_profile;

    public int $x = 0;

    public int $y = 0;

    public int $width = 300;

    public int $height = 300;

    public int $minWidth = 300;

    public int $minHeight = 300;

    public $decoded_image;

    #[Override]
    public static function modalMaxWidth(): string
    {
        return '2xl';
    }

    public function mount($temp_image, string $uuid, $user_uuid, $field, int $width, int $height, mixed $decoded_image): void
    {
        $this->tmdb_token = session('app-user')['tmdb_token'];

        $this->public_profile = session('app-user')['public_profile'];

        $this->temp_image = $temp_image;

        $this->image = Storage::disk('avatars')->path($this->uuid.'/'.$this->temp_image);

        $this->uuid = $uuid;

        $this->user_uuid = $user_uuid;

        $this->field = $field;

        $this->width = $width;

        $this->height = $height;

        $this->minWidth = $width;

        $this->minHeight = $height;

        $this->decoded_image = $decoded_image;
    }

    public function save(): void
    {
        $user = Popcorn::postWithFile(
            'users/'.$this->uuid.'/avatar',
            'avatar',
            $this->decoded_image,
            'image-name',
            [
                'width' => $this->width,
                'height' => $this->height,
                'x' => $this->x,
                'y' => $this->y,
            ]
        );

        session(['app-user' => [
            'uuid' => $this->uuid,
            'name' => $user['data']->name,
            'username' => $user['data']->username,
            'description' => $user['data']->description,
            'language' => $user['data']->language,
            'email' => $user['data']->email,
            'tmdb_token' => $this->tmdb_token,
            'public_profile' => $this->public_profile,
            'profile_picture' => $user['data']->profile_picture,
        ]]);

        $this->dispatch('data-updated');
        cache()->flush();

        Flux::toast(
            text: __('The image has been uploaded and saved'),
            variant: 'success',
        );

        $this->closeModal();
    }
}
