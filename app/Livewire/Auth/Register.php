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

            if (! $token) {
                $this->message = __('Login failed. No token received.');

                return redirect('/login');
            }

            session(['app-access-token' => $token]);

            try {
                $user = Popcorn::get('users/me');

                if (! $user->has('data')) {
                    session()->forget('app-access-token');
                    $this->message = __('Registration succeeded but could not load profile. Please login.');

                    return redirect('/login');
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

                return redirect('/');
            } catch (\Exception $e) {
                session()->forget('app-access-token');
                $this->message = __('Registration succeeded. Please login.');

                return redirect('/login');
            }
        }

        $this->message = __('Account created but login failed. Please try logging in manually.');

        return redirect('/login');
    }
}
