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
use App\Models\Shipment;
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
use App\Services\Shipping\ShipmentService;
use App\Services\Stock\LoanService;
use App\Services\Stock\SerialStateService;
use App\Services\Stock\StockNotifier;
use App\Services\Stock\StockService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\UploadedFile;
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
        'shipments', 'shipment_events', 'loans',
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
        protected ShipmentService $shipments,
        protected LoanService $loans,
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
            $this->shippingAndLoans();
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
     * A vendor book, eight purchase orders covering every status, and five
     * repair orders covering every status.
     *
     * Volume is the point. A list with one row does not show anyone a working
     * module and leaves the status filters with nothing to filter, so each
     * lifecycle is walked through the real service methods rather than having
     * its end state written straight into the table. That way the demo carries
     * the same activity log, counters and ledger entries the live flow would.
     *
     * Vendors are reference data rather than transactional, so they are flagged
     * `is_demo` and the purger removes them by that flag.
     */
    protected function vendorsAndOrders(): void
    {
        $this->vendorBook();
        $this->purchaseOrderNarratives();
        $this->repairOrderNarratives();
    }

    /**
     * The vendor book. The first two appear on the department's own paper
     * forms, so they are reproduced exactly; the rest are plausible trading
     * partners for a Nigerian flying college. Every one carries a full address
     * block because the order PDFs print it as the addressee.
     */
    protected function vendorBook(): void
    {
        $vendors = [
            ['DIAMOND AIRCRAFT INDUSTRIES GMBH', 'supplier', 'Austria',
                "DIAMOND AIRCRAFT INDUSTRIES GMBH,\nNIKOLAUS-AUGUST-OTTO-STRAUBE 5,\n2700WIENERNEUSTADT,\nAUSTRIA.",
                'parts@diamond-air.example', 'Spares Desk'],
            ['BRINKLEY AEROSPACE SERVICE LIMITED', 'repair_organization', 'England',
                "Brinkley Aerospace Service Limited,\nUnit 1, Montgomery Way,\nBiggleswade,\nBedfordshire,\nSG 18 8UB,\nEngland.",
                'repairs@brinkley.example', 'Repair Desk'],
            ['SOCATA / DAHER AIRCRAFT SPARES', 'supplier', 'France',
                "Daher Aircraft Spares,\n65921 Tarbes Cedex 9,\nAeroport Tarbes-Lourdes-Pyrenees,\nFrance.",
                'spares@daher-air.example', 'Customer Support'],
            ['AVIALL SERVICES INCORPORATED', 'supplier', 'United States',
                "Aviall Services Inc.,\n2750 Regent Boulevard,\nDFW Airport,\nTexas 75261,\nUnited States of America.",
                'orders@aviall-services.example', 'Account Manager'],
            ['SKYWAY AVIATION SUPPLIES LIMITED', 'supplier', 'Nigeria',
                "Skyway Aviation Supplies Limited,\nPlot 214 Cadastral Zone C16,\nAirport Road,\nAbuja,\nNigeria.",
                'sales@skywaysupplies.example', 'Mrs. Adaeze Nwosu'],
            ['LAGOS AVIONICS WORKSHOP LIMITED', 'repair_organization', 'Nigeria',
                "Lagos Avionics Workshop Limited,\nHangar 4, General Aviation Terminal,\nMurtala Muhammed Airport,\nIkeja, Lagos,\nNigeria.",
                'workshop@lagosavionics.example', 'Engr. Tunde Bakare'],
            ['SAFRAN LANDING SYSTEMS', 'both', 'France',
                "Safran Landing Systems,\n7 Rue General Valerie Andre,\n78140 Velizy-Villacoublay,\nFrance.",
                'support@safran-landing.example', 'Aftermarket Desk'],
            ['HONEYWELL AEROSPACE TRADING', 'both', 'United States',
                "Honeywell Aerospace Trading,\n1944 East Sky Harbor Circle,\nPhoenix,\nArizona 85034,\nUnited States of America.",
                'aerotrading@honeywell-ae.example', 'Regional Sales'],
            ['TOTALENERGIES MARKETING NIGERIA PLC', 'supplier', 'Nigeria',
                "TotalEnergies Marketing Nigeria Plc,\n4 Afribank Street,\nVictoria Island,\nLagos,\nNigeria.",
                'aviation@totalenergies-ng.example', 'Aviation Fuels'],
            ['KADUNA INDUSTRIAL GASES LIMITED', 'supplier', 'Nigeria',
                "Kaduna Industrial Gases Limited,\nKm 8 Kachia Road,\nKakuri Industrial Estate,\nKaduna,\nNigeria.",
                'orders@kadunagases.example', 'Mallam Sani Yusuf'],
            ['AERO NORMANDIE SARL', 'repair_organization', 'France',
                "Aero Normandie SARL,\nAerodrome de Deauville-Normandie,\n14130 Saint-Gatien-des-Bois,\nFrance.",
                'atelier@aeronormandie.example', 'Atelier Moteurs'],
            ['ZARIA TECHNICAL SUPPLIES LIMITED', 'both', 'Nigeria',
                "Zaria Technical Supplies Limited,\n17 Sokoto Road,\nSabon Gari,\nZaria, Kaduna State,\nNigeria.",
                'info@zariatechnical.example', 'Alhaji Musa Danjuma'],
        ];

        foreach ($vendors as [$name, $type, $country, $address, $email, $contact]) {
            Vendor::firstOrCreate(['name' => $name], [
                'type' => $type,
                'address' => $address,
                'country' => $country,
                'email' => $email,
                'contact_person' => $contact,
                'is_active' => true,
                'is_demo' => true,
            ]);
        }
    }

    protected function vendor(string $name): Vendor
    {
        return Vendor::where('name', $name)->firstOrFail();
    }

    /** Open a draft purchase order with its lines already saved. */
    protected function draftPurchaseOrder(Vendor $vendor, string $typeLabel, string $priority, array $lines): PurchaseOrder
    {
        $order = PurchaseOrder::create([
            'order_date' => Carbon::now()->toDateString(),
            'vendor_id' => $vendor->id,
            'aircraft_type_label' => $typeLabel,
            'priority' => $priority,
            'created_by_user_id' => $this->storeman->id,
        ]);

        return $this->purchaseOrders->saveLines($order, array_map(
            fn ($l, $i) => $l + ['line_status' => 'NEW', 'timeline_month' => 7, 'timeline_year' => 2026],
            $lines,
            array_keys($lines),
        ));
    }

    /**
     * Eight purchase orders, one per status the list can filter by, so the
     * Orders screen reads as a book of work rather than a single row.
     */
    protected function purchaseOrderNarratives(): void
    {
        $diamond = $this->vendor('DIAMOND AIRCRAFT INDUSTRIES GMBH');
        $aviall = $this->vendor('AVIALL SERVICES INCORPORATED');
        $skyway = $this->vendor('SKYWAY AVIATION SUPPLIES LIMITED');
        $honeywell = $this->vendor('HONEYWELL AEROSPACE TRADING');
        $daher = $this->vendor('SOCATA / DAHER AIRCRAFT SPARES');
        $zaria = $this->vendor('ZARIA TECHNICAL SUPPLIES LIMITED');
        $total = $this->vendor('TOTALENERGIES MARKETING NIGERIA PLC');

        // 1. Partially received. The shipping narrative hangs off this one, and
        //    the receipt is booked against a real SRV so the linkage is visible.
        $this->at(21, 10, function () use ($diamond) {
            $order = $this->draftPurchaseOrder($diamond, 'DIAMOND DA-40NG/DA-42NG', 'very_urgent', [
                ['description' => 'ENGINE SHOCK MOUNTS (INCLUDING BOLTS, WASHERS AND LOCK NUTS)',
                    'part_number' => 'D44-7106-00-56', 'qty_to_order' => 4],
                ['description' => '3 POINT SAFETY HARNESS, FRONT PILOT LH/RH',
                    'part_number' => '5-01-1C0710', 'qty_to_order' => 2],
            ]);

            $this->purchaseOrders->issue($order, $this->storeman);
            $this->purchaseOrders->applyReceipt(
                $order->refresh(), [$order->lines->first()->id => 2], null, $this->storeman,
            );
        });

        // 2. Draft, still being typed up. Shows the editable state.
        $this->at(3, 9, function () use ($skyway) {
            $this->draftPurchaseOrder($skyway, 'CESSNA 172S', 'for_inventory', [
                ['description' => 'BRAKE PAD SET, CLEVELAND', 'part_number' => '066-10500', 'qty_to_order' => 12],
                ['description' => 'OIL FILTER, LYCOMING', 'part_number' => 'CH48110-1', 'qty_to_order' => 24],
            ]);
        });

        // 3. Issued and waiting on the vendor.
        $this->at(12, 11, function () use ($aviall) {
            $order = $this->draftPurchaseOrder($aviall, 'DIAMOND DA-42NG', 'aog', [
                ['description' => 'MAIN WHEEL TYRE, DA-40', 'part_number' => '505C61-8', 'qty_to_order' => 8],
                ['description' => 'NOSE WHEEL TYRE, TB-9', 'part_number' => '5.00-5', 'qty_to_order' => 6],
            ]);
            $this->purchaseOrders->issue($order, $this->storeman);
        });

        // 4. Received in full, not yet closed off.
        $this->at(34, 10, function () use ($zaria) {
            $order = $this->draftPurchaseOrder($zaria, 'FLEET GENERAL', 'for_inventory', [
                ['description' => 'FLAT WASHER, AN960-416', 'part_number' => 'AN960-416', 'qty_to_order' => 500],
                ['description' => 'O-RING, PACKING', 'part_number' => 'MS29513-014', 'qty_to_order' => 100],
            ]);
            $this->purchaseOrders->issue($order, $this->storeman);
            $order->refresh();
            $this->purchaseOrders->applyReceipt($order, [
                $order->lines[0]->id => 500,
                $order->lines[1]->id => 100,
            ], null, $this->storeman);
        });

        // 5. Received and closed. The finished state.
        $this->at(48, 9, function () use ($total) {
            $order = $this->draftPurchaseOrder($total, 'FLEET GENERAL', 'for_inventory', [
                ['description' => 'AVIATION GASOLINE 100LL, BULK DELIVERY (LITRES)',
                    'part_number' => 'AVGAS-100LL', 'qty_to_order' => 12000],
            ]);
            $this->purchaseOrders->issue($order, $this->storeman);
            $order->refresh();
            $this->purchaseOrders->applyReceipt($order, [$order->lines->first()->id => 12000], null, $this->storeman);
            $this->purchaseOrders->close($order->refresh(), $this->storeman);
        });

        // 6. Cancelled, with the reason on the record.
        $this->at(26, 15, function () use ($honeywell) {
            $order = $this->draftPurchaseOrder($honeywell, 'DIAMOND DA40G', 'aog', [
                ['description' => 'TRANSPONDER, MODE S, GTX-335', 'part_number' => 'GTX-335', 'qty_to_order' => 1],
            ]);
            $this->purchaseOrders->issue($order, $this->storeman);
            $this->purchaseOrders->cancel(
                $order->refresh(),
                'Unit sourced under warranty from the original installer instead.',
                $this->storeman,
            );
        });

        // 7. Second issued order, a different vendor and aircraft type, so the
        //    vendor filter has more than one issued order to separate.
        $this->at(8, 14, function () use ($daher) {
            $order = $this->draftPurchaseOrder($daher, 'SOCATA TB-9/TB-20', 'for_inventory', [
                ['description' => 'MAIN WHEEL TYRE, TB-20', 'part_number' => '066-01100', 'qty_to_order' => 4],
                ['description' => 'BRAKE DISC', 'part_number' => '30-66', 'qty_to_order' => 4],
            ]);
            $this->purchaseOrders->issue($order, $this->storeman);
        });

        // 8. Second partially received order, and the one that carries the
        //    receiving linkage: the part delivery is booked in on a real SRV,
        //    which names the order it was received against. Both lines here are
        //    catalogued parts, so the voucher moves real stock rather than
        //    standing in for it.
        $this->at(17, 13, function () use ($skyway) {
            $order = $this->draftPurchaseOrder($skyway, 'FLEET GENERAL', 'aog', [
                ['description' => 'SPARK PLUG, REM37BY', 'part_number' => 'REM37BY', 'qty_to_order' => 24],
                ['description' => 'FUEL FILTER ELEMENT', 'part_number' => 'P104-1', 'qty_to_order' => 10],
            ]);
            $this->purchaseOrders->issue($order, $this->storeman);
            $order->refresh();

            $srv = $this->srv([
                ['num' => 'REM37BY', 'qty' => 12, 'rate' => 8500],
            ], $this->store('quarantine'), supplier: $skyway->name, lpo: $order->po_number);
            $srv->update(['purchase_order_id' => $order->id]);

            $this->purchaseOrders->applyReceipt(
                $order, [$order->lines[0]->id => 12], $srv, $this->storeman,
            );
        });
    }

    /**
     * Five repair orders covering draft, issued, at-vendor, returned and
     * closed. The at-vendor one keeps the transponder serial the removal
     * narrative booked out, because that is the loop worth demonstrating: a
     * unit off an aircraft, away for repair, with a borrowed unit in its place.
     */
    protected function repairOrderNarratives(): void
    {
        $brinkley = $this->vendor('BRINKLEY AEROSPACE SERVICE LIMITED');
        $lagos = $this->vendor('LAGOS AVIONICS WORKSHOP LIMITED');
        $safran = $this->vendor('SAFRAN LANDING SYSTEMS');
        $normandie = $this->vendor('AERO NORMANDIE SARL');

        // 1. At vendor, carrying the serial from the removal narrative.
        $serial = PartSerial::where('serial_number', 'SN-GTX-OLD-01')->first();

        if ($serial) {
            $this->at(20, 11, function () use ($brinkley, $serial) {
                $order = $this->draftRepairOrder($brinkley, 'DIAMOND DA40G', 'very_urgent', [[
                    'description' => 'TRANSPONDER, MODE S',
                    'part_serial_id' => $serial->id,
                    'qty' => 1,
                    'action' => 'REPAIR',
                ]]);
                $this->repairOrders->issue($order, $this->storeman);
                $this->repairOrders->markAtVendor($order->refresh(), $this->storeman);
            });
        }

        // 2. Draft, being prepared.
        $this->at(4, 10, function () use ($lagos) {
            $this->draftRepairOrder($lagos, 'CESSNA 172S', 'for_inventory', [[
                'description' => 'ARTIFICIAL HORIZON, VACUUM DRIVEN',
                'serial_no' => 'AH-2291-C',
                'qty' => 1,
                'action' => 'OVERHAUL',
            ]]);
        });

        // 3. Issued, not yet acknowledged as received by the vendor.
        $this->at(9, 12, function () use ($safran) {
            $order = $this->draftRepairOrder($safran, 'DIAMOND DA-42NG', 'aog', [[
                'description' => 'NOSE LANDING GEAR SHOCK STRUT',
                'serial_no' => 'NLG-88-4410',
                'qty' => 1,
                'action' => 'OVERHAUL',
            ]]);
            $this->repairOrders->issue($order, $this->storeman);
        });

        // 4. Returned serviceable, so the unit is back in Quarantine awaiting
        //    certification exactly as an SRV receipt would leave it.
        $this->at(30, 9, function () use ($normandie) {
            $order = $this->draftRepairOrder($normandie, 'SOCATA TB-20', 'for_inventory', [[
                'description' => 'MAGNETO, LEFT HAND',
                'serial_no' => 'MAG-LH-7742',
                'qty' => 1,
                'action' => 'REPAIR',
            ]]);
            $this->repairOrders->issue($order, $this->storeman);
            $this->repairOrders->markAtVendor($order->refresh(), $this->storeman);

            Carbon::setTestNow(Carbon::now()->addDays(18));
            $order->refresh();
            $this->repairOrders->markReturned($order, [
                $order->lines->first()->id => ['disposition' => 'serviceable', 'note' => 'Bench tested, new points fitted.'],
            ], $this->storeman);
        });

        // 5. Returned and closed off.
        $this->at(56, 11, function () use ($lagos) {
            $order = $this->draftRepairOrder($lagos, 'CESSNA 172S', 'for_inventory', [[
                'description' => 'TURN COORDINATOR',
                'serial_no' => 'TC-1180-B',
                'qty' => 1,
                'action' => 'REPAIR',
            ]]);
            $this->repairOrders->issue($order, $this->storeman);
            $this->repairOrders->markAtVendor($order->refresh(), $this->storeman);

            Carbon::setTestNow(Carbon::now()->addDays(21));
            $order->refresh();
            $this->repairOrders->markReturned($order, [
                $order->lines->first()->id => ['disposition' => 'serviceable', 'note' => 'Repaired and recertified.'],
            ], $this->storeman);
            $this->repairOrders->close($order->refresh(), $this->storeman);
        });
    }

    /** Open a draft repair order with its lines already saved. */
    protected function draftRepairOrder(Vendor $vendor, string $typeLabel, string $priority, array $lines): RepairOrder
    {
        $order = RepairOrder::create([
            'order_date' => Carbon::now()->toDateString(),
            'vendor_id' => $vendor->id,
            'aircraft_type_label' => $typeLabel,
            'priority' => $priority,
            'created_by_user_id' => $this->storeman->id,
        ]);

        return $this->repairOrders->saveLines($order, $lines);
    }

    // ---- Shipping + loans (Phase 8) --------------------------------------

    /**
     * Six consignments and six loans, chosen so every state the list filters on
     * has something in it.
     *
     * The set is deliberately not uniform: one consignment is late so the ghost
     * node draws, two arrived and produced receipt vouchers so the handoff into
     * Quarantine is visible, one is closed so the frozen timeline can be shown,
     * and one carries paperwork on its timeline so the attachment feature is
     * demonstrable rather than merely present.
     */
    protected function shippingAndLoans(): void
    {
        $this->shipmentNarratives();
        $this->loanNarratives();
    }

    protected function shipmentNarratives(): void
    {
        $diamond = $this->vendor('DIAMOND AIRCRAFT INDUSTRIES GMBH');
        $aviall = $this->vendor('AVIALL SERVICES INCORPORATED');
        $skyway = $this->vendor('SKYWAY AVIATION SUPPLIES LIMITED');
        $daher = $this->vendor('SOCATA / DAHER AIRCRAFT SPARES');
        $order = PurchaseOrder::where('vendor_id', $diamond->id)->oldest('id')->first();

        // 1. Still in transit and past the promised date, so the dashboard
        //    alert fires and the timeline draws its overdue marker.
        if ($order) {
            $this->at(24, 9, function () use ($diamond, $order) {
                $shipment = $this->shipments->create([
                    'vendor_id' => $diamond->id,
                    'source_kind' => 'purchase_order',
                    'source_id' => $order->id,
                    'description' => 'Engine shock mounts and front harnesses against the July order.',
                    'carrier' => 'DHL Aviation',
                    'awb_reference' => '172-88104235',
                    'expected_arrival_date' => Carbon::now()->addDays(21)->toDateString(),
                    'status' => 'Shipped',
                    'event_date' => Carbon::now()->toDateString(),
                    'note' => 'Collected from the Wiener Neustadt facility.',
                ], $this->storeman);

                $this->shipmentEvent($shipment, 6, 'Arrived at local port', 'Landed at Lagos. Waiting on the clearing agent.');
                $this->shipmentEvent($shipment, 12, 'Cleared customs', 'Duty paid, release note issued.');
                $this->shipmentEvent($shipment, 17, 'In transit to NCAT', 'Handed to the local courier for Zaria.');
            });
        }

        // 2. Landed a month ago and receipted, so the SRV link has something in
        //    it. This is the one carrying paperwork on its timeline.
        $this->at(38, 8, function () use ($diamond) {
            $shipment = $this->shipments->create([
                'vendor_id' => $diamond->id,
                'description' => 'Consumables restock, shipped ahead of the main order.',
                'carrier' => 'Emirates SkyCargo',
                'awb_reference' => '176-55210987',
                'expected_arrival_date' => Carbon::now()->addDays(9)->toDateString(),
                'status' => 'Shipped',
                'event_date' => Carbon::now()->toDateString(),
                'note' => 'Two cartons, 46 kg.',
            ], $this->storeman);

            $this->shipmentEventWithPaperwork(
                $shipment, 5, 'Cleared customs',
                'Duty paid. Release note and airway bill filed against this entry.',
                ['customs-release-176-55210987', 'airway-bill-176-55210987'],
            );
            $this->shipmentEvent($shipment, 8, 'Arrived at NCAT', 'Received at the stores gate.', isArrival: true);

            Carbon::setTestNow(Carbon::now()->addDays(8));
            $srv = $this->srv([
                ['num' => 'AN960-416', 'qty' => 100, 'rate' => 350],
            ], $this->store('quarantine'), supplier: $diamond->name, lpo: 'AWB 176-55210987');
            $srv->update(['shipment_id' => $shipment->id]);
        });

        // 3. A second arrival with its own receipt voucher, so the arrived
        //    state is not a single example.
        $this->at(52, 10, function () use ($skyway) {
            $shipment = $this->shipments->create([
                'vendor_id' => $skyway->id,
                'description' => 'Tyres and brake pads for the Cessna line.',
                'carrier' => 'Road freight, Abuja to Zaria',
                'awb_reference' => 'SKY-DN-40112',
                'expected_arrival_date' => Carbon::now()->addDays(4)->toDateString(),
                'status' => 'In transit to NCAT',
                'event_date' => Carbon::now()->toDateString(),
                'note' => 'Collected from the Abuja warehouse.',
            ], $this->storeman);

            $this->shipmentEvent($shipment, 3, 'Arrived at NCAT', 'Delivered to the stores gate by the vendor.', isArrival: true);

            Carbon::setTestNow(Carbon::now()->addDays(3));
            $srv = $this->srv([
                ['num' => '066-10500', 'qty' => 8, 'rate' => 26000],
            ], $this->store('quarantine'), supplier: $skyway->name, lpo: 'SKY-DN-40112');
            $srv->update(['shipment_id' => $shipment->id]);
        });

        // 4. In transit and comfortably inside its promised date, so the list
        //    shows a healthy consignment next to the late one.
        $this->at(6, 13, function () use ($aviall) {
            $shipment = $this->shipments->create([
                'vendor_id' => $aviall->id,
                'description' => 'Wheel and tyre assemblies against the Aviall order.',
                'carrier' => 'Lufthansa Cargo',
                'awb_reference' => '020-71145528',
                'expected_arrival_date' => Carbon::now()->addDays(18)->toDateString(),
                'status' => 'Shipped',
                'event_date' => Carbon::now()->toDateString(),
                'note' => 'Departed DFW.',
            ], $this->storeman);

            $this->shipmentEvent($shipment, 4, 'Arrived at local port', 'On the ground at Lagos, awaiting the clearing agent.');
        });

        // 5. Sitting in customs, which is where consignments actually stall.
        $this->at(15, 11, function () use ($daher) {
            $shipment = $this->shipments->create([
                'vendor_id' => $daher->id,
                'description' => 'Tail wheel assemblies and control cables, TB-20.',
                'carrier' => 'Air France Cargo',
                'awb_reference' => '057-33920184',
                'expected_arrival_date' => Carbon::now()->addDays(20)->toDateString(),
                'status' => 'Shipped',
                'event_date' => Carbon::now()->toDateString(),
                'note' => 'Handed over at Tarbes.',
            ], $this->storeman);

            $this->shipmentEvent($shipment, 5, 'Arrived at local port', 'Landed at Lagos.');
            $this->shipmentEvent($shipment, 9, 'Cleared customs', 'Released after query on the commercial invoice.');
        });

        // 6. Finished and closed, so the frozen timeline and the re-open path
        //    both have a subject during the demonstration.
        $this->at(64, 9, function () use ($skyway) {
            $shipment = $this->shipments->create([
                'vendor_id' => $skyway->id,
                'description' => 'Hardware restock: washers, o-rings and split pins.',
                'carrier' => 'Road freight, Abuja to Zaria',
                'awb_reference' => 'SKY-DN-39770',
                'expected_arrival_date' => Carbon::now()->addDays(5)->toDateString(),
                'status' => 'Shipped',
                'event_date' => Carbon::now()->toDateString(),
                'note' => 'Two pallets.',
            ], $this->storeman);

            $this->shipmentEvent($shipment, 4, 'Arrived at NCAT', 'Received and checked against the packing list.', isArrival: true);

            Carbon::setTestNow(Carbon::now()->addDays(6));
            $this->shipments->close($shipment->refresh(), $this->storeman);
        });
    }

    /**
     * Six loans across both directions and every state the list filters on.
     */
    protected function loanNarratives(): void
    {
        // 1. Outbound and overdue. Lights the alert and gives the write-off
        //    action something to act on.
        $this->at(42, 14, function () {
            $this->loans->issueOutbound([
                'party_name' => 'Kaduna Flying Club',
                'party_contact' => 'Chief Engineer, 0803 000 0000',
                'part_id' => $this->p('REM37BY')->id,
                'quantity' => 6,
                'from_store_id' => $this->store('bonded')->id,
                'started_at' => Carbon::now()->toDateString(),
                'due_date' => Carbon::now()->addDays(28)->toDateString(),
                'notes' => 'Lent for their annual inspection. Replacement set on order.',
            ], $this->storeman);
        });

        // 2. Inbound, borrowed and fitted, so the parts-on-aircraft view shows a
        //    unit that is on the airframe and is not NCAT's.
        $this->at(16, 11, function () {
            $loan = $this->loans->receiveInbound([
                'party_name' => 'Zaria Aero Maintenance',
                'party_contact' => 'Stores, 0805 111 1111',
                'part_id' => $this->p('GTX-335')->id,
                'serial_text' => 'ZAM-LOAN-4471',
                'quantity' => 1,
                'started_at' => Carbon::now()->toDateString(),
                'due_date' => Carbon::now()->addDays(45)->toDateString(),
                'notes' => 'On loan while our own unit is at Brinkley for repair.',
            ], $this->storeman);

            $aircraft = $this->aircraft('5N-CAK');

            if ($aircraft) {
                $this->loans->installInbound($loan, $aircraft->id);
            }
        });

        // 3. Outbound, returned. The completed cycle.
        $this->at(50, 10, function () {
            $loan = $this->loans->issueOutbound([
                'party_name' => 'Nigerian College of Aviation Technology, Flying School',
                'party_contact' => 'Hangar Supervisor, 0806 222 2222',
                'part_id' => $this->p('CH48110-1')->id,
                'quantity' => 4,
                'from_store_id' => $this->store('bonded')->id,
                'started_at' => Carbon::now()->toDateString(),
                'due_date' => Carbon::now()->addDays(14)->toDateString(),
                'notes' => 'Lent to cover a scheduled oil change.',
            ], $this->storeman);

            Carbon::setTestNow(Carbon::now()->addDays(11));
            $this->loans->recordReturn($loan->refresh(), ['notes' => 'Returned complete and unused.'], $this->storeman);
        });

        // 4. Inbound, returned to its owner.
        $this->at(58, 15, function () {
            $loan = $this->loans->receiveInbound([
                'party_name' => 'Kaduna Flying Club',
                'party_contact' => 'Chief Engineer, 0803 000 0000',
                'part_id' => $this->p('G-243')->id,
                'serial_text' => 'KFC-BATT-9921',
                'quantity' => 1,
                'started_at' => Carbon::now()->toDateString(),
                'due_date' => Carbon::now()->addDays(21)->toDateString(),
                'notes' => 'Borrowed to keep 5N-CAJ serviceable over the check.',
            ], $this->storeman);

            Carbon::setTestNow(Carbon::now()->addDays(19));
            $this->loans->recordReturn($loan->refresh(), ['notes' => 'Returned to owner, tested serviceable.'], $this->storeman);
        });

        // 5. Outbound and still comfortably within its due date.
        $this->at(9, 12, function () {
            $this->loans->issueOutbound([
                'party_name' => 'Zaria Aero Maintenance',
                'party_contact' => 'Stores, 0805 111 1111',
                'part_id' => $this->p('MS29513-014')->id,
                'quantity' => 20,
                'from_store_id' => $this->store('bonded')->id,
                'started_at' => Carbon::now()->toDateString(),
                'due_date' => Carbon::now()->addDays(30)->toDateString(),
                'notes' => 'Lent against their AOG. To be replaced like for like.',
            ], $this->storeman);
        });

        // 6. Inbound, on loan and held in stores rather than fitted.
        $this->at(11, 9, function () {
            $this->loans->receiveInbound([
                'party_name' => 'Lagos Avionics Workshop Limited',
                'party_contact' => 'Engr. Tunde Bakare, 0807 333 3333',
                'part_id' => $this->p('GDU-1040')->id,
                'serial_text' => 'LAW-GDU-5518',
                'quantity' => 1,
                'started_at' => Carbon::now()->toDateString(),
                'due_date' => Carbon::now()->addDays(40)->toDateString(),
                'notes' => 'Held as a spare while our display unit is assessed.',
            ], $this->storeman);
        });
    }

    /** Append one backdated event to a shipment's timeline. */
    protected function shipmentEvent(Shipment $shipment, int $daysAfterStart, string $status, ?string $note, bool $isArrival = false): void
    {
        $this->shipments->addEvent($shipment, [
            'status' => $status,
            'event_date' => Carbon::now()->addDays($daysAfterStart)->toDateString(),
            'note' => $note,
            'is_arrival' => $isArrival,
        ], $this->storeman);
    }

    /**
     * The same, with paperwork hung off the entry.
     *
     * The files are generated here rather than shipped as fixtures, so the demo
     * has no binary assets to keep in the repository and the attachments are
     * real documents that open. They go through the ordinary `addEvent` upload
     * path, which means the rows and the files on disk are indistinguishable
     * from a clerk's upload, and `demo:purge` removes both by the same route.
     *
     * @param  array<int, string>  $documents  Basenames, without extension.
     */
    protected function shipmentEventWithPaperwork(
        Shipment $shipment,
        int $daysAfterStart,
        string $status,
        ?string $note,
        array $documents,
    ): void {
        $files = [];

        foreach ($documents as $name) {
            $path = tempnam(sys_get_temp_dir(), 'ncat-demo-');
            file_put_contents($path, $this->paperworkPdf($name, $shipment));
            // `test: true` because nothing here arrived over HTTP, so the
            // is_uploaded_file() check would reject a perfectly real file.
            $files[] = new UploadedFile($path, $name.'.pdf', 'application/pdf', null, true);
        }

        $this->shipments->addEvent($shipment, [
            'status' => $status,
            'event_date' => Carbon::now()->addDays($daysAfterStart)->toDateString(),
            'note' => $note,
            'is_arrival' => false,
        ], $this->storeman, $files);
    }

    /**
     * A small but genuine PDF standing in for a customs release or an airway
     * bill. Rendered through the same engine the vouchers use, so it opens in
     * a reader rather than being bytes with a .pdf on the end.
     */
    protected function paperworkPdf(string $name, Shipment $shipment): string
    {
        $title = str_replace('-', ' ', strtoupper($name));
        $vendor = e($shipment->vendor?->name ?? 'VENDOR');
        $awb = e($shipment->awb_reference ?? '');
        $ref = e($shipment->reference);
        $date = Carbon::now()->format('d F Y');

        return Pdf::loadHTML(<<<HTML
            <div style="font-family: DejaVu Sans, sans-serif; font-size: 11px;">
                <h2 style="color:#101A62; margin:0 0 4px;">{$title}</h2>
                <p style="margin:0 0 14px; color:#68707D;">Specimen document attached to demonstration data.</p>
                <table cellpadding="5" style="border-collapse:collapse;">
                    <tr><td><b>Consignment</b></td><td>{$ref}</td></tr>
                    <tr><td><b>Shipper</b></td><td>{$vendor}</td></tr>
                    <tr><td><b>AWB / tracking</b></td><td>{$awb}</td></tr>
                    <tr><td><b>Consignee</b></td><td>Nigerian College of Aviation Technology, Zaria</td></tr>
                    <tr><td><b>Issued</b></td><td>{$date}</td></tr>
                </table>
                <p style="margin-top:18px; color:#68707D;">
                    This file was produced by the demonstration seeder. It is removed by
                    <code>php artisan demo:purge</code> together with the record it hangs from.
                </p>
            </div>
        HTML)->setPaper('a4')->output();
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
