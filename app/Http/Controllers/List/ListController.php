<?php

namespace App\Http\Controllers\List;

use Illuminate\View\View;

class ListController
{
    public function __invoke(string $uuid): View
    {
        return view('list.index', [
            'uuid' => $uuid,
        ]);
    }
}
