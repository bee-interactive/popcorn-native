<?php

namespace App\Http\Controllers\User;

use App\Helpers\Popcorn;
use Illuminate\View\View;

class UserController
{
    public function __invoke(string $username): View
    {
        $user = Popcorn::get('users/'.$username);

        abort_unless(isset($user['data']), 404);

        return view('profile.show', [
            'user' => $user['data'],
        ]);
    }
}
