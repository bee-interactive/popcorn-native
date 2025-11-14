<?php

namespace App\Livewire\Auth;

use App\Helpers\Popcorn;
use Illuminate\Auth\Events\Lockout;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Validate;
use Livewire\Component;

#[Layout('components.layouts.auth')]
class Login extends Component
{
    #[Validate('required|string|email')]
    public string $email = '';

    #[Validate('required|string')]
    public string $password = '';

    public bool $remember = false;

    /**
     * Handle an incoming authentication request.
     */
    public function login()
    {
        $this->validate();

        $this->ensureIsNotRateLimited();

        $response = Http::post(config('services.api.url').'auth/login', [
            'email' => $this->email,
            'password' => $this->password,
        ]);

        if ($response->ok()) {
            $token = json_decode($response->body())->success->token;
            session(['app-access-token' => $token]);
            cookie()->queue(cookie('app-access-token', $token, 120000));

            $user = Popcorn::post('users/me', $token);

            if (! $user->has('data')) {
                session()->forget('app-access-token');

                return redirect('/login')->with('error', __('Could not load user profile. Please try again.'));
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

            cookie()->queue(cookie('locale', $data->language, 120000));

            Popcorn::invalidateUserCache();

            return redirect('/dashboard');
        }

        return redirect('/login')->with('error', __('Invalid credentials'));
    }

    /**
     * Ensure the authentication request is not rate limited.
     */
    protected function ensureIsNotRateLimited(): void
    {
        if (! RateLimiter::tooManyAttempts($this->throttleKey(), 5)) {
            return;
        }

        event(new Lockout(request()));

        $seconds = RateLimiter::availableIn($this->throttleKey());

        throw ValidationException::withMessages([
            'email' => __('auth.throttle', [
                'seconds' => $seconds,
                'minutes' => ceil($seconds / 60),
            ]),
        ]);
    }

    /**
     * Get the authentication rate limiting throttle key.
     */
    protected function throttleKey(): string
    {
        return Str::transliterate(Str::lower($this->email).'|'.request()->ip());
    }
}
