<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Spatie\Activitylog\Models\Activity;

class ActivityLogController extends Controller
{
    public function index(Request $request): Response
    {
        $filters = [
            'log_name' => $request->string('log_name')->toString(),
            'event' => $request->string('event')->toString(),
            'search' => $request->string('search')->toString(),
            'from' => $request->string('from')->toString(),
            'to' => $request->string('to')->toString(),
        ];

        $activities = Activity::query()
            ->with('causer:id,name,email')
            ->when($filters['log_name'], fn ($q, $v) => $q->where('log_name', $v))
            ->when($filters['event'], fn ($q, $v) => $q->where('event', $v))
            ->when($filters['search'], fn ($q, $v) => $q->where('description', 'like', "%{$v}%"))
            ->when($filters['from'], fn ($q, $v) => $q->whereDate('created_at', '>=', $v))
            ->when($filters['to'], fn ($q, $v) => $q->whereDate('created_at', '<=', $v))
            ->latest()
            ->paginate(20)
            ->withQueryString()
            ->through(fn (Activity $a) => [
                'id' => $a->id,
                'log_name' => $a->log_name,
                'event' => $a->event,
                'description' => $a->description,
                'causer' => $a->causer?->name ?? 'System',
                'subject_type' => class_basename($a->subject_type ?? ''),
                'properties' => $a->properties,
                'created_at' => $a->created_at?->toDayDateTimeString(),
                'created_for_humans' => $a->created_at?->diffForHumans(),
            ]);

        return Inertia::render('Admin/Activity/Index', [
            'activities' => $activities,
            'filters' => $filters,
            'logNames' => Activity::query()->distinct()->pluck('log_name')->filter()->values(),
            'events' => Activity::query()->whereNotNull('event')->distinct()->pluck('event')->values(),
        ]);
    }
}
