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
use App\Services\Admin\BackupHealth;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Spatie\Permission\Models\Role;

class AdminDashboardController extends Controller
{
    public function index(Request $request, BackupHealth $backups): Response
    {
        // This route carries no `can:` middleware, because the dashboard itself
        // is open to any administrator. The gate therefore has to sit on the
        // payload: without `backups.view` the panel's data is never serialised
        // into the page at all, rather than being sent and hidden in the UI.
        $canSeeBackups = $request->user()?->can('backups.view') ?? false;

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
            'backupHealth' => $canSeeBackups ? $backups->report() : null,
        ]);
    }
}
