<?php

namespace App\Services\Dashboard;

use App\Models\Part;
use App\Models\Requisition;
use App\Models\StockBalance;
use App\Models\WorkOrder;
use App\Services\Stock\StockAlertService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Spatie\Activitylog\Models\Activity;

/**
 * The consolidated command-centre aggregator (Phase 4, spec §7 module 1).
 *
 * The heavy, user-independent numbers (alert counts, KPIs, chart series) are
 * computed with efficient aggregate queries and cached for 60s; the cache is
 * busted the moment stock is posted or a document status changes (see
 * AppServiceProvider), so the per-request cost stops growing with volume.
 *
 * Per-user work (which alert cards to show, the permission-filtered activity
 * feed) is cheap and runs fresh on top of the cached aggregates.
 */
class DashboardService
{
    public const CACHE_KEY = 'dashboard.aggregates.v1';

    /** Work-order statuses that count as "open" everywhere on the dashboard. */
    public const OPEN_WO_STATUSES = ['open', 'in_progress'];

    /** log_name → permission required to see that activity line in the feed. */
    protected const ACTIVITY_PERMISSIONS = [
        'stock' => 'stores.view',
        'serial' => 'parts.view',
        'aircraft' => 'aircraft.view',
        'aircraft_type' => 'aircraft.view',
        'work_order' => 'work_orders.view',
        'requisition' => 'requisitions.view',
        'srv' => 'receiving.view',
        'siv' => 'issues.view',
        'user' => 'users.view',
        'role' => 'roles.view',
    ];

    public function __construct(protected StockAlertService $alerts)
    {
    }

    /** Cached bundle powering the dashboard + the shared alerts/badges path. */
    public function aggregates(): array
    {
        return Cache::remember(self::CACHE_KEY, now()->addSeconds(60), fn () => [
            'alerts' => $this->alertCounts(),
            'kpis' => $this->kpis(),
            'charts' => $this->charts(),
            'samples' => $this->alertSamples(),
        ]);
    }

    /**
     * The notification-bell payload: the same alert cards, permission-filtered,
     * each carrying a few sample lines. Reads entirely from the 60s cache — the
     * per-request cost is a handful of `can()` checks, nothing more.
     *
     * @return array{count: int, groups: array<int, array<string, mixed>>}
     */
    public function sharedAlerts($user): array
    {
        if (! $user) {
            return ['count' => 0, 'groups' => []];
        }

        $aggregates = $this->aggregates();
        $samples = $aggregates['samples'];

        $groups = collect($this->alertCardsFor($user, $aggregates['alerts']))
            ->filter(fn ($card) => $card['count'] > 0)
            ->map(fn ($card) => [
                ...$card,
                'items' => $samples[$card['key']] ?? [],
            ])
            ->values()
            ->all();

        return [
            'count' => array_sum(array_column($groups, 'count')),
            'groups' => $groups,
        ];
    }

    /**
     * A few representative lines per alert category for the bell dropdown.
     *
     * @return array<string, array<int, array<string, string>>>
     */
    public function alertSamples(int $per = 5): array
    {
        $partLine = fn ($p) => ['label' => $p->part_number.' - '.$p->description, 'href' => route('parts.show', $p->id)];

        return [
            'below_reorder' => $this->alerts->belowReorder()->take($per)->map($partLine)->values()->all(),
            'below_min' => $this->alerts->belowMin()->take($per)->map($partLine)->values()->all(),
            'above_max' => $this->alerts->aboveMax()->take($per)->map($partLine)->values()->all(),
            'expired' => $this->alerts->expired()->take($per)->map(fn ($b) => [
                'label' => optional($b->part)->part_number.' - expired '.$b->expiry_date?->toDateString(),
                'href' => $b->part ? route('parts.show', $b->part_id) : '#',
            ])->values()->all(),
            'expiring' => $this->alerts->expiringWithin(90)->take($per)->map(fn ($b) => [
                'label' => optional($b->part)->part_number.' - expiring '.$b->expiry_date?->toDateString(),
                'href' => $b->part ? route('parts.show', $b->part_id) : '#',
            ])->values()->all(),
            'quarantine' => Part::query()
                ->whereHas('balances', fn ($q) => $q->where('quantity', '>', 0)
                    ->whereHas('store', fn ($s) => $s->where('type', 'quarantine')))
                ->limit($per)->get()->map($partLine)->values()->all(),
            'requisitions_pending' => Requisition::where('status', 'submitted')
                ->orderByDesc('id')->limit($per)->get()->map(fn ($r) => [
                    'label' => $r->requisition_no.' - '.$r->full_description,
                    'href' => route('requisitions.show', $r->id),
                ])->values()->all(),
            'open_work_orders' => WorkOrder::whereIn('status', self::OPEN_WO_STATUSES)
                ->orderByDesc('id')->limit($per)->get()->map(fn ($w) => [
                    'label' => $w->wo_ref.' - '.$w->title,
                    'href' => route('work-orders.show', $w->id),
                ])->values()->all(),
        ];
    }

    /** Forget the cached aggregates (called on every posting / status change). */
    public function bust(): void
    {
        Cache::forget(self::CACHE_KEY);
    }

    // ---- Alerts (spec §5.9) ---------------------------------------------

    /** @return array<string, int> */
    public function alertCounts(): array
    {
        return [
            'below_reorder' => $this->alerts->belowReorder()->count(),
            'below_min' => $this->alerts->belowMin()->count(),
            'above_max' => $this->alerts->aboveMax()->count(),
            'expiring' => $this->alerts->expiringWithin(90)->count(),
            'expired' => $this->alerts->expired()->count(),
            'quarantine' => StockBalance::where('quantity', '>', 0)
                ->whereHas('store', fn ($q) => $q->where('type', 'quarantine'))
                ->count(),
            'requisitions_pending' => Requisition::where('status', 'submitted')->count(),
            'open_work_orders' => WorkOrder::whereIn('status', self::OPEN_WO_STATUSES)->count(),
        ];
    }

    /**
     * The alert panel definitions — each card carries the count, the route it
     * drills into (pre-filtered), and the permission needed to act on it.
     * Only cards the user is permitted to act on are returned.
     *
     * @return array<int, array<string, mixed>>
     */
    public function alertCardsFor($user, ?array $counts = null): array
    {
        $counts ??= $this->aggregates()['alerts'];

        $cards = [
            ['key' => 'below_reorder', 'label' => 'Below Reorder', 'tone' => 'destructive', 'permission' => 'parts.view',
                'route' => 'parts.index', 'params' => ['state' => 'below_reorder']],
            ['key' => 'below_min', 'label' => 'Below Minimum', 'tone' => 'destructive', 'permission' => 'parts.view',
                'route' => 'parts.index', 'params' => ['state' => 'below_min']],
            ['key' => 'above_max', 'label' => 'Above Maximum', 'tone' => 'warning', 'permission' => 'parts.view',
                'route' => 'parts.index', 'params' => ['state' => 'above_max']],
            ['key' => 'expired', 'label' => 'Expired', 'tone' => 'destructive', 'permission' => 'parts.view',
                'route' => 'parts.index', 'params' => ['state' => 'expired']],
            ['key' => 'expiring', 'label' => 'Expiring ≤90d', 'tone' => 'warning', 'permission' => 'parts.view',
                'route' => 'parts.index', 'params' => ['state' => 'expiring']],
            ['key' => 'quarantine', 'label' => 'Awaiting Certification', 'tone' => 'info', 'permission' => 'quarantine.certify',
                'route' => 'stores.index', 'params' => []],
            ['key' => 'requisitions_pending', 'label' => 'Pending Approval', 'tone' => 'info', 'permission' => 'requisitions.approve',
                'route' => 'requisitions.index', 'params' => ['status' => 'submitted']],
            ['key' => 'open_work_orders', 'label' => 'Open Work Orders', 'tone' => 'brand', 'permission' => 'work_orders.view',
                'route' => 'work-orders.index', 'params' => ['status' => 'open']],
        ];

        return collect($cards)
            ->filter(fn ($c) => $user && $user->can($c['permission']))
            ->map(fn ($c) => [
                'key' => $c['key'],
                'label' => $c['label'],
                'tone' => $c['tone'],
                'count' => $counts[$c['key']] ?? 0,
                'route' => $c['route'],
                'params' => $c['params'],
            ])
            ->values()
            ->all();
    }

    // ---- KPI tiles -------------------------------------------------------

    /** @return array<string, mixed> */
    public function kpis(): array
    {
        // Total value of on-hand stock where a unit price is known, excluding
        // fuel (fuel is reported in litres, not naira).
        $stockValue = (float) StockBalance::query()
            ->join('parts', 'parts.id', '=', 'stock_balances.part_id')
            ->whereNull('parts.deleted_at')
            ->whereNotNull('parts.unit_price')
            ->where('parts.is_fuel', false)
            ->sum(DB::raw('stock_balances.quantity * parts.unit_price'));

        $fuelLitres = (float) StockBalance::query()
            ->join('parts', 'parts.id', '=', 'stock_balances.part_id')
            ->whereNull('parts.deleted_at')
            ->where('parts.is_fuel', true)
            ->sum('stock_balances.quantity');

        return [
            'distinct_parts' => Part::count(),
            'stock_value' => round($stockValue, 2),
            'open_work_orders' => WorkOrder::whereIn('status', self::OPEN_WO_STATUSES)->count(),
            'fuel_litres' => round($fuelLitres, 2),
        ];
    }

    // ---- Charts (server-side aggregation) -------------------------------

    /** @return array<string, mixed> */
    public function charts(): array
    {
        return [
            'movements_trend' => $this->movementsTrend(),
            'consumption_by_type' => $this->consumptionByType(),
            'receiving_vs_issuing' => $this->receivingVsIssuing(),
        ];
    }

    /**
     * Stock in vs out over the last 12 ISO weeks. Aggregated to daily rows in
     * SQL (portable DATE()), then bucketed to weeks in PHP — at most ~168 rows
     * cross the boundary regardless of ledger size (no N+1, no raw ledger).
     *
     * @return array<int, array<string, mixed>>
     */
    protected function movementsTrend(): array
    {
        $weeks = 12;
        $start = now()->startOfWeek()->subWeeks($weeks - 1);

        $daily = DB::table('stock_movements')
            ->selectRaw('DATE(posted_at) as d, direction, SUM(quantity) as qty')
            ->where('posted_at', '>=', $start)
            ->groupBy('d', 'direction')
            ->get();

        // Seed 12 empty week buckets keyed by their Monday.
        $buckets = [];
        for ($i = 0; $i < $weeks; $i++) {
            $weekStart = (clone $start)->addWeeks($i);
            $buckets[$weekStart->format('Y-m-d')] = [
                'week' => $weekStart->format('d M'),
                'in' => 0.0,
                'out' => 0.0,
            ];
        }

        foreach ($daily as $row) {
            $key = Carbon::parse($row->d)->startOfWeek()->format('Y-m-d');
            if (! isset($buckets[$key])) {
                continue;
            }
            $buckets[$key][$row->direction === 'in' ? 'in' : 'out'] += (float) $row->qty;
        }

        return array_values($buckets);
    }

    /**
     * Total quantity issued grouped by aircraft type (spec: consumption by
     * aircraft type). Cross-type consumables bucket under "Cross-type".
     *
     * @return array<int, array<string, mixed>>
     */
    protected function consumptionByType(): array
    {
        return DB::table('stock_movements')
            ->join('parts', 'parts.id', '=', 'stock_movements.part_id')
            ->leftJoin('aircraft_types', 'aircraft_types.id', '=', 'parts.aircraft_type_id')
            ->where('stock_movements.direction', 'out')
            ->where('stock_movements.movement_type', 'issue')
            ->selectRaw("COALESCE(aircraft_types.name, 'Cross-type') as type, SUM(stock_movements.quantity) as issued")
            ->groupBy('type')
            ->orderByDesc('issued')
            ->get()
            ->map(fn ($r) => ['type' => $r->type, 'issued' => (float) $r->issued])
            ->all();
    }

    /**
     * Received vs issued quantity per month over the last 12 months.
     *
     * @return array<int, array<string, mixed>>
     */
    protected function receivingVsIssuing(): array
    {
        $months = 12;
        $start = now()->startOfMonth()->subMonths($months - 1);

        $daily = DB::table('stock_movements')
            ->selectRaw('DATE(posted_at) as d, movement_type, direction, SUM(quantity) as qty')
            ->where('posted_at', '>=', $start)
            ->groupBy('d', 'movement_type', 'direction')
            ->get();

        $buckets = [];
        for ($i = 0; $i < $months; $i++) {
            $monthStart = (clone $start)->addMonths($i);
            $buckets[$monthStart->format('Y-m')] = [
                'month' => $monthStart->format('M'),
                'received' => 0.0,
                'issued' => 0.0,
            ];
        }

        foreach ($daily as $row) {
            $key = Carbon::parse($row->d)->format('Y-m');
            if (! isset($buckets[$key])) {
                continue;
            }
            if ($row->direction === 'in' && in_array($row->movement_type, ['receiving', 'fuel_receive'], true)) {
                $buckets[$key]['received'] += (float) $row->qty;
            } elseif ($row->direction === 'out' && in_array($row->movement_type, ['issue', 'fuel_issue'], true)) {
                $buckets[$key]['issued'] += (float) $row->qty;
            }
        }

        return array_values($buckets);
    }

    // ---- Recent activity feed -------------------------------------------

    /**
     * Latest audit-log lines the user is permitted to see, humanized.
     */
    public function recentActivity($user, int $limit = 12): Collection
    {
        return Activity::query()
            ->with('causer:id,name')
            ->latest()
            ->limit($limit * 3) // over-fetch, then permission-filter down
            ->get()
            ->filter(fn (Activity $a) => $this->canSeeActivity($user, $a->log_name))
            ->take($limit)
            ->map(fn (Activity $a) => [
                'id' => $a->id,
                'log_name' => $a->log_name,
                'description' => $this->humanize($a),
                'causer' => $a->causer?->name ?? 'System',
                'event' => $a->event,
                'at' => $a->created_at?->diffForHumans(),
                'at_iso' => $a->created_at?->toIso8601String(),
            ])
            ->values();
    }

    /** Turn a bare Spatie event ("created") into a readable line ("Part created"). */
    protected function humanize(Activity $a): string
    {
        $desc = (string) $a->description;
        if (in_array($desc, ['created', 'updated', 'deleted'], true) && $a->subject_type) {
            return class_basename($a->subject_type).' '.$desc;
        }

        return $desc;
    }

    protected function canSeeActivity($user, ?string $logName): bool
    {
        if (! $user) {
            return false;
        }

        $permission = self::ACTIVITY_PERMISSIONS[$logName] ?? null;

        // Unmapped, low-sensitivity log names are visible to any authenticated user.
        return $permission === null || $user->can($permission);
    }
}
