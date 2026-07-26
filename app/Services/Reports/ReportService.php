<?php

namespace App\Services\Reports;

use App\Models\Store;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\LazyCollection;

/**
 * The reports engine (spec §7 module 10). Each report resolves to a title, a
 * column set, and a LAZY row stream — the same definition drives both the
 * on-screen table (take N) and the streamed CSV export (iterate all), so a full
 * ledger export never materialises in memory. All queries honour the filters.
 */
class ReportService
{
    public const REPORTS = [
        'stock-summary' => 'Stock Summary',
        'movements' => 'Movement Register',
        'expiry' => 'Expiry Report',
        'consumption' => 'Per-Aircraft Consumption',
        'quarantine' => 'Quarantine Aging',
        'outstanding-loans' => 'Outstanding Loans',
        'shipments-in-transit' => 'Shipments In Transit',
    ];

    /**
     * Reports that need a module permission on top of `reports.view`. Holding
     * "view reports" should not become a side door into a module the user was
     * deliberately not given.
     */
    public const EXTRA_PERMISSIONS = [
        'outstanding-loans' => 'loans.view',
        'shipments-in-transit' => 'shipping.view',
    ];

    public function permissionFor(string $key): ?string
    {
        return self::EXTRA_PERMISSIONS[$key] ?? null;
    }

    public function titleFor(string $key): string
    {
        return self::REPORTS[$key] ?? 'Report';
    }

    public function exists(string $key): bool
    {
        return array_key_exists($key, self::REPORTS);
    }

    /** @return array<int, string> */
    public function columns(string $key): array
    {
        return match ($key) {
            'stock-summary' => ['Part No.', 'Description', 'Store', 'On Hand', 'Min', 'Reorder', 'Max', 'Unit Price (₦)', 'Value (₦)', 'State'],
            'movements' => ['Date', 'Part No.', 'Store', 'Type', 'Direction', 'Qty', 'Balance', 'Aircraft', 'Reference', 'User'],
            'expiry' => ['Part No.', 'Description', 'Batch', 'Year', 'Expiry', 'Days', 'Status'],
            'consumption' => ['Registration', 'Type', 'Issues', 'Qty Issued'],
            'quarantine' => ['Part No.', 'Description', 'Qty', 'Oldest Received', 'Days in Quarantine'],
            'outstanding-loans' => ['Direction', 'Counterparty', 'Item', 'Serial', 'Qty', 'From Store', 'Started', 'Due', 'Days Overdue', 'Status'],
            'shipments-in-transit' => ['Reference', 'Vendor', 'Source Order', 'Carrier', 'AWB', 'Latest Status', 'Last Event', 'Days Since Last Event', 'Expected', 'Days Overdue', 'Overdue'],
            default => [],
        };
    }

    /**
     * Lazy rows (associative arrays keyed by column label) for a report.
     *
     * @param  array<string, mixed>  $filters
     */
    public function rows(string $key, array $filters = []): LazyCollection
    {
        return match ($key) {
            'stock-summary' => $this->stockSummary($filters),
            'movements' => $this->movements($filters),
            'expiry' => $this->expiry($filters),
            'consumption' => $this->consumption($filters),
            'quarantine' => $this->quarantine($filters),
            'outstanding-loans' => $this->outstandingLoans($filters),
            'shipments-in-transit' => $this->shipmentsInTransit($filters),
            default => LazyCollection::make([]),
        };
    }

    protected function stockSummary(array $f): LazyCollection
    {
        $q = DB::table('stock_balances')
            ->join('parts', 'parts.id', '=', 'stock_balances.part_id')
            ->join('stores', 'stores.id', '=', 'stock_balances.store_id')
            ->whereNull('parts.deleted_at')
            // Stock summary reports what NCAT holds of its own. Borrowed stock
            // has its own report (outstanding-loans) and never lands here.
            ->where('stores.owned', true)
            ->when($f['store'] ?? null, fn ($q, $v) => $q->where('stores.id', $v))
            ->when($f['search'] ?? null, fn ($q, $v) => $q->where(fn ($w) => $w
                ->where('parts.part_number', 'like', "%{$v}%")->orWhere('parts.description', 'like', "%{$v}%")))
            ->orderBy('parts.part_number')
            ->select('parts.part_number', 'parts.description', 'stores.name as store', 'stock_balances.quantity',
                'parts.min_level', 'parts.reorder_level', 'parts.max_level', 'parts.unit_price');

        return $q->cursor()->map(function ($r) {
            $onHand = (float) $r->quantity;
            return [
                'Part No.' => $r->part_number,
                'Description' => $r->description,
                'Store' => $r->store,
                'On Hand' => $onHand,
                'Min' => (float) $r->min_level,
                'Reorder' => (float) $r->reorder_level,
                'Max' => $r->max_level !== null ? (float) $r->max_level : null,
                'Unit Price (₦)' => $r->unit_price !== null ? (float) $r->unit_price : null,
                'Value (₦)' => $r->unit_price !== null ? round($onHand * (float) $r->unit_price, 2) : null,
                'State' => $this->stockState($onHand, $r),
            ];
        })->when(($f['state'] ?? null), fn ($c, $v) => $c->filter(fn ($row) => $row['State'] === $v));
    }

    protected function stockState(float $onHand, object $r): string
    {
        if ($r->min_level > 0 && $onHand <= (float) $r->min_level) {
            return 'below_min';
        }
        if ($r->reorder_level > 0 && $onHand <= (float) $r->reorder_level) {
            return 'below_reorder';
        }
        if ($r->max_level !== null && $onHand > (float) $r->max_level) {
            return 'above_max';
        }

        return 'ok';
    }

    protected function movements(array $f): LazyCollection
    {
        $q = DB::table('stock_movements')
            ->join('parts', 'parts.id', '=', 'stock_movements.part_id')
            ->join('stores', 'stores.id', '=', 'stock_movements.store_id')
            ->leftJoin('aircraft', 'aircraft.id', '=', 'stock_movements.aircraft_id')
            ->leftJoin('users', 'users.id', '=', 'stock_movements.user_id')
            ->when($f['store'] ?? null, fn ($q, $v) => $q->where('stores.id', $v))
            ->when($f['part'] ?? null, fn ($q, $v) => $q->where('parts.id', $v))
            ->when($f['type'] ?? null, fn ($q, $v) => $q->where('stock_movements.movement_type', $v))
            ->when($f['user'] ?? null, fn ($q, $v) => $q->where('users.id', $v))
            ->when($f['from'] ?? null, fn ($q, $v) => $q->whereDate('stock_movements.posted_at', '>=', $v))
            ->when($f['to'] ?? null, fn ($q, $v) => $q->whereDate('stock_movements.posted_at', '<=', $v))
            ->orderBy('stock_movements.posted_at')->orderBy('stock_movements.id')
            ->select('stock_movements.posted_at', 'parts.part_number', 'stores.name as store',
                'stock_movements.movement_type', 'stock_movements.direction', 'stock_movements.quantity',
                'stock_movements.balance_after', 'aircraft.registration', 'stock_movements.reference', 'users.name as user');

        return $q->cursor()->map(fn ($r) => [
            'Date' => $r->posted_at ? substr($r->posted_at, 0, 10) : null,
            'Part No.' => $r->part_number,
            'Store' => $r->store,
            'Type' => $r->movement_type,
            'Direction' => $r->direction,
            'Qty' => (float) $r->quantity,
            'Balance' => (float) $r->balance_after,
            'Aircraft' => $r->registration,
            'Reference' => $r->reference,
            'User' => $r->user,
        ]);
    }

    protected function expiry(array $f): LazyCollection
    {
        $window = (int) ($f['days'] ?? 90);
        $scope = $f['scope'] ?? 'all'; // all | expired | expiring

        $q = DB::table('part_batches')
            ->join('parts', 'parts.id', '=', 'part_batches.part_id')
            ->whereNotNull('part_batches.expiry_date')
            ->when($scope === 'expired', fn ($q) => $q->whereDate('part_batches.expiry_date', '<', today()))
            ->when($scope === 'expiring', fn ($q) => $q
                ->whereDate('part_batches.expiry_date', '>=', today())
                ->whereDate('part_batches.expiry_date', '<=', today()->addDays($window)))
            ->when($scope === 'all', fn ($q) => $q->whereDate('part_batches.expiry_date', '<=', today()->addDays($window)))
            ->orderBy('part_batches.expiry_date')
            ->select('parts.part_number', 'parts.description', 'part_batches.batch_number',
                'part_batches.batch_year', 'part_batches.expiry_date');

        return $q->cursor()->map(function ($r) {
            $expiry = \Illuminate\Support\Carbon::parse($r->expiry_date);
            $days = (int) round(now()->startOfDay()->diffInDays($expiry->startOfDay(), false));

            return [
                'Part No.' => $r->part_number,
                'Description' => $r->description,
                'Batch' => $r->batch_number,
                'Year' => $r->batch_year,
                'Expiry' => $expiry->toDateString(),
                'Days' => $days,
                'Status' => $days < 0 ? 'expired' : 'expiring',
            ];
        });
    }

    protected function consumption(array $f): LazyCollection
    {
        $q = DB::table('stock_movements')
            ->join('aircraft', 'aircraft.id', '=', 'stock_movements.aircraft_id')
            ->leftJoin('aircraft_types', 'aircraft_types.id', '=', 'aircraft.aircraft_type_id')
            ->where('stock_movements.direction', 'out')
            ->whereIn('stock_movements.movement_type', ['issue', 'fuel_issue'])
            ->when($f['type'] ?? null, fn ($q, $v) => $q->where('aircraft_types.id', $v))
            ->when($f['from'] ?? null, fn ($q, $v) => $q->whereDate('stock_movements.posted_at', '>=', $v))
            ->when($f['to'] ?? null, fn ($q, $v) => $q->whereDate('stock_movements.posted_at', '<=', $v))
            ->groupBy('aircraft.registration', 'aircraft_types.name')
            ->orderByDesc(DB::raw('SUM(stock_movements.quantity)'))
            ->select('aircraft.registration', 'aircraft_types.name as type',
                DB::raw('COUNT(*) as issues'), DB::raw('SUM(stock_movements.quantity) as qty'));

        return $q->cursor()->map(fn ($r) => [
            'Registration' => $r->registration,
            'Type' => $r->type ?? '—',
            'Issues' => (int) $r->issues,
            'Qty Issued' => (float) $r->qty,
        ]);
    }

    /**
     * Loans still out in either direction, with days overdue (spec §12.7).
     * Overdue is computed from due_date at read time, so the report cannot
     * disagree with the screens.
     */
    protected function outstandingLoans(array $f): LazyCollection
    {
        $q = DB::table('loans')
            ->leftJoin('vendors', 'vendors.id', '=', 'loans.vendor_id')
            ->leftJoin('parts', 'parts.id', '=', 'loans.part_id')
            ->leftJoin('part_serials', 'part_serials.id', '=', 'loans.part_serial_id')
            ->leftJoin('stores', 'stores.id', '=', 'loans.from_store_id')
            ->when($f['direction'] ?? null, fn ($q, $v) => $q->where('loans.direction', $v))
            ->when(($f['scope'] ?? 'open') === 'open', fn ($q) => $q->where('loans.status', 'on_loan'))
            ->when(($f['scope'] ?? null) === 'overdue', fn ($q) => $q->where('loans.status', 'on_loan')
                ->whereNotNull('loans.due_date')->whereDate('loans.due_date', '<', today()))
            ->when($f['from'] ?? null, fn ($q, $v) => $q->whereDate('loans.started_at', '>=', $v))
            ->when($f['to'] ?? null, fn ($q, $v) => $q->whereDate('loans.started_at', '<=', $v))
            ->orderBy('loans.due_date')->orderBy('loans.id')
            ->select('loans.direction', 'loans.party_name', 'loans.item_description', 'loans.serial_text',
                'loans.quantity', 'loans.started_at', 'loans.due_date', 'loans.status',
                'vendors.name as vendor', 'parts.part_number', 'parts.description as part_description',
                'part_serials.serial_number', 'stores.name as from_store');

        return $q->cursor()->map(function ($r) {
            $overdue = $r->status === 'on_loan' && $r->due_date !== null
                && \Illuminate\Support\Carbon::parse($r->due_date)->startOfDay()->lt(today());

            return [
                'Direction' => $r->direction === 'out' ? 'Out (lent by NCAT)' : 'In (borrowed by NCAT)',
                'Counterparty' => $r->vendor ?? $r->party_name,
                'Item' => $r->part_number
                    ? trim($r->part_number.' - '.($r->part_description ?? ''), ' -')
                    : $r->item_description,
                'Serial' => $r->serial_number ?? $r->serial_text,
                'Qty' => (float) $r->quantity,
                'From Store' => $r->from_store,
                'Started' => $r->started_at ? substr($r->started_at, 0, 10) : null,
                'Due' => $r->due_date ? substr($r->due_date, 0, 10) : null,
                'Days Overdue' => $overdue
                    ? (int) round(\Illuminate\Support\Carbon::parse($r->due_date)->startOfDay()->diffInDays(today()))
                    : 0,
                'Status' => $overdue ? 'overdue' : $r->status,
            ];
        });
    }

    /**
     * Consignments still on their way, with how long they have been silent
     * (spec §12.6). A shipment that has not moved in weeks is the thing this
     * report exists to surface, so days-since-last-event sits next to overdue.
     */
    protected function shipmentsInTransit(array $f): LazyCollection
    {
        $q = DB::table('shipments')
            ->leftJoin('vendors', 'vendors.id', '=', 'shipments.vendor_id')
            ->leftJoin('purchase_orders', function ($j) {
                $j->on('purchase_orders.id', '=', 'shipments.source_id')
                    ->where('shipments.source_type', '=', \App\Models\PurchaseOrder::class);
            })
            ->leftJoin('repair_orders', function ($j) {
                $j->on('repair_orders.id', '=', 'shipments.source_id')
                    ->where('shipments.source_type', '=', \App\Models\RepairOrder::class);
            })
            ->whereNull('shipments.closed_at')
            ->when(($f['scope'] ?? 'in_transit') === 'in_transit', fn ($q) => $q->whereNull('shipments.arrived_at'))
            ->when(($f['scope'] ?? null) === 'overdue', fn ($q) => $q->whereNull('shipments.arrived_at')
                ->whereNotNull('shipments.expected_arrival_date')
                ->whereDate('shipments.expected_arrival_date', '<', today()))
            ->when($f['vendor'] ?? null, fn ($q, $v) => $q->where('shipments.vendor_id', $v))
            ->orderBy('shipments.expected_arrival_date')->orderBy('shipments.id')
            ->select('shipments.reference', 'shipments.carrier', 'shipments.awb_reference',
                'shipments.current_status', 'shipments.current_status_date',
                'shipments.expected_arrival_date', 'shipments.arrived_at',
                'vendors.name as vendor', 'purchase_orders.po_number', 'repair_orders.ro_number');

        return $q->cursor()->map(function ($r) {
            $overdue = $r->arrived_at === null && $r->expected_arrival_date !== null
                && \Illuminate\Support\Carbon::parse($r->expected_arrival_date)->startOfDay()->lt(today());

            return [
                'Reference' => $r->reference,
                'Vendor' => $r->vendor,
                'Source Order' => $r->po_number ?? $r->ro_number,
                'Carrier' => $r->carrier,
                'AWB' => $r->awb_reference,
                'Latest Status' => $r->current_status,
                'Last Event' => $r->current_status_date ? substr($r->current_status_date, 0, 10) : null,
                'Days Since Last Event' => $r->current_status_date
                    ? (int) round(\Illuminate\Support\Carbon::parse($r->current_status_date)->startOfDay()->diffInDays(today()))
                    : null,
                'Expected' => $r->expected_arrival_date ? substr($r->expected_arrival_date, 0, 10) : null,
                'Days Overdue' => $overdue
                    ? (int) round(\Illuminate\Support\Carbon::parse($r->expected_arrival_date)->startOfDay()->diffInDays(today()))
                    : 0,
                'Overdue' => $overdue ? 'yes' : 'no',
            ];
        });
    }

    protected function quarantine(array $f): LazyCollection
    {
        $quarantine = Store::where('type', 'quarantine')->first();
        if (! $quarantine) {
            return LazyCollection::make([]);
        }

        $q = DB::table('stock_balances')
            ->join('parts', 'parts.id', '=', 'stock_balances.part_id')
            ->where('stock_balances.store_id', $quarantine->id)
            ->where('stock_balances.quantity', '>', 0)
            ->whereNull('parts.deleted_at')
            ->orderBy('parts.part_number')
            ->select('parts.id as part_id', 'parts.part_number', 'parts.description', 'stock_balances.quantity');

        return $q->cursor()->map(function ($r) use ($quarantine) {
            $oldest = DB::table('stock_movements')
                ->where('part_id', $r->part_id)->where('store_id', $quarantine->id)
                ->where('direction', 'in')->min('posted_at');
            $days = $oldest ? (int) round(\Illuminate\Support\Carbon::parse($oldest)->startOfDay()->diffInDays(now()->startOfDay())) : 0;

            return [
                'Part No.' => $r->part_number,
                'Description' => $r->description,
                'Qty' => (float) $r->quantity,
                'Oldest Received' => $oldest ? substr($oldest, 0, 10) : null,
                'Days in Quarantine' => $days,
            ];
        });
    }
}
