<?php

namespace App\Livewire\Auth;

use App\Helpers\Popcorn;
use Illuminate\Support\Facades\Http;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('components.layouts.auth')]
class Register extends Component
{
    public string $name = '';

    public string $username = '';

    public string $email = '';

    public string $password = '';

    public string $message = '';

    public array $apiErrors = [];

    public string $password_confirmation = '';

    /**
     * Handle an incoming registration request.
     */
    public function register()
    {
        $this->reset(['message', 'apiErrors']);

        $validated = $this->validate([
            'name' => ['required', 'string', 'max:255'],
            'username' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'lowercase', 'email', 'max:255'],
            'password' => ['required', 'string', 'confirmed'],
        ]);

        $registerResponse = Http::accept('application/json')->post(config('services.api.url').'auth/register', [
            'name' => $validated['name'],
            'username' => $validated['username'],
            'email' => $validated['email'],
            'password' => $validated['password'],
        ]);

        if ($registerResponse->status() === 422) {
            $errors = $registerResponse->json('errors', []);

            foreach ($errors as $field => $messages) {
                $this->addError($field, is_array($messages) ? $messages[0] : $messages);
            }

            $this->message = $registerResponse->json('message', __('Validation failed. Please check the errors below.'));

            return null;
        }

        if (! $registerResponse->successful()) {
            $this->message = $registerResponse->json('message', __('Registration failed. Please try again.'));

            return null;
        }

        $loginResponse = Http::accept('application/json')->post(config('services.api.url').'auth/login', [
            'email' => $validated['email'],
            'password' => $validated['password'],
        ]);

        if ($loginResponse->successful()) {
            $token = $loginResponse->json('success.token');
            session(['app-access-token' => $token]);

            $user = Popcorn::post('users/me', $token);

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

            return redirect('/');
        }

        $this->message = __('Account created but login failed. Please try logging in manually.');

        return redirect('/login');
    }
}
