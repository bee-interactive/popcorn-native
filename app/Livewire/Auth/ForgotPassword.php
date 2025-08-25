<?php

namespace App\Livewire\Auth;

use Illuminate\Support\Facades\Http;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('components.layouts.auth')]
class ForgotPassword extends Component
{
    public string $email = '';

    /**
     * Send a password reset link to the provided email address.
     */
    public function sendPasswordResetLink(): void
    {
        $this->validate([
            'email' => ['required', 'string', 'email'],
        ]);

        Http::post(config('services.api.url').'auth/request-reset-link', [
            'email' => $this->only('email'),
        ]);

        session()->flash('status', __('A reset link will be sent if the account exists.'));
    }
}
