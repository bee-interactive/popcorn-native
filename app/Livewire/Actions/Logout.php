<?php

namespace App\Livewire\Actions;

use Illuminate\Support\Facades\Session;

class Logout
{
    /**
     * Log the current user out of the application.
     */
    public function __invoke()
    {
        session()->forget('app-access-token');
        session()->forget('app-user');
        cookie()->queue(cookie()->forget('app-access-token'));

        Session::invalidate();
        Session::regenerateToken();

        return redirect(route('login'));
    }
}
