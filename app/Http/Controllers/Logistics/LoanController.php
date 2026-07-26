<?php

namespace App\Http\Controllers\Logistics;

use App\Exceptions\Stock\StockException;
use App\Http\Controllers\Controller;
use App\Models\Aircraft;
use App\Models\Loan;
use App\Models\Part;
use App\Models\PartSerial;
use App\Models\Store;
use App\Models\Vendor;
use App\Services\Stock\LoanService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Loans in both directions (spec §12.7). One screen with two views, because the
 * mechanics differ enough that mixing the forms would mislead: an outbound loan
 * takes stock out of a store NCAT owns, an inbound one brings in property NCAT
 * does not.
 */
class LoanController extends Controller
{
    public function __construct(protected LoanService $loans)
    {
    }

    public function index(Request $request): Response
    {
        $direction = $request->string('direction')->toString() ?: 'out';
        $direction = in_array($direction, ['out', 'in'], true) ? $direction : 'out';

        $filters = [
            'direction' => $direction,
            'status' => $request->string('status')->toString(),   // on_loan | overdue | returned | written_off
            'search' => $request->string('search')->toString(),
        ];

        $rows = Loan::query()
            ->where('direction', $direction)
            ->with(['vendor:id,name', 'part:id,part_number,description', 'fromStore:id,name', 'installedAircraft:id,registration'])
            ->when($filters['status'] === 'overdue', fn ($q) => $q->overdue())
            ->when(in_array($filters['status'], ['on_loan', 'returned', 'written_off'], true),
                fn ($q) => $q->where('status', $filters['status']))
            ->when($filters['search'], fn ($q, $v) => $q->where(fn ($w) => $w
                ->where('party_name', 'like', "%{$v}%")
                ->orWhere('item_description', 'like', "%{$v}%")
                ->orWhere('serial_text', 'like', "%{$v}%")
                ->orWhereHas('vendor', fn ($r) => $r->where('name', 'like', "%{$v}%"))
                ->orWhereHas('part', fn ($r) => $r->where('part_number', 'like', "%{$v}%"))))
            ->orderByRaw("case when status = 'on_loan' then 0 else 1 end")
            ->orderBy('due_date')
            ->orderByDesc('id')
            ->get()
            ->map(fn (Loan $l) => $this->payload($l));

        return Inertia::render('Logistics/Loans/Index', [
            'loans' => $rows,
            'filters' => $filters,
            'counts' => [
                'out' => Loan::outbound()->open()->count(),
                'in' => Loan::inbound()->open()->count(),
                'overdue' => Loan::overdue()->count(),
            ],
            'formProps' => $this->formProps(),
            'can' => $this->abilities($request),
        ]);
    }

    public function show(Request $request, Loan $loan): Response
    {
        $loan->load(['vendor', 'part', 'serial', 'fromStore', 'installedAircraft', 'createdBy:id,name', 'writtenOffBy:id,name']);

        return Inertia::render('Logistics/Loans/Show', [
            'loan' => $this->payload($loan) + [
                'notes' => $loan->notes,
                'created_by' => $loan->createdBy?->name,
                'written_off_by' => $loan->writtenOffBy?->name,
                'write_off_reason' => $loan->write_off_reason,
                'return_condition' => $loan->return_condition,
            ],
            'aircraft' => Aircraft::orderBy('registration')->get(['id', 'registration']),
            'can' => $this->abilities($request),
        ]);
    }

    public function storeOutbound(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'vendor_id' => ['nullable', 'exists:vendors,id'],
            'party_name' => ['nullable', 'string', 'max:255', 'required_without:vendor_id'],
            'party_contact' => ['nullable', 'string', 'max:255'],
            'part_id' => ['required', 'exists:parts,id'],
            'part_serial_id' => ['nullable', 'exists:part_serials,id'],
            'part_batch_id' => ['nullable', 'exists:part_batches,id'],
            'quantity' => ['required', 'numeric', 'gt:0'],
            'from_store_id' => ['required', 'exists:stores,id'],
            'started_at' => ['required', 'date'],
            'due_date' => ['nullable', 'date', 'after_or_equal:started_at'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        try {
            $loan = $this->loans->issueOutbound($data, $request->user());
        } catch (StockException $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }

        return redirect()->route('loans.show', $loan)
            ->with('success', 'Loan recorded. Stock is now showing as on loan rather than in store.');
    }

    public function storeInbound(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'vendor_id' => ['nullable', 'exists:vendors,id'],
            'party_name' => ['nullable', 'string', 'max:255', 'required_without:vendor_id'],
            'party_contact' => ['nullable', 'string', 'max:255'],
            // Nullable on purpose: a borrowed item is often not in the catalogue.
            'part_id' => ['nullable', 'exists:parts,id'],
            'item_description' => ['nullable', 'string', 'max:1000', 'required_without:part_id'],
            'serial_text' => ['nullable', 'string', 'max:255'],
            'quantity' => ['required', 'numeric', 'gt:0'],
            'started_at' => ['required', 'date'],
            'due_date' => ['nullable', 'date', 'after_or_equal:started_at'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        try {
            $loan = $this->loans->receiveInbound($data, $request->user());
        } catch (StockException $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }

        return redirect()->route('loans.show', $loan)
            ->with('success', 'Inbound loan recorded. It is tracked but excluded from NCAT stock value.');
    }

    public function recordReturn(Request $request, Loan $loan): RedirectResponse
    {
        $data = $request->validate([
            'returned_at' => ['nullable', 'date'],
            'return_condition' => ['nullable', 'string', 'max:2000'],
        ]);

        try {
            $this->loans->recordReturn($loan, $data, $request->user());
        } catch (StockException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', 'Return recorded.');
    }

    /**
     * Write off an unreturned outbound loan. Gated on `stock.adjust` rather than
     * `loans.manage`: this posts a ledger adjustment, so it needs the permission
     * that governs adjustments everywhere else.
     */
    public function writeOff(Request $request, Loan $loan): RedirectResponse
    {
        abort_unless($request->user()->can('stock.adjust'), 403);

        $data = $request->validate([
            'write_off_reason' => ['required', 'string', 'min:5', 'max:2000'],
        ]);

        try {
            $this->loans->writeOff($loan, $data['write_off_reason'], $request->user());
        } catch (StockException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', 'Loan written off. The adjustment is on the ledger with your reason attached.');
    }

    public function install(Request $request, Loan $loan): RedirectResponse
    {
        $data = $request->validate([
            'installed_aircraft_id' => ['required', 'exists:aircraft,id'],
        ]);

        try {
            $this->loans->installInbound($loan, (int) $data['installed_aircraft_id']);
        } catch (StockException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', 'Recorded as fitted. It is marked as loaned property wherever it appears.');
    }

    /** @return array<string, mixed> */
    protected function payload(Loan $l): array
    {
        return [
            'id' => $l->id,
            'direction' => $l->direction,
            'counterparty' => $l->counterparty(),
            'vendor_id' => $l->vendor_id,
            'party_contact' => $l->party_contact,
            'item_label' => $l->itemLabel(),
            'part_id' => $l->part_id,
            'part_number' => $l->part?->part_number,
            'serial' => $l->serial?->serial_number ?? $l->serial_text,
            'quantity' => (float) $l->quantity,
            'from_store' => $l->fromStore?->name,
            'started_at' => $l->started_at?->toDateString(),
            'due_date' => $l->due_date?->toDateString(),
            'status' => $l->status,
            'display_status' => $l->displayStatus(),
            'is_overdue' => $l->isOverdue(),
            'days_overdue' => $l->daysOverdue(),
            'returned_at' => $l->returned_at?->toDateString(),
            'installed_aircraft' => $l->installedAircraft?->registration,
            'is_loaned_property' => $l->direction === 'in',
        ];
    }

    /** @return array<string, mixed> */
    protected function formProps(): array
    {
        return [
            'vendors' => Vendor::where('is_active', true)->orderBy('name')->get(['id', 'name']),
            'parts' => Part::orderBy('part_number')->get(['id', 'part_number', 'description', 'is_serialized']),
            // Same rule as issuing: an outbound loan only leaves a serviceable store.
            'stores' => Store::whereIn('type', ['bonded', 'dope'])->where('is_active', true)
                ->orderBy('sort_order')->get(['id', 'name', 'type']),
            'serials' => PartSerial::where('status', 'in_store')->whereNotNull('current_store_id')
                ->get(['id', 'part_id', 'serial_number', 'current_store_id']),
        ];
    }

    /** @return array<string, bool> */
    protected function abilities(Request $request): array
    {
        return [
            'manage' => $request->user()->can('loans.manage'),
            'write_off' => $request->user()->can('stock.adjust'),
        ];
    }
}
