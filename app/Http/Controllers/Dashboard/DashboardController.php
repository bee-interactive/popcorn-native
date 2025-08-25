<?php

namespace App\Http\Controllers\Dashboard;

use Illuminate\View\View;

class DashboardController
{
    public function __invoke(): View
    {
        return view('dashboard.index');
    }
}
