<?php

namespace App\Http\Controllers;

use App\Services\Dashboard\DashboardService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function __invoke(Request $request, DashboardService $dashboard): Response
    {
        $user = $request->user();
        $aggregates = $dashboard->aggregates();

        return Inertia::render('Dashboard', [
            'alertCards' => $dashboard->alertCardsFor($user, $aggregates['alerts']),
            'kpis' => $aggregates['kpis'],
            'charts' => $aggregates['charts'],
            'activity' => $dashboard->recentActivity($user, 12),
        ]);
    }
}
