<?php

namespace App\Livewire\Settings;

use App\Livewire\Actions\Logout;
use Illuminate\Support\Facades\Http;
use Livewire\Component;

class DeleteUserForm extends Component
{
    public string $password = '';

    public string $message = '';

    /**
     * Delete the currently authenticated user.
     */
    public function deleteUser(Logout $logout): void
    {
        $this->reset('message');

        $this->validate([
            'password' => ['required', 'string'],
        ]);

        $token = session('app-access-token');
        $user = session('app-user');

        if (! $user || ! isset($user['uuid'])) {
            $this->message = __('User session not found. Please login again.');

            return;
        }

        $response = Http::acceptJson()
            ->withToken($token)
            ->delete(config('services.api.url').'users/'.$user['uuid'], [
                'password' => $this->password,
            ]);

        if ($response->status() === 422) {
            $errors = $response->json('errors', []);

            foreach ($errors as $field => $messages) {
                $this->addError($field, is_array($messages) ? $messages[0] : $messages);
            }

            $this->message = $response->json('message', __('Validation failed. Please check your password.'));

            return;
        }

        if (! $response->successful()) {
            $this->message = $response->json('message', __('Failed to delete account. Please try again.'));

            return;
        }

        $logout();

        $this->redirect('/', navigate: true);
    }
}
