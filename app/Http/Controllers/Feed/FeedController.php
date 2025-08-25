<?php

namespace App\Http\Controllers\Feed;

use App\Helpers\Popcorn;
use Illuminate\Http\Request;
use Illuminate\View\View;

class FeedController
{
    public function __invoke(Request $request): View
    {
        $page = $request->query('page', 1);
        $items = Popcorn::get('users-feed?page='.$page);

        // Process the data to limit users per page
        $data = $items['data'] ?? [];
        $perPage = 5;
        $allUsers = [];

        // Flatten all users from all date groups
        foreach ($data as $dateGroup) {
            foreach ($dateGroup->users ?? [] as $user) {
                $allUsers[] = [
                    'date' => $dateGroup->date,
                    'user' => $user,
                ];
            }
        }

        // Paginate users
        $totalUsers = count($allUsers);
        $totalPages = ceil($totalUsers / $perPage);
        $currentPage = max(1, min($page, $totalPages));
        $offset = ($currentPage - 1) * $perPage;
        $paginatedUsers = array_slice($allUsers, $offset, $perPage);

        // Regroup by date
        $groupedData = [];
        foreach ($paginatedUsers as $userData) {
            $date = $userData['date'];
            if (! isset($groupedData[$date])) {
                $groupedData[$date] = (object) [
                    'date' => $date,
                    'users' => [],
                ];
            }
            $groupedData[$date]->users[] = $userData['user'];
        }

        return view('feed.index', [
            'items' => array_values($groupedData),
            'currentPage' => $currentPage,
            'totalPages' => $totalPages,
            'perPage' => $perPage,
        ]);
    }
}
