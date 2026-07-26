<?php

namespace App\Services\Demo;

use App\Models\Aircraft;
use App\Models\AtaChapter;
use App\Models\DocumentCounter;
use App\Models\Part;
use App\Models\PartBatch;
use App\Models\PartSerial;
use App\Models\PurchaseOrder;
use App\Models\RepairOrder;
use App\Models\Requisition;
use App\Services\Documents\ApprovalService;
use App\Models\Siv;
use App\Models\SivItem;
use App\Models\Srv;
use App\Models\SrvItem;
use App\Models\Store;
use App\Models\User;
use App\Models\Vendor;
use App\Services\Documents\DocumentNumberService;
use App\Services\Documents\PurchaseOrderService;
use App\Services\Documents\RepairOrderService;
use App\Services\Documents\SivService;
use App\Services\Documents\SrvService;
use App\Services\Stock\SerialStateService;
use App\Services\Stock\StockNotifier;
use App\Services\Stock\StockService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;

/**
 * Builds the management-demo narrative (Builder Prompt #7): ~40 realistic GA
 * parts and a 10-week backdated story that exercises every stock mechanism,
 * lights every dashboard alert, and produces a requisition in every status.
 *
 * Backdating is done by sweeping Carbon::setTestNow() forward through the
 * weeks, so movements/documents record real historical `posted_at` values via
 * the normal service paths (the ledger's balance_after math stays correct
 * because events are seeded in chronological order).
 */
class DemoSeeder
{
    /** Transactional tables that must be empty (unless --force) before seeding. */
    public const TRANSACTIONAL_TABLES = [
        'stock_movements', 'stock_balances', 'part_serials', 'part_batches', 'parts',
        'work_orders', 'requisitions', 'srvs', 'srv_items', 'sivs', 'siv_items',
        'purchase_orders', 'purchase_order_lines', 'repair_orders', 'repair_order_lines',
    ];

    public const DEMO_DOMAIN = '@demo.ncatfmd.local';

    public const DEMO_PASSWORD = 'DemoNCAT2026!';

    /** @var array<string, Part> */
    protected array $parts = [];

    protected User $storeman;

    public function __construct(
        protected StockService $stock,
        protected SerialStateService $serials,
        protected SivService $sivService,
        protected SrvService $srvService,
        protected DocumentNumberService $counters,
        protected StockNotifier $notifier,
        protected DemoMode $demo,
        protected PurchaseOrderService $purchaseOrders,
        protected RepairOrderService $repairOrders,
    ) {
    }

    public function alreadyPopulated(): bool
    {
        if ($this->demo->isActive()) {
            return true;
        }
        foreach (self::TRANSACTIONAL_TABLES as $table) {
            if (DB::table($table)->exists()) {
                return true;
            }
        }

        return false;
    }

    public function run(): void
    {
        $snapshot = $this->snapshotCounters();

        try {
            $this->demoUsers();
            $this->storeman = User::where('email', 'storeman'.self::DEMO_DOMAIN)->firstOrFail();

            $this->catalogue();
            $this->openingStock();      // ~10 weeks ago
            $this->receivingAndCertification();
            $this->transfersAndAdjustments();
            $this->fuelOperations();
            $this->workOrdersAndRequisitions();
            $this->vendorsAndOrders();
            $this->issuing();
            $this->shelfLifeAndAlerts();
            $this->refreshNotifications();
        } finally {
            Carbon::setTestNow(); // always restore real time
        }

        $this->demo->activate($snapshot);
    }

    /** @return array<string, int> series => next_number, captured pre-seed */
    protected function snapshotCounters(): array
    {
        return DocumentCounter::query()->pluck('next_number', 'series')->map(fn ($n) => (int) $n)->all();
    }

    /** Run a closure "as of" a backdated moment so all now() calls record history. */
    protected function at(int $daysAgo, int $hour, callable $fn): void
    {
        Carbon::setTestNow(Carbon::now()->subDays($daysAgo)->setTime($hour, 0));
        $fn();
        Carbon::setTestNow();
    }

    protected function ata(string $n): ?int
    {
        return AtaChapter::where('chapter_number', $n)->value('id');
    }

    protected function aircraft(string $reg): ?Aircraft
    {
        return Aircraft::where('registration', $reg)->first();
    }

    protected function store(string $type): Store
    {
        return Store::where('type', $type)->firstOrFail();
    }

    // ---- Demo users -----------------------------------------------------

    protected function demoUsers(): void
    {
        $roles = [
            ['Stores Officer', 'Femi Adewale', 'officer'],
            ['Storekeeper', 'Grace Okoro', 'storeman'],
            ['Engineer/Technician', 'Musa Ibrahim', 'engineer'],
            ['Viewer', 'Ngozi Eze', 'viewer'],
        ];

        foreach ($roles as [$role, $name, $handle]) {
            $user = User::updateOrCreate(
                ['email' => $handle.self::DEMO_DOMAIN],
                [
                    'name' => $name.' (Demo)',
                    'password' => bcrypt(self::DEMO_PASSWORD),
                    'password_change_required' => false,
                    'is_active' => true,
                    'is_demo' => true,
                ],
            );
            if (Role::where('name', $role)->exists() && ! $user->hasRole($role)) {
                $user->assignRole($role);
            }
        }
    }

    // ---- Catalogue (~40 parts) ------------------------------------------

    protected function catalogue(): void
    {
        $defs = [
            // number, desc, ata, unit, price, min, reorder, max, flags
            ['505C61-8', 'Main Wheel Tyre, DA-40', '32', 'EA', 92000, 4, 8, 24, []],
            ['5.00-5', 'Nose Wheel Tyre, TB-9', '32', 'EA', 48000, 4, 8, 20, []],
            ['066-01100', 'Main Wheel Tyre, TB-20', '32', 'EA', 105000, 2, 4, 16, []],
            ['30-66', 'Brake Disc', '32', 'EA', 78000, 2, 4, 12, []],
            ['066-10500', 'Brake Pad Set', '32', 'SET', 26000, 4, 8, 30, []],
            ['MS29513-014', 'O-Ring, Packing', '20', 'EA', 350, 20, 40, 200, []],
            ['AN960-416', 'Flat Washer', '20', 'EA', 60, 100, 200, 1000, []],
            ['CM-2612', 'Concorde Battery RG-24', '24', 'EA', 210000, 1, 2, 6, []],
            ['G-243', 'Gill Battery G-243', '24', 'EA', 185000, 1, 2, 6, []],
            ['REM37BY', 'Spark Plug', '74', 'EA', 9500, 12, 24, 96, []],
            ['CH48110-1', 'Oil Filter', '79', 'EA', 14500, 6, 12, 48, []],
            ['AA48103-2', 'Oil Filter, Lycoming', '79', 'EA', 15200, 6, 12, 48, []],
            ['P104-1', 'Fuel Filter Element', '73', 'EA', 8800, 6, 12, 40, []],
            ['GTN-650', 'GPS/NAV/COMM Unit', '34', 'EA', 4500000, 0, 0, null, ['serialized']],
            ['GDU-1040', 'G1000 Display Unit', '34', 'EA', 6200000, 0, 0, null, ['serialized']],
            ['GTX-335', 'Transponder', '34', 'EA', 1850000, 0, 0, null, ['serialized']],
            ['W100-1QT', 'Aeroshell W100 Oil', '79', 'QT', 4200, 24, 48, 240, ['shelf']],
            ['15W50-1QT', 'Aeroshell 15W-50 Oil', '79', 'QT', 4800, 24, 48, 240, ['shelf']],
            ['PR-1440-B2', 'Sealant, Fuel Tank', '28', 'KIT', 18000, 2, 4, 20, ['shelf', 'flammable']],
            ['MEK-1L', 'Methyl Ethyl Ketone', '28', 'L', 5200, 4, 8, 40, ['flammable']],
            ['DOPE-1L', 'Nitrate Dope', '51', 'L', 6100, 4, 8, 40, ['flammable']],
            ['JET-A1', 'Jet A-1 Fuel', '28', 'L', 1250, 3000, 6000, null, ['fuel']],
            ['AVGAS-100LL', 'Aviation Gasoline 100LL', '28', 'L', 1200, 2000, 5000, null, ['fuel']],
        ];

        // Pad out to ~40 with realistic consumables so the catalogue looks full.
        for ($i = 1; $i <= 18; $i++) {
            $defs[] = ['HW-'.str_pad((string) $i, 4, '0', STR_PAD_LEFT), 'Airframe Hardware Item '.$i, '20', 'EA',
                rand(80, 4200), 10, 20, 200, []];
        }

        foreach ($defs as [$num, $desc, $ata, $unit, $price, $min, $reorder, $max, $flags]) {
            $this->parts[$num] = Part::updateOrCreate(['part_number' => $num], [
                'description' => $desc,
                'ata_chapter_id' => $this->ata($ata),
                'unit_of_issue' => $unit,
                'unit_price' => $price,
                'min_level' => $min,
                'reorder_level' => $reorder,
                'max_level' => $max,
                'is_serialized' => in_array('serialized', $flags, true),
                'has_shelf_life' => in_array('shelf', $flags, true),
                'is_flammable' => in_array('flammable', $flags, true),
                'is_fuel' => in_array('fuel', $flags, true),
            ]);
        }
    }

    protected function p(string $num): Part
    {
        return $this->parts[$num];
    }

    // ---- Opening stock (~10 weeks ago) ----------------------------------

    protected function openingStock(): void
    {
        $bonded = $this->store('bonded');
        $this->at(70, 8, function () use ($bonded) {
            $opening = [
                '505C61-8' => 14, '5.00-5' => 10, '066-01100' => 8, '30-66' => 6, '066-10500' => 18,
                'MS29513-014' => 150, 'AN960-416' => 600, 'CM-2612' => 3, 'G-243' => 4,
                'REM37BY' => 60, 'CH48110-1' => 24, 'AA48103-2' => 20, 'P104-1' => 20,
                'W100-1QT' => 180, '15W50-1QT' => 120,
            ];
            foreach ($opening as $num => $qty) {
                $this->stock->openingBalance(part: $this->p($num), store: $bonded, quantity: $qty, user: $this->storeman);
            }
            // Serialized avionics on the shelf.
            foreach (['GTN-650' => 'SN-GTN-4471', 'GDU-1040' => 'SN-GDU-2210', 'GTX-335' => 'SN-GTX-8890'] as $num => $sn) {
                $serial = PartSerial::firstOrCreate(['part_id' => $this->p($num)->id, 'serial_number' => $sn], ['status' => 'in_store']);
                $this->stock->openingBalance(part: $this->p($num), store: $bonded, quantity: 1, user: $this->storeman, serialId: $serial->id);
            }
            // Above-max: overstock hardware.
            $this->stock->openingBalance(part: $this->p('HW-0001'), store: $bonded, quantity: 320, user: $this->storeman);
        });
    }

    // ---- Receiving + certification --------------------------------------

    protected function receivingAndCertification(): void
    {
        $quarantine = $this->store('quarantine');

        // SRV posted 8 weeks ago → certified to Bonded.
        $this->at(56, 9, function () use ($quarantine) {
            $srv = $this->srv([
                ['num' => 'CH48110-1', 'qty' => 24, 'rate' => 14500, 'batch' => 'OF-2405', 'year' => 2024],
                ['num' => 'REM37BY', 'qty' => 48, 'rate' => 9500],
            ], $quarantine, supplier: 'Aero Supplies Ltd', lpo: 'LPO-2024-118');
            foreach ($srv->items as $item) {
                $this->stock->certify(part: $item->part, quantity: (float) $item->quantity, decision: 'release_to_bonded', user: $this->storeman, remarks: 'Certified serviceable');
            }
        });

        // Flammables → Dope store, 6 weeks ago.
        $this->at(42, 10, function () use ($quarantine) {
            $srv = $this->srv([
                ['num' => 'MEK-1L', 'qty' => 20, 'rate' => 5200],
                ['num' => 'DOPE-1L', 'qty' => 20, 'rate' => 6100],
            ], $quarantine, supplier: 'ChemAv Nigeria', lpo: 'LPO-2024-133');
            foreach ($srv->items as $item) {
                $this->stock->certify(part: $item->part, quantity: (float) $item->quantity, decision: 'release_to_dope', user: $this->storeman, remarks: 'Flammable, routed to Dope');
            }
        });

        // SRV still sitting in Quarantine 14 days → aging alert.
        $this->at(14, 11, function () use ($quarantine) {
            $this->srv([
                ['num' => 'PR-1440-B2', 'qty' => 6, 'rate' => 18000, 'batch' => 'SL-2506', 'year' => 2025, 'expiry' => Carbon::now()->addMonths(2)->toDateString()],
            ], $quarantine, supplier: 'SealTech', lpo: 'LPO-2025-041');
        });
    }

    /**
     * Create + post an SRV. Items: [{num, qty, rate, batch?, year?, expiry?}].
     */
    protected function srv(array $items, Store $destination, string $supplier, string $lpo): Srv
    {
        $srv = Srv::create([
            'srv_number' => $this->counters->reserve('srv'),
            'srv_date' => Carbon::now()->toDateString(),
            'destination_store_id' => $destination->id,
            'supplier' => $supplier,
            'lpo_or_petty_cash_ref' => $lpo,
            'head_of_receiving_dept' => 'Head of Stores',
            'storekeeper' => $this->storeman->name,
            'created_by_user_id' => $this->storeman->id,
        ]);

        foreach ($items as $i => $it) {
            SrvItem::create([
                'srv_id' => $srv->id, 'line_no' => $i + 1, 'part_id' => $this->p($it['num'])->id,
                'quantity' => $it['qty'], 'rate' => $it['rate'], 'amount' => $it['qty'] * $it['rate'],
                'invoice_no' => 'INV-'.rand(10000, 99999), 'acct_code' => 'STK-'.rand(100, 999),
                'batch_no' => $it['batch'] ?? null, 'batch_year' => $it['year'] ?? null,
                'expiry_date' => $it['expiry'] ?? null,
                'supplier_details' => $this->p($it['num'])->description,
            ]);
        }

        return $this->srvService->post($srv->fresh('items'), $this->storeman);
    }

    // ---- Transfers + adjustments ----------------------------------------

    protected function transfersAndAdjustments(): void
    {
        $bonded = $this->store('bonded');
        $dope = $this->store('dope');

        $this->at(35, 13, function () use ($bonded, $dope) {
            // Transfer a little sealant handling stock Bonded↔Dope is not valid
            // (flammable lives in Dope); instead adjust with reasons.
            $this->stock->adjust(part: $this->p('AN960-416'), store: $bonded, delta: -12, reason: 'Stocktake correction, miscount', user: $this->storeman);
            $this->stock->adjust(part: $this->p('MS29513-014'), store: $bonded, delta: 6, reason: 'Found stock returned unused', user: $this->storeman);
            // Move some MEK within Dope is n/a; keep dope stocked.
            unset($dope);
        });
    }

    // ---- Fuel -----------------------------------------------------------

    protected function fuelOperations(): void
    {
        $this->at(49, 7, function () {
            $this->stock->fuelReceive(part: $this->p('JET-A1'), quantity: 12000, user: $this->storeman, unitPrice: 1250, reference: 'BULK-JETA1-06');
            $this->stock->fuelReceive(part: $this->p('AVGAS-100LL'), quantity: 9000, user: $this->storeman, unitPrice: 1200, reference: 'BULK-AVGAS-06');
        });

        // Issues to aircraft over the weeks.
        $issues = [
            [40, 'JET-A1', '5N-BZA', 420], [33, 'AVGAS-100LL', '5N-CAK', 180],
            [21, 'JET-A1', '5N-BZE', 510], [12, 'AVGAS-100LL', '5N-BZH', 160], [4, 'JET-A1', '5N-BZA', 380],
        ];
        foreach ($issues as [$daysAgo, $num, $reg, $litres]) {
            $ac = $this->aircraft($reg);
            if (! $ac) {
                continue;
            }
            $this->at($daysAgo, 14, fn () => $this->stock->fuelIssue(part: $this->p($num), quantity: $litres, aircraft: $ac, user: $this->storeman, remarks: 'Training sortie'));
        }
    }

    // ---- Work orders + requisitions (every status) ----------------------

    protected function workOrdersAndRequisitions(): void
    {
        // A handful of work orders across the fleet.
        $wos = [
            // 5N-CAK is the demo "hero" aircraft — an OPEN snag drives the workspace story.
            [12, '5N-CAK', 'snag', 'SNAG: R/H main wheel tyre worn out', 'open'],
            [30, '5N-CZB', 'snag', 'SNAG: ECU A failed after start', 'in_progress'],
            [20, '5N-BZH', 'scheduled_inspection', '100 HRS INSPECTION', 'in_progress'],
            [15, '5N-BZE', 'scheduled_inspection', 'ANNUAL INSPECTION', 'open'],
            [45, '5N-CAJ', 'snag', 'SNAG: Landing light inoperative', 'closed'],
            [7, '5N-BZK', 'snag', 'SNAG: Brake binding on taxi', 'open'],
        ];
        $woModels = [];
        foreach ($wos as [$daysAgo, $reg, $type, $title, $status]) {
            $ac = $this->aircraft($reg);
            if (! $ac) {
                continue;
            }
            $this->at($daysAgo, 9, function () use (&$woModels, $ac, $type, $title, $status, $reg) {
                $woModels[$reg] = \App\Models\WorkOrder::create([
                    'wo_ref' => $this->counters->reserveWorkOrder($ac->aircraftType, Carbon::now()),
                    'aircraft_id' => $ac->id,
                    'work_type' => $type,
                    'inspection_type' => $type === 'scheduled_inspection' ? (str_contains($title, 'ANNUAL') ? 'ANNUAL' : '100 HRS') : null,
                    'title' => $title,
                    'description' => $title,
                    'status' => $status,
                    'raised_by' => 'Line Engineer',
                    'work_date' => Carbon::now()->toDateString(),
                    'closed_at' => $status === 'closed' ? Carbon::now()->addDays(3) : null,
                ]);
            });
        }

        // Requisitions in every status.
        $this->requisitionEveryStatus($woModels['5N-CAK'] ?? null);
    }

    protected function requisitionEveryStatus(?\App\Models\WorkOrder $wo): void
    {
        $cak = $this->aircraft('5N-CAK');

        // draft
        $this->at(28, 10, fn () => $this->req('draft', $cak, '505C61-8'));
        // submitted (x2 → pending-approval badge)
        $this->at(6, 10, fn () => $this->req('submitted', $cak, '5.00-5'));
        $this->at(5, 11, fn () => $this->req('submitted', $this->aircraft('5N-CBA'), '30-66'));
        // approved awaiting issue
        $this->at(9, 10, fn () => $this->req('approved', $cak, '066-10500', ['approved_at' => Carbon::now()]));
        // rejected with remarks
        $this->at(12, 15, fn () => $this->req('rejected', $this->aircraft('5N-CBD'), 'REM37BY', ['rejected_at' => Carbon::now(), 'approval_remarks' => 'Duplicate of WO already actioned']));
        // issued with completed removal (serial → at_repair)
        $this->at(24, 9, function () use ($cak) {
            $serial = PartSerial::firstOrCreate(
                ['part_id' => $this->p('GTX-335')->id, 'serial_number' => 'SN-GTX-OLD-01'],
                ['status' => 'installed', 'current_aircraft_id' => $cak?->id, 'position' => 'AVIONICS BAY'],
            );
            $this->req('issued', $cak, 'GTX-335', [
                'approved_at' => Carbon::now()->subDay(), 'issued_at' => Carbon::now(),
                'serial_no_removed' => $serial->serial_number, 'removal_zone' => 'AVIONICS BAY',
                'unit_changed_by' => 'Musa Ibrahim', 'reason_for_removal' => 'Transponder intermittent',
                'repair_facility' => 'Avionics Workshop', 'date_sent' => Carbon::now()->toDateString(),
                'repair_order_ref' => 'RO-2025-0087', 'removed_serial_id' => $serial->id,
                'removal_completed_at' => Carbon::now(),
            ]);
            // Guard so a --force re-seed (serial already at_repair) is idempotent.
            if ($serial->status === 'installed') {
                $this->serials->remove($serial, 'at_repair', reason: 'Transponder intermittent', user: $this->storeman);
            }
        });
        // closed
        $this->at(40, 9, fn () => $this->req('closed', $this->aircraft('5N-CAM'), 'CH48110-1', ['approved_at' => Carbon::now()->subDays(2), 'issued_at' => Carbon::now()->subDay()]));
    }

    // ---- Vendors and orders (Phase 7) -----------------------------------

    /**
     * Two vendors, one purchase order part-way through receiving, and one repair
     * order carrying the serial the removal narrative already sent away. The RO
     * stamps its real reference over the placeholder the requisition was seeded
     * with, so the demo shows the back-link the way the live flow produces it.
     *
     * Vendors are reference data rather than transactional, so they are flagged
     * `is_demo` and the purger removes them by that flag.
     */
    protected function vendorsAndOrders(): void
    {
        $supplier = Vendor::firstOrCreate(
            ['name' => 'DIAMOND AIRCRAFT INDUSTRIES GMBH'],
            [
                'type' => 'supplier',
                'address' => "DIAMOND AIRCRAFT INDUSTRIES GMBH,\nNIKOLAUS-AUGUST-OTTO-STRAUBE 5,\n2700WIENERNEUSTADT,\nAUSTRIA.",
                'country' => 'Austria', 'email' => 'parts@diamond-air.example',
                'contact_person' => 'Spares Desk', 'is_active' => true, 'is_demo' => true,
            ],
        );

        $repairer = Vendor::firstOrCreate(
            ['name' => 'BRINKLEY AEROSPACE SERVICE LIMITED'],
            [
                'type' => 'repair_organization',
                'address' => "Brinkley Aerospace Service Limited,\nUnit 1, Montgomery Way,\nBiggleswade,\nBedfordshire,\nSG 18 8UB,\nEngland.",
                'country' => 'England', 'email' => 'repairs@brinkley.example',
                'contact_person' => 'Repair Desk', 'is_active' => true, 'is_demo' => true,
            ],
        );

        // Purchase order raised three weeks ago, half of line 1 delivered.
        $this->at(21, 10, function () use ($supplier) {
            $order = PurchaseOrder::create([
                'order_date' => Carbon::now()->toDateString(),
                'vendor_id' => $supplier->id,
                'aircraft_type_label' => 'DIAMOND DA-40NG/DA-42NG',
                'priority' => 'very_urgent',
                'created_by_user_id' => $this->storeman->id,
            ]);

            $this->purchaseOrders->saveLines($order, [
                ['description' => 'ENGINE SHOCK MOUNTS (INCLUDING BOLTS, WASHERS AND LOCK NUTS)',
                    'part_number' => 'D44-7106-00-56', 'qty_to_order' => 4,
                    'line_status' => 'NEW', 'timeline_month' => 7, 'timeline_year' => 2026],
                ['description' => '3 POINT SAFETY HARNESS, FRONT PILOT LH/RH',
                    'part_number' => '5-01-1C0710', 'qty_to_order' => 2,
                    'line_status' => 'NEW', 'timeline_month' => 7, 'timeline_year' => 2026],
            ]);

            $this->purchaseOrders->issue($order, $this->storeman);

            $this->purchaseOrders->applyReceipt(
                $order->refresh(),
                [$order->lines->first()->id => 2],
                null,
                $this->storeman,
            );
        });

        // Repair order for the transponder the removal narrative booked out.
        $serial = PartSerial::where('serial_number', 'SN-GTX-OLD-01')->first();

        if (! $serial) {
            return;
        }

        $this->at(20, 11, function () use ($repairer, $serial) {
            $order = RepairOrder::create([
                'order_date' => Carbon::now()->toDateString(),
                'vendor_id' => $repairer->id,
                'aircraft_type_label' => 'DIAMOND DA40G',
                'priority' => 'very_urgent',
                'created_by_user_id' => $this->storeman->id,
            ]);

            $this->repairOrders->saveLines($order, [[
                'description' => 'TRANSPONDER, MODE S',
                'part_serial_id' => $serial->id,
                'qty' => 1,
                'action' => 'REPAIR',
            ]]);

            $this->repairOrders->issue($order, $this->storeman);
            $this->repairOrders->markAtVendor($order->refresh(), $this->storeman);
        });
    }

    protected function req(string $status, ?Aircraft $ac, string $partNum, array $extra = []): Requisition
    {
        $part = $this->p($partNum);

        $requisition = Requisition::create(array_merge([
            'requisition_no' => $this->counters->reserve('requisition'),
            'requisition_date' => Carbon::now()->toDateString(),
            'aircraft_id' => $ac?->id,
            'aircraft_reg' => $ac?->registration,
            'full_description' => $part->description,
            'part_id' => $part->id,
            'part_no' => $part->part_number,
            'stock_code' => $part->stock_code,
            'status' => $status,
            'requested_by_user_id' => $this->storeman->id,
            'submitted_at' => $status === 'draft' ? null : Carbon::now(),
            'authorised_by' => 'Chief Engineer',
        ], $extra));

        // Demo rows set their status directly, so give anything past draft the
        // approval chain a real submission would have written.
        if ($requisition->status !== 'draft') {
            app(ApprovalService::class)->backfillChain($requisition);
        }

        return $requisition;
    }

    // ---- Issuing (SIV) --------------------------------------------------

    protected function issuing(): void
    {
        $bonded = $this->store('bonded');

        // Standalone consumables issue (oil) — 18 days ago.
        $this->at(18, 12, function () use ($bonded) {
            $siv = $this->siv('Routine consumables', [
                ['num' => 'W100-1QT', 'qty' => 12], ['num' => 'MS29513-014', 'qty' => 20],
            ], $bonded);
            unset($siv);
        });

        // Issue against an approved requisition — 8 days ago (partial).
        $this->at(8, 13, function () use ($bonded) {
            $this->siv('Against approved requisition', [
                ['num' => '066-10500', 'qty' => 2, 'reqd' => 4],
            ], $bonded);
        });
    }

    protected function siv(string $for, array $items, Store $source): Siv
    {
        $siv = Siv::create([
            'siv_number' => $this->counters->reserve('siv'),
            'requisition_for' => $for,
            'ordered_by' => 'Line Engineer',
            'ordered_by_date' => Carbon::now()->toDateString(),
            'issued_by' => $this->storeman->name,
            'issued_by_date' => Carbon::now()->toDateString(),
            'created_by_user_id' => $this->storeman->id,
        ]);
        foreach ($items as $i => $it) {
            SivItem::create([
                'siv_id' => $siv->id, 'line_no' => $i + 1, 'part_id' => $this->p($it['num'])->id,
                'source_store_id' => $source->id, 'description' => $this->p($it['num'])->description,
                'qty_required' => $it['reqd'] ?? $it['qty'], 'qty_issued' => $it['qty'],
                'rate' => (float) $this->p($it['num'])->unit_price,
                'amount' => $it['qty'] * (float) $this->p($it['num'])->unit_price,
            ]);
        }

        return $this->sivService->post($siv->fresh('items'), $this->storeman);
    }

    // ---- Shelf-life + remaining alert coverage --------------------------

    protected function shelfLifeAndAlerts(): void
    {
        $bonded = $this->store('bonded');

        // Expired batch + expiring-soon batch on the oils.
        $this->at(60, 8, function () use ($bonded) {
            $expired = PartBatch::firstOrCreate(['part_id' => $this->p('15W50-1QT')->id, 'batch_number' => 'OIL-2312'], ['batch_year' => 2023, 'expiry_date' => Carbon::now()->addDays(20)->toDateString(), 'qty_received' => 24]);
            $this->stock->openingBalance(part: $this->p('15W50-1QT'), store: $bonded, quantity: 24, user: $this->storeman, batchId: $expired->id);
        });
        // Make it actually expired now by adding a clearly-expired batch (no stock needed for the alert).
        PartBatch::firstOrCreate(['part_id' => $this->p('W100-1QT')->id, 'batch_number' => 'OIL-2210'], ['batch_year' => 2022, 'expiry_date' => now()->subMonths(2)->toDateString(), 'qty_received' => 12]);
        // Expiring ≤90d.
        PartBatch::firstOrCreate(['part_id' => $this->p('CH48110-1')->id, 'batch_number' => 'OF-2405'], ['batch_year' => 2024, 'expiry_date' => now()->addDays(45)->toDateString(), 'qty_received' => 24]);

        // Drive specific parts below thresholds via backdated issues.
        $this->at(3, 10, function () use ($bonded) {
            // Tyre below reorder (start 14, issue down to 6 ≤ reorder 8).
            $this->stock->issue(part: $this->p('505C61-8'), store: $bonded, quantity: 8, user: $this->storeman, reference: 'DEMO');
            // Battery below min (start 3, issue 2 → 1 ≤ min? min 1 → set to 1 = at min).
            $this->stock->issue(part: $this->p('CM-2612'), store: $bonded, quantity: 2, user: $this->storeman, reference: 'DEMO');
        });
    }

    protected function refreshNotifications(): void
    {
        foreach ($this->parts as $part) {
            $this->notifier->checkReorder($part->fresh());
        }
    }
}
