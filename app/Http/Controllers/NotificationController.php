<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Notifications\DatabaseNotification;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Event notifications written to the database channel (approval decisions, low
 * stock). Separate from the live-computed stock alert groups, which stay in
 * DashboardService and are shared as `alerts`.
 */
class NotificationController extends Controller
{
    /** Rows served to the bell dropdown on every page. */
    public const BELL_LIMIT = 6;

    public function index(Request $request): Response
    {
        $filter = $request->string('filter')->toString() ?: 'unread';

        $query = $request->user()->notifications()
            ->when($filter === 'unread', fn ($q) => $q->whereNull('read_at'))
            ->latest()
            ->limit(100);

        return Inertia::render('Notifications/Index', [
            'notifications' => $query->get()->map(fn ($n) => static::present($n))->values(),
            'filters' => ['filter' => $filter],
            'unreadCount' => $request->user()->unreadNotifications()->count(),
        ]);
    }

    /** Mark one notification read, or all of them when no id is given. */
    public function read(Request $request): RedirectResponse
    {
        $data = $request->validate(['id' => ['nullable', 'string', 'max:64']]);

        if (! empty($data['id'])) {
            // Scoped to the signed-in user so an id cannot be used to clear
            // somebody else's queue.
            $request->user()->unreadNotifications()->whereKey($data['id'])->update(['read_at' => now()]);
        } else {
            $request->user()->unreadNotifications()->update(['read_at' => now()]);
        }

        return back();
    }

    /**
     * Humanized shape for the bell and the list. Falls back to the raw payload
     * for notification types written before this phase.
     *
     * @return array<string, mixed>
     */
    public static function present(DatabaseNotification $notification): array
    {
        $data = $notification->data;
        $kind = $data['type'] ?? 'notice';

        return [
            'id' => $notification->id,
            'type' => $kind,
            'title' => $data['title'] ?? static::fallbackTitle($kind, $data),
            'message' => $data['message'] ?? '',
            'href' => $data['href'] ?? null,
            'read' => $notification->read_at !== null,
            'at' => $notification->created_at?->diffForHumans(),
            'at_full' => $notification->created_at?->toDayDateTimeString(),
        ];
    }

    /** @param array<string, mixed> $data */
    protected static function fallbackTitle(string $kind, array $data): string
    {
        if ($kind === 'low_stock') {
            return ($data['part_number'] ?? 'A part').' is at or below reorder level';
        }

        return 'Notification';
    }
}
