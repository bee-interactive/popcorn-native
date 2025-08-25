<?php

namespace App\Http\Controllers\ViewedItems;

use App\Helpers\Popcorn;
use Illuminate\View\View;

class ViewedItemsController
{
    public function __invoke(): View
    {
        $items = Popcorn::get('items?watched=1');

        return view('viewed.index', [
            'items' => $items['data'],
        ]);
    }
}
