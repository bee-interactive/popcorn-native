<?php

namespace App\Http\Controllers\Item;

use App\Helpers\Popcorn;
use App\Http\Controllers\Controller;

class ItemController extends Controller
{
    /**
     * Handle the incoming request.
     */
    public function __invoke(string $uuid)
    {
        $item = Popcorn::get('items/'.$uuid);

        return view('items.show', [
            'item' => ($item['data'] ?? abort(404)),
        ]);
    }
}
