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

            cookie()->queue(cookie('locale', $user['data']->language, 120000));

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
