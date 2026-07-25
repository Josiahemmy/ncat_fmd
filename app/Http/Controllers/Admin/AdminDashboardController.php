<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Aircraft;
use App\Models\ApprovalLevel;
use App\Models\AircraftType;
use App\Models\AtaChapter;
use App\Models\DocumentCounter;
use App\Models\Store;
use App\Models\User;
use Inertia\Inertia;
use Inertia\Response;
use Spatie\Permission\Models\Role;

class AdminDashboardController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('Admin/Dashboard', [
            'counts' => [
                'users' => User::count(),
                'activeUsers' => User::where('is_active', true)->count(),
                'roles' => Role::count(),
                'aircraft' => Aircraft::count(),
                'types' => AircraftType::count(),
                'stores' => Store::count(),
                'ataChapters' => AtaChapter::count(),
                'counters' => DocumentCounter::count(),
                'approvalLevels' => ApprovalLevel::where('is_active', true)->count(),
            ],
        ]);
    }
}
