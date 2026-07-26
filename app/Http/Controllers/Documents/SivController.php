<?php

namespace App\Http\Controllers\Documents;

use App\Http\Controllers\Controller;
use App\Models\Part;
use App\Models\Requisition;
use App\Models\Siv;
use App\Models\Store;
use App\Services\Documents\DocumentNumberService;
use App\Services\Documents\SivService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Number;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class SivController extends Controller
{
    public function index(Request $request): Response
    {
        // Aircraft filter (Phase 4 workspace): SIVs whose lines reference a
        // requisition raised for this aircraft.
        $aircraft = $request->integer('aircraft') ?: null;

        $rows = Siv::query()
            ->when($request->string('status')->toString(), fn ($q, $v) => $q->where('status', $v))
            ->when($request->string('search')->toString(), fn ($q, $v) => $q->where(fn ($w) => $w
                ->where('siv_number', 'like', "%{$v}%")->orWhere('requisition_for', 'like', "%{$v}%")))
            ->when($aircraft, fn ($q, $v) => $q->whereHas('items.requisition', fn ($r) => $r->where('aircraft_id', $v)))
            ->orderByDesc('id')->get()
            ->map(fn (Siv $s) => [
                'id' => $s->id,
                'siv_number' => $s->siv_number,
                'requisition_for' => $s->requisition_for,
                'status' => $s->status,
                'date' => $s->issued_by_date?->toDateString() ?? $s->created_at?->toDateString(),
            ]);

        return Inertia::render('Siv/Index', [
            'sivs' => $rows,
            'filters' => [
                'status' => $request->string('status')->toString(),
                'search' => $request->string('search')->toString(),
                'aircraft' => $aircraft,
            ],
        ]);
    }

    public function create(Request $request): Response
    {
        return Inertia::render('Siv/Create', [
            'parts' => Part::orderBy('part_number')->get(['id', 'part_number', 'description', 'is_serialized']),
            'stores' => Store::whereIn('type', SivService::ISSUABLE_STORE_TYPES)->orderBy('sort_order')
                ->get(['id', 'name', 'slug', 'type']),
            'approvedRequisitions' => $this->approvedRequisitions(),
            'nextNumber' => app(DocumentNumberService::class)->peek('siv'),
            // Pre-scoping from a store page: lines default to this store.
            'storeId' => $request->integer('store') ?: null,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);

        $siv = Siv::create($this->header($data) + [
            'siv_number' => app(DocumentNumberService::class)->reserve('siv'),
            'created_by_user_id' => $request->user()->id,
        ]);

        $this->syncItems($siv, $data['items']);

        return redirect()->route('issuing.show', $siv)->with('success', "SIV {$siv->siv_number} saved as draft.");
    }

    public function show(Siv $siv): Response
    {
        $siv->load(['items.part:id,part_number,description', 'items.sourceStore:id,name', 'items.requisition:id,requisition_no', 'postedBy:id,name']);

        return Inertia::render('Siv/Show', [
            'siv' => [
                'id' => $siv->id,
                'siv_number' => $siv->siv_number,
                'requisition_id' => $siv->requisition_id,
                'requisition_for' => $siv->requisition_for,
                'ordered_by' => $siv->ordered_by,
                'ordered_by_date' => $siv->ordered_by_date?->toDateString(),
                'school_section' => $siv->school_section,
                'approved_by' => $siv->approved_by,
                'approved_by_date' => $siv->approved_by_date?->toDateString(),
                'entered_by' => $siv->entered_by,
                'entered_by_date' => $siv->entered_by_date?->toDateString(),
                'issued_by' => $siv->issued_by,
                'issued_by_date' => $siv->issued_by_date?->toDateString(),
                'received_by' => $siv->received_by,
                'received_by_date' => $siv->received_by_date?->toDateString(),
                'remark' => $siv->remark,
                'status' => $siv->status,
                'posted_at' => $siv->posted_at?->toDayDateTimeString(),
                'posted_by' => $siv->postedBy?->name,
                'items' => $siv->items->map(fn ($i) => [
                    'id' => $i->id, 'line_no' => $i->line_no,
                    'part_number' => $i->part->part_number,
                    'description' => $i->description ?? $i->part->description,
                    'qty_required' => (float) $i->qty_required,
                    'qty_issued' => (float) $i->qty_issued,
                    'qty_issued_words' => Number::spell((int) $i->qty_issued),
                    'store' => $i->sourceStore->name,
                    // Issued out of a store NCAT does not own, so the voucher
                    // says so on its face rather than reading like NCAT stock.
                    'is_loaned_property' => ! $i->sourceStore->owned,
                    'stores_folio' => $i->stores_folio,
                    'rate' => $i->rate, 'amount' => $i->amount,
                    'charging_code' => $i->charging_code,
                    'requisition_no' => $i->requisition?->requisition_no,
                ]),
            ],
        ]);
    }

    public function update(Request $request, Siv $siv): RedirectResponse
    {
        abort_if($siv->isPosted(), 422, 'A posted SIV is read-only.');
        $data = $this->validated($request);

        $siv->update($this->header($data));
        $siv->items()->delete();
        $this->syncItems($siv, $data['items']);

        return redirect()->route('issuing.show', $siv)->with('success', "SIV {$siv->siv_number} updated.");
    }

    public function post(Request $request, Siv $siv, SivService $service): RedirectResponse
    {
        abort_if($siv->isPosted(), 422, 'This SIV has already been posted.');

        $siv->load(['items.part', 'items.sourceStore', 'items.requisition']);
        if ($errors = $service->validationErrors($siv)) {
            throw ValidationException::withMessages($errors);
        }

        $service->post($siv, $request->user());

        return redirect()->route('issuing.show', $siv)->with('success', "SIV {$siv->siv_number} posted. Stock issued.");
    }

    /**
     * Voucher header values. When the voucher is linked to a requisition, the
     * "Requisition for", "Ordered by" and request date are read from that
     * requisition and whatever the client sent for those fields is discarded.
     * They are rendered read-only, but the server does not rely on that.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function header(array $data): array
    {
        $header = collect($data)->except('items')->all();
        $requisition = ! empty($data['requisition_id'])
            ? Requisition::with('requestedBy:id,name')->find($data['requisition_id'])
            : null;

        if (! $requisition) {
            $header['requisition_id'] = null;

            return $header;
        }

        return array_replace($header, [
            'requisition_for' => $this->requisitionLabel($requisition),
            'ordered_by' => $requisition->requestedBy?->name,
            'ordered_by_date' => $requisition->submitted_at?->toDateString()
                ?? $requisition->requisition_date?->toDateString(),
        ]);
    }

    protected function requisitionLabel(Requisition $r): string
    {
        return trim("{$r->requisition_no} - {$r->full_description}");
    }

    protected function syncItems(Siv $siv, array $items): void
    {
        foreach (array_values($items) as $n => $item) {
            $siv->items()->create([
                'line_no' => $n + 1,
                'requisition_id' => $item['requisition_id'] ?? null,
                'part_id' => $item['part_id'],
                'description' => $item['description'] ?? null,
                'qty_required' => $item['qty_required'],
                'qty_issued' => $item['qty_issued'] ?? 0,
                'source_store_id' => $item['source_store_id'],
                'stores_folio' => $item['stores_folio'] ?? null,
                'rate' => $item['rate'] ?? null,
                'amount' => $item['amount'] ?? null,
                'charging_code' => $item['charging_code'] ?? null,
                'part_batch_id' => $item['part_batch_id'] ?? null,
                'serial_ids' => array_values(array_filter((array) ($item['serial_ids'] ?? []))),
            ]);
        }
    }

    protected function validated(Request $request): array
    {
        return $request->validate([
            // Only a fully approved, not-yet-issued requisition may be linked.
            'requisition_id' => ['nullable', Rule::exists('requisitions', 'id')->where('status', 'approved')],
            'requisition_for' => ['nullable', 'string', 'max:255'],
            'ordered_by' => ['nullable', 'string', 'max:255'],
            'ordered_by_date' => ['nullable', 'date'],
            'school_section' => ['nullable', 'string', 'max:255'],
            'approved_by' => ['nullable', 'string', 'max:255'],
            'approved_by_date' => ['nullable', 'date'],
            'entered_by' => ['nullable', 'string', 'max:255'],
            'entered_by_date' => ['nullable', 'date'],
            'issued_by' => ['nullable', 'string', 'max:255'],
            'issued_by_date' => ['nullable', 'date'],
            'received_by' => ['nullable', 'string', 'max:255'],
            'received_by_date' => ['nullable', 'date'],
            'remark' => ['nullable', 'string', 'max:2000'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.requisition_id' => ['nullable', 'exists:requisitions,id'],
            'items.*.part_id' => ['required', 'exists:parts,id'],
            'items.*.description' => ['nullable', 'string', 'max:500'],
            'items.*.qty_required' => ['required', 'numeric', 'gt:0'],
            'items.*.qty_issued' => ['nullable', 'numeric', 'min:0'],
            'items.*.source_store_id' => ['required', 'exists:stores,id'],
            'items.*.stores_folio' => ['nullable', 'string', 'max:100'],
            'items.*.rate' => ['nullable', 'numeric', 'min:0'],
            'items.*.amount' => ['nullable', 'numeric', 'min:0'],
            'items.*.charging_code' => ['nullable', 'string', 'max:100'],
            'items.*.part_batch_id' => ['nullable', 'exists:part_batches,id'],
            'items.*.serial_ids' => ['nullable', 'array'],
            'items.*.serial_ids.*' => ['nullable', 'integer', 'exists:part_serials,id'],
        ]);
    }

    /**
     * The "Requisition for" picker: fully approved requisitions that have not
     * been issued yet. Issuing moves a requisition to `issued`, so the status
     * filter covers both halves of that rule.
     *
     * Each row carries the requester's name and submitted date, which the form
     * shows read-only and the server re-derives on save.
     */
    protected function approvedRequisitions()
    {
        return Requisition::where('status', 'approved')
            ->with(['aircraft:id,registration', 'requestedBy:id,name'])
            ->orderByDesc('id')
            ->get(['id', 'requisition_no', 'full_description', 'part_id', 'part_no', 'aircraft_id',
                'requested_by_user_id', 'submitted_at', 'requisition_date'])
            ->map(fn (Requisition $r) => [
                'id' => $r->id,
                'requisition_no' => $r->requisition_no,
                'full_description' => $r->full_description,
                'part_id' => $r->part_id,
                'part_no' => $r->part_no,
                'aircraft' => $r->aircraft?->registration,
                'ordered_by' => $r->requestedBy?->name,
                'ordered_by_date' => $r->submitted_at?->toDateString() ?? $r->requisition_date?->toDateString(),
                'label' => $this->requisitionLabel($r),
            ]);
    }
}
